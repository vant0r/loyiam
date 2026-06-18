<?php
/**
 * Telegram Bot — webhook handler
 *
 * Webhook URL: https://your-domain.uz/telegram/bot.php
 *
 * Komandalar:
 *   /start  — Boshlash, ro'yxatdan o'tish
 *   /tarif  — Tariflar ro'yxati
 *   /aloqa  — Aloqa ma'lumotlari
 *   /test   — Test (mini-app)
 *   /yordam — Yordam
 *   /chiqish — Akkauntdan chiqish
 *
 * To'lov flow:
 *   user → /tarif → tarif tanlash → karta ma'lumotlar → screenshot yuborish → admin tasdiqlashi
 */
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

// ============================================================
// WEBHOOK SECURITY: BotFather'da setWebhook qilganda secret_token bering.
// Telegram har bir update bilan birga shu header'ni yuboradi:
//   X-Telegram-Bot-Api-Secret-Token: <your-secret>
// Tasdiqlanmagan so'rovlar 401 bilan rad etiladi.
// ============================================================
$expectedSecret = (string)setting('telegram_webhook_secret', $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '');
$incomingSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

if (empty($expectedSecret)) {
    // Production'da secret to'ldirilmagan bo'lsa, webhookni umuman qabul qilmaymiz
    if (defined('APP_DEBUG') && APP_DEBUG) {
        // Debug rejimida ogohlantirish bilan davom etamiz
        @error_log("[telegram/bot.php] WARNING: telegram_webhook_secret bo'sh — webhook himoyasiz!");
    } else {
        http_response_code(403);
        @error_log("[telegram/bot.php] BLOCKED: webhook secret bo'sh — production'da xavfsizlik uchun rad etildi");
        exit;
    }
} else {
    if (empty($incomingSecret) || !hash_equals($expectedSecret, $incomingSecret)) {
        http_response_code(401);
        Security::audit('telegram_webhook_unauthorized', 'IP: ' . Security::client_ip(), 'warning');
        exit;
    }
}

// JSON request olish
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!is_array($update)) { http_response_code(400); exit; }

// Optional logging (debug uchun)
@file_put_contents(__DIR__.'/../cache/tg_last.json', $input);

http_response_code(200);

// Handle different update types
if (isset($update['message'])) {
    handle_message($update['message']);
} elseif (isset($update['callback_query'])) {
    handle_callback($update['callback_query']);
}

// ============================================================
// MESSAGE HANDLER
// ============================================================
function handle_message(array $msg): void {
    $chatId = $msg['chat']['id'] ?? 0;
    $text   = $msg['text'] ?? '';
    $userId = $msg['from']['id'] ?? 0;

    if (!$chatId) return;

    // Telefon raqami yuborilganmi? (Contact)
    if (isset($msg['contact'])) {
        handle_contact($chatId, $userId, $msg['contact']);
        return;
    }

    // Photo yuborilganmi? (chek skrinshoti)
    if (isset($msg['photo'])) {
        handle_photo($chatId, $userId, $msg['photo'], $msg['caption'] ?? '');
        return;
    }

    // Komandalar
    if ($text === '/start') { cmd_start($chatId, $userId, $msg['from']); return; }
    if ($text === '/tarif' || $text === '/tariflar' || $text === '💎 Tariflar') { cmd_tariflar($chatId, $userId); return; }
    if ($text === '/aloqa' || $text === '📞 Aloqa') { cmd_aloqa($chatId); return; }
    if ($text === '/test'  || $text === '📝 Test')  { cmd_test($chatId, $userId); return; }
    if ($text === '/yordam' || $text === '/help' || $text === 'ℹ️ Yordam') { cmd_yordam($chatId); return; }
    if ($text === '/chiqish') { cmd_chiqish($chatId, $userId); return; }

    // Default — yordam ko'rsatish
    cmd_yordam($chatId);
}

