<?php
/**
 * Telegram Bot API wrapper
 */
require_once __DIR__ . '/../includes/functions.php';

class TelegramAPI {

    private static function token(): string {
        return setting('telegram_bot_token', '');
    }

    public static function call(string $method, array $params = []): array {
        $token = self::token();
        if (!$token) return ['ok' => false, 'error' => 'No token'];

        $url = "https://api.telegram.org/bot$token/$method";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $params,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($resp, true);
        return is_array($data) ? $data : ['ok' => false, 'error' => 'Invalid response'];
    }

    public static function sendMessage(int $chatId, string $text, array $extra = []): array {
        return self::call('sendMessage', array_merge([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ], $extra));
    }

    public static function sendPhoto(int $chatId, string $photo, string $caption = '', array $extra = []): array {
        return self::call('sendPhoto', array_merge([
            'chat_id'    => $chatId,
            'photo'      => $photo,
            'caption'    => $caption,
            'parse_mode' => 'HTML',
        ], $extra));
    }

    public static function answerCallback(string $callbackId, string $text = '', bool $alert = false): array {
        return self::call('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text'              => $text,
            'show_alert'        => $alert,
        ]);
    }

    public static function editMessage(int $chatId, int $messageId, string $text, array $extra = []): array {
        return self::call('editMessageText', array_merge([
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ], $extra));
    }

    public static function getFile(string $fileId): ?string {
        $r = self::call('getFile', ['file_id' => $fileId]);
        if (!($r['ok'] ?? false) || empty($r['result']['file_path'])) return null;
        $token = self::token();
        return "https://api.telegram.org/file/bot$token/" . $r['result']['file_path'];
    }

    public static function downloadFile(string $fileId, string $destPath): bool {
        $url = self::getFile($fileId);
        if (!$url) return false;
        $data = @file_get_contents($url);
        if ($data === false) return false;
        return @file_put_contents($destPath, $data) !== false;
    }

    /** Adminga xabar yuborish (chat_id sozlamadan) */
    public static function notifyAdmin(string $text, array $extra = []): void {
        $adminId = (int)setting('telegram_admin_chat_id', 0);
        if ($adminId) {
            self::sendMessage($adminId, $text, $extra);
        }
    }

    /** Webhook ni o'rnatish (admin paneldan) */
    public static function setWebhook(string $url): array {
        return self::call('setWebhook', ['url' => $url]);
    }

    public static function deleteWebhook(): array {
        return self::call('deleteWebhook');
    }

    public static function getWebhookInfo(): array {
        return self::call('getWebhookInfo');
    }
}
