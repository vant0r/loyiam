<?php
/**
 * PAYME (Paycom) to'lov tizimi integratsiyasi - JSON-RPC 2.0
 *
 * Payme 6 ta methodga POST yuboradi:
 *  - CheckPerformTransaction
 *  - CreateTransaction
 *  - PerformTransaction
 *  - CancelTransaction
 *  - CheckTransaction
 *  - GetStatement
 *
 * Avtorizatsiya: Authorization: Basic base64(Paycom:KEY)
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../functions.php';

class PaymePayment {

    // Standart Payme xato kodlari
    const ERR_INSUFFICIENT_PRIVILEGE = -32504;
    const ERR_METHOD_NOT_FOUND       = -32601;
    const ERR_INVALID_AMOUNT         = -31001;
    const ERR_TRANSACTION_NOT_FOUND  = -31003;
    const ERR_CANNOT_CANCEL          = -31007;
    const ERR_CANNOT_PERFORM         = -31008;
    const ERR_USER_NOT_FOUND         = -31050;

    // Transaction states
    const STATE_CREATED   = 1;
    const STATE_COMPLETED = 2;
    const STATE_CANCELLED = -1;
    const STATE_CANCELLED_AFTER = -2;

    /** Webhook handler */
    public static function handle(): void {
        header('Content-Type: application/json; charset=utf-8');

        if (!self::auth_check()) {
            echo json_encode(['error' => [
                'code' => self::ERR_INSUFFICIENT_PRIVILEGE,
                'message' => ['ru' => 'Недостаточно привилегий', 'uz' => 'Ruxsat yo\'q', 'en' => 'Insufficient privilege'],
            ]]);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body) || empty($body['method'])) {
            echo json_encode(['error' => ['code' => -32600, 'message' => ['en' => 'Invalid request']]]);
            return;
        }

        $method = $body['method'];
        $params = $body['params'] ?? [];
        $id     = $body['id'] ?? null;

        $result = match($method) {
            'CheckPerformTransaction' => self::checkPerform($params),
            'CreateTransaction'       => self::create($params),
            'PerformTransaction'      => self::perform($params),
            'CancelTransaction'       => self::cancel($params),
            'CheckTransaction'        => self::check($params),
            'GetStatement'            => self::statement($params),
            default => ['error' => ['code' => self::ERR_METHOD_NOT_FOUND, 'message' => ['en' => 'Method not found']]],
        };

        echo json_encode(array_merge(['id' => $id, 'jsonrpc' => '2.0'], $result));
    }

    private static function auth_check(): bool {
        $key = setting('payme_secret_key', '');
        if (!$key) return false;
        $expected = 'Basic ' . base64_encode("Paycom:$key");
        return ($_SERVER['HTTP_AUTHORIZATION'] ?? '') === $expected;
    }

    /** account.order_id orqali payment topish */
    private static function find_payment(array $params): ?array {
        $orderId = (int)($params['account']['order_id'] ?? 0);
        if (!$orderId) return null;
        return db()->fetch("SELECT * FROM payments WHERE id = ?", [$orderId]);
    }

    private static function checkPerform(array $p): array {
        $payment = self::find_payment($p);
        if (!$payment) {
            return ['error' => ['code' => self::ERR_USER_NOT_FOUND, 'message' => ['en' => 'Order not found']]];
        }
        // Payme summaga *100 ko'paytirib yuboradi (tiyin)
        if ((int)($p['amount'] ?? 0) !== (int)($payment['amount'] * 100)) {
            return ['error' => ['code' => self::ERR_INVALID_AMOUNT, 'message' => ['en' => 'Invalid amount']]];
        }
        return ['result' => ['allow' => true]];
    }

    private static function create(array $p): array {
        $payment = self::find_payment($p);
        if (!$payment) {
            return ['error' => ['code' => self::ERR_USER_NOT_FOUND, 'message' => ['en' => 'Order not found']]];
        }
        if ((int)($p['amount'] ?? 0) !== (int)($payment['amount'] * 100)) {
            return ['error' => ['code' => self::ERR_INVALID_AMOUNT, 'message' => ['en' => 'Invalid amount']]];
        }

        $payme_id = $p['id'];
        $time     = (int)($p['time'] ?? (time() * 1000));

        // Mavjud tranzaksiyani qidiramiz
        $existing = db()->fetch("SELECT * FROM payments WHERE transaction_id = ?", [$payme_id]);
        if ($existing) {
            return ['result' => [
                'create_time' => $time,
                'transaction' => (string)$existing['id'],
                'state'       => self::STATE_CREATED,
            ]];
        }

        // Yangi tranzaksiyani belgilash
        db()->execute("UPDATE payments SET transaction_id = ?, method = 'payme' WHERE id = ?",
            [$payme_id, $payment['id']]);

        return ['result' => [
            'create_time' => $time,
            'transaction' => (string)$payment['id'],
            'state'       => self::STATE_CREATED,
        ]];
    }

    private static function perform(array $p): array {
        $payment = db()->fetch("SELECT * FROM payments WHERE transaction_id = ?", [$p['id'] ?? '']);
        if (!$payment) {
            return ['error' => ['code' => self::ERR_TRANSACTION_NOT_FOUND, 'message' => ['en' => 'Transaction not found']]];
        }
        if ($payment['status'] === 'rejected') {
            return ['error' => ['code' => self::ERR_CANNOT_PERFORM, 'message' => ['en' => 'Cancelled']]];
        }
        if ($payment['status'] !== 'approved') {
            db()->execute("UPDATE payments SET status='approved' WHERE id=?", [$payment['id']]);

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
            Security::audit('payment_approved', "Payme to'lov: {$payment['amount']} so'm", 'info', $payment['user_id']);
        }

        return ['result' => [
            'transaction'  => (string)$payment['id'],
            'perform_time' => time() * 1000,
            'state'        => self::STATE_COMPLETED,
        ]];
    }

    private static function cancel(array $p): array {
        $payment = db()->fetch("SELECT * FROM payments WHERE transaction_id = ?", [$p['id'] ?? '']);
        if (!$payment) {
            return ['error' => ['code' => self::ERR_TRANSACTION_NOT_FOUND, 'message' => ['en' => 'Transaction not found']]];
        }
        $newState = $payment['status'] === 'approved' ? self::STATE_CANCELLED_AFTER : self::STATE_CANCELLED;
        db()->execute("UPDATE payments SET status='rejected' WHERE id=?", [$payment['id']]);

        // Approved bo'lsa tarif bekor
        if ($payment['status'] === 'approved' && $payment['tariff_id']) {
            db()->execute("UPDATE users SET tariff_id = NULL, tariff_expires_at = NULL WHERE id = ?
                           AND tariff_id = ?", [$payment['user_id'], $payment['tariff_id']]);
        }

        return ['result' => [
            'transaction' => (string)$payment['id'],
            'cancel_time' => time() * 1000,
            'state'       => $newState,
        ]];
    }

    private static function check(array $p): array {
        $payment = db()->fetch("SELECT * FROM payments WHERE transaction_id = ?", [$p['id'] ?? '']);
        if (!$payment) {
            return ['error' => ['code' => self::ERR_TRANSACTION_NOT_FOUND, 'message' => ['en' => 'Transaction not found']]];
        }
        $state = match ($payment['status']) {
            'approved' => self::STATE_COMPLETED,
            'rejected' => self::STATE_CANCELLED,
            default    => self::STATE_CREATED,
        };
        return ['result' => [
            'create_time'  => strtotime($payment['created_at']) * 1000,
            'perform_time' => $payment['status']==='approved' ? strtotime($payment['updated_at']) * 1000 : 0,
            'cancel_time'  => $payment['status']==='rejected' ? strtotime($payment['updated_at']) * 1000 : 0,
            'transaction'  => (string)$payment['id'],
            'state'        => $state,
            'reason'       => null,
        ]];
    }

    private static function statement(array $p): array {
        $from = (int)($p['from'] ?? 0) / 1000;
        $to   = (int)($p['to'] ?? 0) / 1000;
        $rows = db()->fetchAll(
            "SELECT * FROM payments WHERE method='payme' AND UNIX_TIMESTAMP(created_at) BETWEEN ? AND ?",
            [$from, $to]);

        $transactions = [];
        foreach ($rows as $r) {
            $transactions[] = [
                'id'           => $r['transaction_id'],
                'time'         => strtotime($r['created_at']) * 1000,
                'amount'       => (int)($r['amount'] * 100),
                'account'      => ['order_id' => (int)$r['id']],
                'create_time'  => strtotime($r['created_at']) * 1000,
                'perform_time' => $r['status']==='approved' ? strtotime($r['updated_at']) * 1000 : 0,
                'cancel_time'  => $r['status']==='rejected' ? strtotime($r['updated_at']) * 1000 : 0,
                'transaction'  => (string)$r['id'],
                'state'        => $r['status']==='approved' ? self::STATE_COMPLETED : ($r['status']==='rejected' ? self::STATE_CANCELLED : self::STATE_CREATED),
                'reason'       => null,
            ];
        }
        return ['result' => ['transactions' => $transactions]];
    }

    /** Foydalanuvchi uchun Payme to'lov sahifasiga URL */
    public static function build_payment_url(int $payment_id, float $amount): string {
        $merchant_id = setting('payme_merchant_id', '');
        $params = "m=$merchant_id;ac.order_id=$payment_id;a=" . (int)($amount * 100);
        $encoded = base64_encode($params);
        return "https://checkout.paycom.uz/$encoded";
    }
}