// ============================================================
// CALLBACK HANDLER (inline tugmalar)
// ============================================================
function handle_callback(array $cb): void {
    $chatId = $cb['message']['chat']['id'] ?? 0;
    $msgId  = $cb['message']['message_id'] ?? 0;
    $userId = $cb['from']['id'] ?? 0;
    $data   = $cb['data'] ?? '';

    [$action, $param] = array_pad(explode(':', $data, 2), 2, null);

    TelegramAPI::answerCallback($cb['id']);

    if ($action === 'tarif')      { cb_tarif($chatId, $userId, (int)$param); return; }
    if ($action === 'approve')    { cb_admin_approve($chatId, $msgId, (int)$param); return; }
    if ($action === 'reject')     { cb_admin_reject($chatId, $msgId, (int)$param); return; }
    if ($action === 'noop')       return;
}

// ============================================================
// COMMAND: /start
// ============================================================
function cmd_start(int $chatId, int $userId, array $from): void {
    $user = db()->fetch("SELECT * FROM users WHERE telegram_id = ?", [$userId]);

    if ($user) {
        // Allaqachon bog'langan
        $name = $from['first_name'] ?? 'Foydalanuvchi';
        $kbd = main_menu_keyboard();
        TelegramAPI::sendMessage($chatId,
            "👋 <b>Xush kelibsiz, $name!</b>\n\n"
            . "Sizning akkauntingiz: <b>".htmlspecialchars($user['first_name'].' '.$user['last_name'])."</b>\n\n"
            . "Quyidagi tugmalardan foydalaning 👇",
            ['reply_markup' => json_encode($kbd)]);
        return;
    }

    // Yangi foydalanuvchi — telefon so'rash
    $kbd = [
        'keyboard' => [[['text' => '📱 Telefon raqamini ulashish', 'request_contact' => true]]],
        'resize_keyboard'   => true,
        'one_time_keyboard' => true,
    ];
    TelegramAPI::sendMessage($chatId,
        "🚗 <b>VatanParvar Yaypan</b>\n\n"
        . "Avtomaktab imtihoniga onlayn tayyorlanish platformasiga xush kelibsiz!\n\n"
        . "Davom etish uchun <b>telefon raqamingizni</b> ulashing 👇",
        ['reply_markup' => json_encode($kbd)]);
}

function handle_contact(int $chatId, int $userId, array $contact): void {
    $phone = preg_replace('/\D/', '', $contact['phone_number'] ?? '');
    if (strlen($phone) < 9) {
        TelegramAPI::sendMessage($chatId, "❌ Noto'g'ri telefon raqami");
        return;
    }
    if (strlen($phone) === 9) $phone = '998' . $phone;

    // Mavjud user bormi?
    $user = db()->fetch("SELECT * FROM users WHERE phone = ? OR phone = ?",
        [$phone, '+'.$phone]);

    if (!$user) {
        // Yangi user yarat
        $first = $contact['first_name'] ?? 'TG';
        $last  = $contact['last_name'] ?? 'User';
        $code  = strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));
        db()->execute(
            "INSERT INTO users (first_name, last_name, phone, password, role, status, referral_code, telegram_id, telegram_phone)
             VALUES (?,?,?,?,'user','active',?,?,?)",
            [$first, $last, $phone, password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), $code, $userId, $phone]);
        $user = db()->fetch("SELECT * FROM users WHERE phone = ?", [$phone]);
    } else {
        // Telegram ID ni biriktirish
        db()->execute("UPDATE users SET telegram_id = ?, telegram_phone = ? WHERE id = ?",
            [$userId, $phone, $user['id']]);
    }

    $kbd = main_menu_keyboard();
    TelegramAPI::sendMessage($chatId,
        "✅ <b>Tasdiqlandi!</b>\n\n"
        . "Akkauntingiz: <b>".htmlspecialchars($user['first_name'].' '.$user['last_name'])."</b>\n"
        . "Telefon: <code>+$phone</code>\n\n"
        . "Endi siz quyidagilarni qila olasiz:",
        ['reply_markup' => json_encode($kbd)]);
}

