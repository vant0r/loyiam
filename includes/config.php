<?php
/**
 * VatanParvar Yaypan — Asosiy sozlamalar
 *
 * Production'da xato chiqarmaslik uchun APP_DEBUG=0 bo'lishi shart.
 * Lokal serverda APP_DEBUG=1 ga qo'ying yoki .env / config.local.php yarating.
 */

// ============================================================
// ENVIRONMENT-BASED DEBUG (xavfsiz)
// ============================================================
// .env fayli mavjud bo'lsa, undan o'qiymiz; yo'q bo'lsa default — production
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if ($k && !isset($_ENV[$k])) $_ENV[$k] = trim($v, "\"'");
    }
}

$APP_DEBUG = (string)($_ENV['APP_DEBUG'] ?? '0') === '1';
$APP_ENV   = $_ENV['APP_ENV'] ?? 'production';

if ($APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    @ini_set('error_log', dirname(__DIR__) . '/cache/php-error.log');
}

define('APP_DEBUG', $APP_DEBUG);
define('APP_ENV', $APP_ENV);

// Vaqt zonasi
date_default_timezone_set('Asia/Tashkent');

// ============================================================
// HTTPS / Secure cookie detection
// ============================================================
$IS_HTTPS = (
    (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
);
define('IS_HTTPS', $IS_HTTPS);

// ============================================================
// SESSION (xavfsiz konfiguratsiya)
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax');
    @ini_set('session.cookie_secure', $IS_HTTPS ? '1' : '0');
    @ini_set('session.gc_maxlifetime', '7200');
    @ini_set('session.use_trans_sid', '0');
    @ini_set('session.sid_length', '48');
    @ini_set('session.sid_bits_per_character', '6');
    @session_set_cookie_params([
        'lifetime' => 7200,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $IS_HTTPS,
    ]);
    @session_name('vp_sess');
    session_start();

    // Idle timeout — 2 soat hech narsa qilmasa, foydalanuvchi ma'lumotlarini
    // tozalaymiz (sessiyani destroy qilmasdan, faqat content'ni unset).
    // Bu shu request'dayoq xavfsiz: keyingi sahifada foydalanuvchi anonymous bo'ladi.
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 7200) {
        // Faqat sezgir ma'lumotni tozalaymiz, sessiyaning o'zini emas
        unset($_SESSION['user_id'], $_SESSION['user_role'],
              $_SESSION['fingerprint'], $_SESSION['login_time'],
              $_SESSION['reset_code'], $_SESSION['reset_user'],
              $_SESSION['reset_expire']);
    }
    $_SESSION['last_activity'] = time();
}

// ============================================================
// Loyiha doimiylari
// ============================================================
define('SITE_NAME',   'VatanParvar Yaypan');
define('SITE_URL',    ($IS_HTTPS ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('BASE_PATH',   dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads');
define('UPLOAD_URL',  '/assets/uploads');

// ============================================================
// .installed lockfile tekshiruvi
// ============================================================
if (!is_file(BASE_PATH . '/.installed')
    && !defined('INSTALLER_RUNNING')
    && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
    if (php_sapi_name() !== 'cli' && !headers_sent()) {
        header('Location: /install.php');
        exit;
    }
}

// ============================================================
// Ma'lumotlar bazasi
// ============================================================
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'vatanparvar_yaypan');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// Asosiy ranglar (CSS uchun)
// ============================================================
define('PRIMARY_COLOR',   '#3B82F6');
define('PRIMARY_DARK',    '#2563EB');
define('PRIMARY_LIGHT',   '#DBEAFE');
define('SECONDARY_COLOR', '#1E40AF');

// ============================================================
// Telegram bot
// ============================================================
define('TELEGRAM_BOT_USERNAME', $_ENV['TELEGRAM_BOT_USERNAME'] ?? 'vatanparvar_bot');

// ============================================================
// Yuklash papkasini yaratish
// ============================================================
if (!is_dir(UPLOAD_PATH)) {
    @mkdir(UPLOAD_PATH, 0755, true);
}

// Default til
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'uz_latin';
}

// ============================================================
// Tilni o'zgartirish (SAME-HOST referer faqat — open redirect oldini olish)
// ============================================================
if (isset($_GET['setlang']) && in_array($_GET['setlang'], ['uz_latin','uz_cyrillic'], true)) {
    $_SESSION['lang'] = $_GET['setlang'];

    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $safe = '/';
    if ($ref) {
        $parsed = parse_url($ref);
        $myHost = $_SERVER['HTTP_HOST'] ?? '';
        if (!empty($parsed['host']) && strcasecmp($parsed['host'], $myHost) === 0) {
            // Faqat path + query qaytaramiz, scheme/host'ni tashlab
            $safe = ($parsed['path'] ?? '/');
            if (!empty($parsed['query'])) {
                // setlang param'ini tashlab yuboramiz
                parse_str($parsed['query'], $q);
                unset($q['setlang']);
                if (!empty($q)) $safe .= '?' . http_build_query($q);
            }
        }
    }
    if (!str_starts_with($safe, '/')) $safe = '/';
    header('Location: ' . $safe);
    exit;
}

// Auto-migration
if (is_file(BASE_PATH . '/.installed') && !is_file(BASE_PATH . '/.migrated_v3.0')) {
    @require_once __DIR__ . '/migrate.php';
    if (function_exists('maybe_auto_migrate')) maybe_auto_migrate();
}
