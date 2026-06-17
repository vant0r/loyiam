<?php
/**
 * CLICK to'lov tizimi integratsiyasi
 *
 * Click serveri 2 ta endpointga so'rov yuboradi:
 *   1. Prepare (action=0)  — to'lovni tayyorlash
 *   2. Complete (action=1) — to'lovni tasdiqlash
 *
 * Imzo (sign_string) MD5 orqali tekshiriladi:
 *   prepare:  click_trans_id+service_id+SECRET_KEY+merchant_trans_id+amount+action+sign_time
 *   complete: click_trans_id+service_id+SECRET_KEY+merchant_trans_id+merchant_prepare_id+amount+action+sign_time
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../functions.php';

class ClickPayment {

    const ACTION_PREPARE  = 0;
    const ACTION_COMPLETE = 1;

    const STATUS_OK              = 0;
    const ERR_SIGN               = -1;
    const ERR_AMOUNT             = -2;
    const ERR_ACTION             = -3;
    const ERR_ALREADY_PAID       = -4;
    const ERR_USER_NOT_FOUND     = -5;
    const ERR_TRANS_NOT_FOUND    = -6;
    const ERR_FAILED             = -7;
    const ERR_DATA               = -8;
    const ERR_TRANS_CANCELLED    = -9;

    /** Webhook handler (Click serveridan keladigan POST) */
    public static function handle(): void {
        header('Content-Type: application/json; charset=utf-8');

        $data = $_POST;
        $action = (int)($data['action'] ?? -1);

        if ($action === self::ACTION_PREPARE) {
            echo json_encode(self::prepare($data));
        } elseif ($action === self::ACTION_COMPLETE) {
            echo json_encode(self::complete($data));
        } else {
            echo json_encode([
                'error' => self::ERR_ACTION,
                'error_note' => 'Action not found',
            ]);
        }
    }

    /** Prepare bosqichi */
    private static function prepare(array $data): array {
        if (!self::verify_sign($data, self::ACTION_PREPARE)) {
            return self::error(self::ERR_SIGN, 'SIGN CHECK FAILED!');
        }

        $merchant_trans_id = $data['merchant_trans_id'] ?? '';
        $payment = db()->fetch("SELECT * FROM payments WHERE id = ? OR transaction_id = ?",
            [(int)$merchant_trans_id, $merchant_trans_id]);

        if (!$payment) {
            return self::error(self::ERR_USER_NOT_FOUND, 'Payment not found');
        }
        if ((float)$payment['amount'] != (float)$data['amount']) {
            return self::error(self::ERR_AMOUNT, 'Incorrect amount');
        }
        if ($payment['status'] === 'approved') {
            return self::error(self::ERR_ALREADY_PAID, 'Already paid');
        }

        $merchant_prepare_id = (int)$payment['id']; // bizning DB id
        return [
            'click_trans_id'      => $data['click_trans_id'] ?? '',
            'merchant_trans_id'   => $merchant_trans_id,
            'merchant_prepare_id' => $merchant_prepare_id,
            'error'               => self::STATUS_OK,
            'error_note'          => 'Success',
        ];
    }

    /** Complete bosqichi */
    private static function complete(array $data): array {
        if (!self::verify_sign($data, self::ACTION_COMPLETE)) {
            return self::error(self::ERR_SIGN, 'SIGN CHECK FAILED!');
        }

        $merchant_trans_id = $data['merchant_trans_id'] ?? '';
        $payment = db()->fetch("SELECT * FROM payments WHERE id = ? OR transaction_id = ?",
            [(int)$merchant_trans_id, $merchant_trans_id]);

        if (!$payment) return self::error(self::ERR_USER_NOT_FOUND, 'Payment not found');
        if ((float)$payment['amount'] != (float)$data['amount']) {
            return self::error(self::ERR_AMOUNT, 'Incorrect amount');
        }

        $error = (int)($data['error'] ?? 0);

        if ($error < 0) {
            // Click tomonidan rad etilgan
            db()->execute("UPDATE payments SET status='rejected', note=? WHERE id=?",
                ["Click rejected: " . ($data['error_note'] ?? ''), $payment['id']]);
            return self::error(self::ERR_TRANS_CANCELLED, 'Transaction cancelled');
        }

        // Muvaffaqiyatli to'lov
        db()->execute("UPDATE payments SET status='approved', method='click', transaction_id=? WHERE id=?",
            [$data['click_trans_id'] ?? '', $payment['id']]);

        // Tarif faollashtirish
        if ($payment['tariff_id']) {
            $tariff = db()->fetch("SELECT * FROM tariffs WHERE id=?", [$payment['tariff_id']]);
            if ($tariff) {
                $expires = date('Y-m-d H:i:s', strtotime("+{$tariff['duration_days']} days"));
                db()->execute("UPDATE users SET tariff_id=?, tariff_expires_at=? WHERE id=?",
                    [$payment['tariff_id'], $expires, $payment['user_id']]);
            }
        }

        require_once __DIR__ . '/../security.php';
        Security::audit('payment_approved', "Click to'lov: {$payment['amount']} so'm", 'info', $payment['user_id']);

        return [
            'click_trans_id'      => $data['click_trans_id'] ?? '',
            'merchant_trans_id'   => $merchant_trans_id,
            'merchant_confirm_id' => $payment['id'],
            'error'               => self::STATUS_OK,
            'error_note'          => 'Success',
        ];
    }

    /** MD5 imzosini tekshirish (timing-safe + replay protection) */
    private static function verify_sign(array $d, int $action): bool {
        $secret = setting('click_secret_key', '');
        if (!$secret) return false;

        // Replay protection: sign_time 30 daqiqadan eski bo'lmasligi kerak
        $signTime = $d['sign_time'] ?? '';
        if ($signTime) {
            // Click sign_time formatlari farqlanadi — sanaga aylantirib taqqoslaymiz
            $ts = strtotime(str_replace('-', '', substr($signTime, 0, 14)));
            if ($ts !== false && abs(time() - $ts) > 1800) {
                return false;
            }
        }

        if ($action === self::ACTION_PREPARE) {
            $str = ($d['click_trans_id'] ?? '')
                 . ($d['service_id'] ?? '')
                 . $secret
                 . ($d['merchant_trans_id'] ?? '')
                 . ($d['amount'] ?? '')
                 . ($d['action'] ?? '')
                 . ($d['sign_time'] ?? '');
        } else {
            $str = ($d['click_trans_id'] ?? '')
                 . ($d['service_id'] ?? '')
                 . $secret
                 . ($d['merchant_trans_id'] ?? '')
                 . ($d['merchant_prepare_id'] ?? '')
                 . ($d['amount'] ?? '')
                 . ($d['action'] ?? '')
                 . ($d['sign_time'] ?? '');
        }

        $expected = md5($str);
        $given    = (string)($d['sign_string'] ?? '');
        if (strlen($expected) !== strlen($given)) return false;

        // Timing-safe compare
        return hash_equals($expected, $given);
    }

    private static function error(int $code, string $note): array {
        return ['error' => $code, 'error_note' => $note];
    }

    /** Foydalanuvchi uchun Click to'lov sahifasiga URL yaratish */
    public static function build_payment_url(int $payment_id, float $amount, string $return_url = ''): string {
        $merchant_id = setting('click_merchant_id', '');
        $service_id  = setting('click_service_id', '');
        $base = 'https://my.click.uz/services/pay';
        $params = [
            'service_id'        => $service_id,
            'merchant_id'       => $merchant_id,
            'amount'            => $amount,
            'transaction_param' => $payment_id,
            'return_url'        => $return_url ?: SITE_URL . '/user/tariflar.php',
        ];
        return $base . '?' . http_build_query($params);
    }
}