// ============================================================
// COMMAND: /tarif
// ============================================================
function cmd_tariflar(int $chatId, int $userId): void {
    $user = db()->fetch("SELECT * FROM users WHERE telegram_id = ?", [$userId]);
    if (!$user) {
        TelegramAPI::sendMessage($chatId, "❌ Avval /start buyrug'i bilan ro'yxatdan o'ting");
        return;
    }

    $tariffs = db()->fetchAll("SELECT * FROM tariffs WHERE status='active' ORDER BY sort_order");

    $text = "💎 <b>Tariflarimiz</b>\n\n";
    $buttons = [];
    foreach ($tariffs as $t) {
        $price = $t['price'] == 0 ? 'Bepul' : number_format($t['price'], 0, '.', ' ').' so\'m';
        $popular = $t['is_popular'] ? ' ⭐' : '';
        $text .= "<b>{$t['name_latin']}$popular</b>\n";
        $text .= "💰 <b>$price</b> · {$t['duration_days']} kun\n";
        $text .= htmlspecialchars($t['description_latin'])."\n\n";

        if ($t['price'] > 0) {
            $buttons[] = [['text' => "{$t['name_latin']} — $price", 'callback_data' => 'tarif:'.$t['id']]];
        }
    }
    $buttons[] = [['text' => '🌐 Saytda ko\'rish', 'url' => SITE_URL.'/tariflar.php']];

    TelegramAPI::sendMessage($chatId, $text, [
        'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
    ]);
}

function cb_tarif(int $chatId, int $userId, int $tariffId): void {
    $user = db()->fetch("SELECT * FROM users WHERE telegram_id = ?", [$userId]);
    $tariff = db()->fetch("SELECT * FROM tariffs WHERE id = ? AND status='active'", [$tariffId]);
    if (!$user || !$tariff) {
        TelegramAPI::sendMessage($chatId, "❌ Topilmadi");
        return;
    }

    // Pending payment yaratamiz
    db()->execute(
        "INSERT INTO payments (user_id, tariff_id, amount, method, status) VALUES (?, ?, ?, 'telegram', 'pending')",
        [$user['id'], $tariff['id'], $tariff['price']]);
    $payId = (int)db()->lastInsertId();

    $card = setting('card_number', '8600 1234 5678 9012');
    $holder = setting('card_holder', 'VATANPARVAR YAYPAN');

    $text = "💳 <b>To'lov ma'lumotlari</b>\n\n"
          . "Tarif: <b>{$tariff['name_latin']}</b>\n"
          . "Summa: <b>".number_format($tariff['price'], 0, '.', ' ')." so'm</b>\n\n"
          . "📌 <b>Karta raqami:</b>\n<code>$card</code>\n\n"
          . "👤 <b>Karta egasi:</b>\n$holder\n\n"
          . "─────────────\n"
          . "1️⃣ Yuqoridagi kartaga ko'rsatilgan summani o'tkazing\n"
          . "2️⃣ Chek skrinshotini shu chatga yuboring\n"
          . "3️⃣ Admin tasdiqlashini kuting (1-24 soat)\n\n"
          . "💡 To'lov ID: <code>#$payId</code>";

    $kbd = ['inline_keyboard' => [
        [['text' => '🌐 Click/Payme orqali to\'lash', 'url' => SITE_URL.'/user/tariflar.php']],
        [['text' => '« Tariflar', 'callback_data' => 'noop']],
    ]];
    TelegramAPI::sendMessage($chatId, $text, ['reply_markup' => json_encode($kbd)]);
}

