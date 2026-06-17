<?php
/**
 * Kunlik cron job — har kuni 00:05 da ishga tushuriladi
 *
 * Crontab namunasi:
 *   5 0 * * * /usr/bin/php /var/www/vatanparvar-yaypan/cron/daily.php
 *
 * Yoki HTTP orqali (URL secret bilan):
 *   wget -q -O - https://your-domain.uz/cron/daily.php?secret=YOUR_SECRET
 */

// CLI tekshiruv yoki secret URL parameter (timing-safe compare)
$is_cli = php_sapi_name() === 'cli';
// Secret'ni .env'dan o'qish tavsiya etiladi
$cron_secret = $_ENV['CRON_SECRET'] ?? 'CHANGE_ME_TO_A_RANDOM_SECRET_STRING';

if (!$is_cli) {
    $given = (string)($_GET['secret'] ?? '');
    // Production'da default secret bo'lmasin
    if ($cron_secret === 'CHANGE_ME_TO_A_RANDOM_SECRET_STRING') {
        http_response_code(403);
        echo "Forbidden — set CRON_SECRET in .env";
        exit;
    }
    if (strlen($given) !== strlen($cron_secret) || !hash_equals($cron_secret, $given)) {
        http_response_code(403);
        // Audit log
        @error_log("[cron/daily.php] BLOCKED: wrong secret from " . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        echo "Forbidden";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cache.php';

$startTime = microtime(true);
echo "===== VatanParvar Yaypan Daily Cron =====\n";
echo "Boshlandi: " . date('Y-m-d H:i:s') . "\n\n";

$totals = [
    'expired_tariffs'    => 0,
    'expiring_warned'    => 0,
    'expired_attempts'   => 0,
    'old_logs_deleted'   => 0,
    'old_pending_payments' => 0,
    'cache_cleaned'      => 0,
];

// ============================================================
// 1. Tugagan tariflarni "expired" qilish
// ============================================================
echo "[1/6] Tarif muddati tugaganlarni tekshirish...\n";
$expired = db()->fetchAll(
    "SELECT id, first_name, telegram_id FROM users
     WHERE tariff_id IS NOT NULL AND tariff_expires_at IS NOT NULL
     AND tariff_expires_at < NOW()");

foreach ($expired as $u) {
    db()->execute(
        "UPDATE users SET tariff_id = NULL, tariff_expires_at = NULL WHERE id = ?",
        [$u['id']]);
    $totals['expired_tariffs']++;

    // Telegram'da xabardor qilish
    if ($u['telegram_id']) {
        try_notify_telegram((int)$u['telegram_id'],
            "⏰ <b>Tarif muddati tugadi</b>\n\nSalom, {$u['first_name']}!\n"
            . "Sizning tarifingiz muddati tugadi. /tarif buyrug'i bilan yangilang.");
    }
}
echo "  ✓ {$totals['expired_tariffs']} ta tarif muddati tugadi\n";

// ============================================================
// 2. 3 kun ichida tugaydigan tariflar uchun ogohlantirish
// ============================================================
echo "[2/6] Tugashga 3 kun qolganlar...\n";
$soon = db()->fetchAll(
    "SELECT id, first_name, telegram_id, tariff_expires_at FROM users
     WHERE tariff_id IS NOT NULL
     AND tariff_expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
     AND (last_warned_at IS NULL OR DATE(last_warned_at) < CURDATE())");

foreach ($soon as $u) {
    $days = max(0, ceil((strtotime($u['tariff_expires_at']) - time()) / 86400));
    if ($u['telegram_id']) {
        try_notify_telegram((int)$u['telegram_id'],
            "⚠️ <b>Tarif tugashga $days kun qoldi</b>\n\n"
            . "Sizning tarifingiz {$u['tariff_expires_at']} sanasida tugaydi.\n"
            . "Uzaytirish uchun /tarif buyrug'idan foydalaning.");
    }
    // last_warned_at ustunini qo'shamiz (yo'q bo'lsa, ALTER kerak — pastda)
    @db()->execute("UPDATE users SET last_warned_at = NOW() WHERE id = ?", [$u['id']]);
    $totals['expiring_warned']++;
}
echo "  ✓ {$totals['expiring_warned']} ta foydalanuvchi ogohlantirildi\n";

// ============================================================
// 3. Tugamagan testlarni "expired" qilish (24+ soat oldin boshlangan)
// ============================================================
echo "[3/6] Eski test urinishlarini yopish...\n";
$r = db()->execute(
    "UPDATE test_attempts SET status='expired', finished_at=NOW()
     WHERE status='in_progress' AND started_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$totals['expired_attempts'] = $r ? db()->pdo->lastInsertId() : 0;
// MySQL'da affected rows uchun:
$count = db()->fetch("SELECT ROW_COUNT() c");
$totals['expired_attempts'] = (int)($count['c'] ?? 0);
echo "  ✓ {$totals['expired_attempts']} ta test eskirdi\n";

// ============================================================
// 4. 30 kundan eski INFO loglarni o'chirish
// ============================================================
echo "[4/6] Eski loglarni tozalash...\n";
db()->execute("DELETE FROM logs WHERE level='info' AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$count = db()->fetch("SELECT ROW_COUNT() c");
$totals['old_logs_deleted'] = (int)($count['c'] ?? 0);
echo "  ✓ {$totals['old_logs_deleted']} ta log o'chirildi\n";

// ============================================================
// 5. 7 kundan eski pending to'lovlarni rejected qilish
// ============================================================
echo "[5/6] Eski kutilayotgan to'lovlar...\n";
db()->execute("UPDATE payments SET status='rejected', note='Auto: 7 kun ichida tasdiqlanmagan'
               WHERE status='pending' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
$count = db()->fetch("SELECT ROW_COUNT() c");
$totals['old_pending_payments'] = (int)($count['c'] ?? 0);
echo "  ✓ {$totals['old_pending_payments']} ta to'lov rad etildi\n";

// ============================================================
// 6. Cache tozalash (eski TTL'lar)
// ============================================================
echo "[6/6] Cache tozalash...\n";
$totals['cache_cleaned'] = Cache::flushExpired();
flush_settings_cache();
echo "  ✓ {$totals['cache_cleaned']} ta cache fayl o'chirildi\n";

// ============================================================
// Yakunlash
// ============================================================
$elapsed = round(microtime(true) - $startTime, 2);

echo "\n===== Tugadi =====\n";
echo "Vaqt: {$elapsed}s\n";
echo "Yakuniy hisobot:\n";
foreach ($totals as $key => $val) echo "  - $key: $val\n";

// Adminga Telegram orqali kunlik hisobot
$adminSummary = "📊 <b>Kunlik hisobot</b>\n"
              . date('Y-m-d') . "\n\n"
              . "✓ Tarif muddati tugadi: <b>{$totals['expired_tariffs']}</b>\n"
              . "⚠️ Ogohlantirilgan: <b>{$totals['expiring_warned']}</b>\n"
              . "⏱ Eski testlar: <b>{$totals['expired_attempts']}</b>\n"
              . "🗑 Loglar tozalandi: <b>{$totals['old_logs_deleted']}</b>\n"
              . "❌ Eski to'lovlar: <b>{$totals['old_pending_payments']}</b>\n"
              . "💾 Cache: <b>{$totals['cache_cleaned']}</b>\n"
              . "⏱ Vaqt: {$elapsed}s";

@try_notify_admin($adminSummary);

// Auditga yozish
db()->execute(
    "INSERT INTO logs (action, description, level) VALUES ('cron_daily', ?, 'info')",
    [json_encode($totals)]);

// ============================================================
// Helper: Telegram'ga xabar (direkt API call, requires_once'siz)
// ============================================================
function try_notify_telegram(int $chatId, string $text): void {
    $token = setting('telegram_bot_token', '');
    if (!$token || !$chatId) return;
    $url = "https://api.telegram.org/bot$token/sendMessage";
    @file_get_contents($url . '?' . http_build_query([
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => 'HTML',
    ]));
}

function try_notify_admin(string $text): void {
    $admin = (int)setting('telegram_admin_chat_id', 0);
    if ($admin) try_notify_telegram($admin, $text);
}
