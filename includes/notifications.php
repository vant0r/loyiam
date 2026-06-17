<?php
/**
 * Notifications helper — foydalanuvchilarga xabarnoma yuborish
 *
 * Foydalanish:
 *   Notify::send($userId, 'payment_approved', 'To\'lov tasdiqlandi!', 'Tarifingiz faollashtirildi', ['link' => '/user/']);
 *   Notify::unread($userId)        // sanoq
 *   Notify::list($userId, 10)      // ro'yxat
 *   Notify::markAllRead($userId)
 */

class Notify {

    /** Yangi xabarnoma yaratish */
    public static function send(int $userId, string $type, string $title, string $message = '', array $opts = []): bool {
        if (!$userId) return false;

        $link = $opts['link'] ?? null;
        $icon = $opts['icon'] ?? self::iconForType($type);

        $ok = db()->execute(
            "INSERT INTO notifications (user_id, type, title, message, link, icon)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$userId, $type, $title, $message, $link, $icon]
        );

        // Telegramga ham yuboramiz (agar bog'langan bo'lsa)
        if ($ok && !empty($opts['telegram'])) {
            self::sendTelegram($userId, $title, $message, $link);
        }

        return (bool)$ok;
    }

    /** Adminlarga yuborish */
    public static function sendToAdmins(string $type, string $title, string $message = '', array $opts = []): int {
        $admins = db()->fetchAll("SELECT id FROM users WHERE role IN ('admin','developer') AND status='active'");
        $count = 0;
        foreach ($admins as $a) {
            if (self::send((int)$a['id'], $type, $title, $message, $opts)) $count++;
        }
        return $count;
    }

    /** Telegram orqali xabar (foydalanuvchi telegram_id si bo'lsa) */
    public static function sendTelegram(int $userId, string $title, string $message, ?string $link = null): void {
        $u = db()->fetch("SELECT telegram_id FROM users WHERE id = ?", [$userId]);
        if (!$u || empty($u['telegram_id'])) return;

        $text = "<b>" . htmlspecialchars($title) . "</b>";
        if ($message) $text .= "\n\n" . htmlspecialchars($message);
        if ($link) $text .= "\n\n🔗 " . SITE_URL . $link;

        try {
            require_once __DIR__ . '/../telegram/api.php';
            TelegramAPI::sendMessage((int)$u['telegram_id'], $text);
        } catch (Throwable $e) {
            // Silent
        }
    }

    /** O'qilmagan sanoq */
    public static function unread(int $userId): int {
        $r = db()->fetch("SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0", [$userId]);
        return (int)($r['c'] ?? 0);
    }

    /** Ro'yxat */
    public static function list(int $userId, int $limit = 20): array {
        return db()->fetchAll(
            "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT $limit",
            [$userId]
        );
    }

    /** Bitta o'qilgan deb belgilash */
    public static function markRead(int $userId, int $notifId): bool {
        return db()->execute(
            "UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?",
            [$notifId, $userId]
        );
    }

    /** Hammasi o'qilgan */
    public static function markAllRead(int $userId): bool {
        return db()->execute(
            "UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0",
            [$userId]
        );
    }

    /** O'chirish */
    public static function delete(int $userId, int $notifId): bool {
        return db()->execute(
            "DELETE FROM notifications WHERE id=? AND user_id=?",
            [$notifId, $userId]
        );
    }

    /** Eskilarni tozalash (cron) */
    public static function cleanup(int $daysOld = 60): int {
        db()->execute(
            "DELETE FROM notifications WHERE is_read=1 AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$daysOld]
        );
        return (int)(db()->fetch("SELECT ROW_COUNT() c")['c'] ?? 0);
    }

    /** Type uchun standart icon */
    private static function iconForType(string $type): string {
        return match ($type) {
            'payment_approved'  => 'check-circle',
            'payment_rejected'  => 'x-circle',
            'payment_pending'   => 'clock',
            'tariff_activated'  => 'gem',
            'tariff_expiring'   => 'flame',
            'tariff_expired'    => 'x-circle',
            'achievement'       => 'trophy',
            'test_completed'    => 'check',
            'new_message'       => 'message',
            'admin_alert'       => 'shield',
            'welcome'           => 'star',
            default             => 'bell',
        };
    }

    /** Type uchun rang */
    public static function colorForType(string $type): string {
        return match ($type) {
            'payment_approved', 'tariff_activated', 'achievement', 'welcome' => 'success',
            'payment_rejected', 'tariff_expired', 'admin_alert'              => 'danger',
            'payment_pending', 'tariff_expiring'                              => 'warning',
            default                                                           => 'info',
        };
    }
}