// Photo (chek skrinshot) yuborilgani
function handle_photo(int $chatId, int $userId, array $photos, string $caption): void {
    $user = db()->fetch("SELECT * FROM users WHERE telegram_id = ?", [$userId]);
    if (!$user) {
        TelegramAPI::sendMessage($chatId, "❌ Avval /start orqali ro'yxatdan o'ting");
        return;
    }

    // Pending to'lovni topish (oxirgi)
    $payment = db()->fetch(
        "SELECT * FROM payments WHERE user_id = ? AND status = 'pending' AND method = 'telegram'
         ORDER BY created_at DESC LIMIT 1", [$user['id']]);

    if (!$payment) {
        TelegramAPI::sendMessage($chatId,
            "❌ Faol to'lov so'rovi topilmadi.\n\nIltimos, avval /tarif orqali tarif tanlang.");
        return;
    }

    // Eng katta o'lchamdagi rasmni olish
    $bestPhoto = end($photos);
    $fileId = $bestPhoto['file_id'] ?? '';
    if (!$fileId) return;

    $name = 'tg_pay_'.$user['id'].'_'.time().'.jpg';
    $dest = UPLOAD_PATH . '/' . $name;
    @mkdir(UPLOAD_PATH, 0755, true);

    if (TelegramAPI::downloadFile($fileId, $dest)) {
        db()->execute("UPDATE payments SET screenshot = ? WHERE id = ?",
            [UPLOAD_URL.'/'.$name, $payment['id']]);
    }

    TelegramAPI::sendMessage($chatId,
        "✅ <b>Chek qabul qilindi!</b>\n\n"
        . "To'lov ID: <code>#{$payment['id']}</code>\n"
        . "Admin tasdiqlashini kuting. Tasdiqlangach, sizga xabar keladi.");

    // Adminga yuborish
    $tariff = db()->fetch("SELECT * FROM tariffs WHERE id = ?", [$payment['tariff_id']]);
    $adminText = "💰 <b>Yangi to'lov</b>\n\n"
               . "👤 ".htmlspecialchars($user['first_name'].' '.$user['last_name'])."\n"
               . "📞 +".$user['phone']."\n"
               . "💎 ".($tariff['name_latin'] ?? '—')."\n"
               . "💵 ".number_format($payment['amount'], 0, '.', ' ')." so'm\n"
               . "🆔 <code>#{$payment['id']}</code>";

    $adminId = (int)setting('telegram_admin_chat_id', 0);
    if ($adminId && $fileId) {
        TelegramAPI::sendPhoto($adminId, $fileId, $adminText, [
            'reply_markup' => json_encode(['inline_keyboard' => [[
                ['text' => '✅ Tasdiqlash', 'callback_data' => 'approve:'.$payment['id']],
                ['text' => '❌ Rad etish',  'callback_data' => 'reject:'.$payment['id']],
            ]]]),
        ]);
    }
}

// ============================================================
// ADMIN actions
// ============================================================
function cb_admin_approve(int $chatId, int $msgId, int $payId): void {
    $admin = (int)setting('telegram_admin_chat_id', 0);
    if ($chatId !== $admin) return;

    $p = db()->fetch("SELECT * FROM payments WHERE id = ?", [$payId]);
    if (!$p) return;

    db()->execute("UPDATE payments SET status='approved' WHERE id=?", [$payId]);

    if ($p['tariff_id']) {
        $tariff = db()->fetch("SELECT * FROM tariffs WHERE id=?", [$p['tariff_id']]);
        if ($tariff) {
            $expires = date('Y-m-d H:i:s', strtotime("+{$tariff['duration_days']} days"));
            db()->execute("UPDATE users SET tariff_id=?, tariff_expires_at=? WHERE id=?",
                [$p['tariff_id'], $expires, $p['user_id']]);

            // Notification yuboramiz (DB + Telegram)
            require_once __DIR__ . '/../includes/notifications.php';
            Notify::send((int)$p['user_id'], 'payment_approved',
                "✅ To'lov tasdiqlandi!",
                $tariff['name_latin'] . " tarifingiz faollashtirildi ({$tariff['duration_days']} kun)",
                ['link' => '/user/tariflar.php', 'icon' => 'check-circle', 'telegram' => true]);
        }
    }

    TelegramAPI::editMessage($chatId, $msgId,
        "✅ <b>TASDIQLANDI</b>\n\nTo'lov #{$payId} qabul qilindi va foydalanuvchi xabardor qilindi.");
}

function cb_admin_reject(int $chatId, int $msgId, int $payId): void {
    $admin = (int)setting('telegram_admin_chat_id', 0);
    if ($chatId !== $admin) return;

    $p = db()->fetch("SELECT * FROM payments WHERE id = ?", [$payId]);
    if (!$p) return;

    db()->execute("UPDATE payments SET status='rejected' WHERE id=?", [$payId]);

    require_once __DIR__ . '/../includes/notifications.php';
    Notify::send((int)$p['user_id'], 'payment_rejected',
        "❌ To'lov rad etildi",
        "Iltimos, admin bilan bog'laning yoki qayta urinib ko'ring",
        ['link' => '/user/tariflar.php', 'icon' => 'x-circle', 'telegram' => true]);

    TelegramAPI::editMessage($chatId, $msgId,
        "❌ <b>RAD ETILDI</b>\n\nTo'lov #{$payId} rad etildi.");
}

// ============================================================
// COMMAND: /aloqa
// ============================================================
function cmd_aloqa(int $chatId): void {
    $text = "📞 <b>Aloqa</b>\n\n"
          . "📱 Telefon: <code>".setting('site_phone')."</code>\n"
          . "✉️ Email: ".setting('site_email')."\n"
          . "📍 Manzil: ".setting('site_address')."\n"
          . "🕐 Ish vaqti: ".setting('working_hours')."\n\n"
          . "🌐 Sayt: ".SITE_URL;

    $kbd = ['inline_keyboard' => [
        [['text' => '🌐 Sayt', 'url' => SITE_URL]],
        [['text' => '✉️ Telegram kanal', 'url' => setting('telegram_url', SITE_URL)]],
    ]];
    TelegramAPI::sendMessage($chatId, $text, ['reply_markup' => json_encode($kbd)]);
}

// ============================================================
// COMMAND: /test
// ============================================================
function cmd_test(int $chatId, int $userId): void {
    $user = db()->fetch("SELECT * FROM users WHERE telegram_id = ?", [$userId]);
    if (!$user) {
        TelegramAPI::sendMessage($chatId, "❌ Avval /start orqali ro'yxatdan o'ting");
        return;
    }

    // Foydalanuvchi tarifi
    $hasActiveTariff = $user['tariff_id'] && $user['tariff_expires_at'] && strtotime($user['tariff_expires_at']) > time();

    if (!$hasActiveTariff) {
        TelegramAPI::sendMessage($chatId,
            "📝 <b>Test boshlash</b>\n\nTestlar uchun aktiv tarif kerak.\n\n/tarif buyrug'i bilan tarif tanlang.",
            ['reply_markup' => json_encode([
                'inline_keyboard' => [[
                    ['text' => '💎 Tariflar', 'callback_data' => 'noop'],
                    ['text' => '🌐 Saytda', 'url' => SITE_URL.'/tariflar.php'],
                ]],
            ])]);
        return;
    }

    TelegramAPI::sendMessage($chatId,
        "📝 <b>Test boshlash</b>\n\nTestlarni saytdagi shaxsiy kabinetingizdan boshlashingiz mumkin:",
        ['reply_markup' => json_encode([
            'inline_keyboard' => [[
                ['text' => '🚀 Testni boshlash', 'url' => SITE_URL.'/user/testlar.php'],
            ]],
        ])]);
}

// ============================================================
// COMMAND: /yordam
// ============================================================
function cmd_yordam(int $chatId): void {
    $text = "ℹ️ <b>Yordam</b>\n\n"
          . "Mavjud buyruqlar:\n"
          . "/start — Boshlash va ro'yxatdan o'tish\n"
          . "/tarif — Tariflar ro'yxati\n"
          . "/test — Testni boshlash\n"
          . "/aloqa — Aloqa ma'lumotlari\n"
          . "/yordam — Ushbu xabar\n"
          . "/chiqish — Telegramdan akkauntni ajratish\n\n"
          . "<b>To'lov bo'yicha:</b>\n"
          . "1. /tarif buyrug'i\n"
          . "2. Tarifni tanlang\n"
          . "3. Karta orqali to'lang\n"
          . "4. Chek skrinshotini yuboring";
    TelegramAPI::sendMessage($chatId, $text);
}

function cmd_chiqish(int $chatId, int $userId): void {
    db()->execute("UPDATE users SET telegram_id = NULL WHERE telegram_id = ?", [$userId]);
    TelegramAPI::sendMessage($chatId,
        "👋 Telegramdan chiqdingiz.\n\nQayta kirish uchun /start buyrug'idan foydalaning.",
        ['reply_markup' => json_encode(['remove_keyboard' => true])]);
}

// ============================================================
// Main menu keyboard (reply)
// ============================================================
function main_menu_keyboard(): array {
    return [
        'keyboard' => [
            [['text' => '💎 Tariflar'], ['text' => '📝 Test']],
            [['text' => '📞 Aloqa'],    ['text' => 'ℹ️ Yordam']],
        ],
        'resize_keyboard' => true,
    ];
}
