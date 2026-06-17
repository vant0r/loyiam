<?php
/**
 * VatanParvar Yaypan — Asosiy sozlamalar
 */

// Xatoliklarni ko'rsatish (ishlab chiqishda)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vaqt zonasi
date_default_timezone_set('Asia/Tashkent');

// Sessiya
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------
// Loyiha doimiylari
// ----------------------------------
define('SITE_NAME',   'VatanParvar Yaypan');
define('SITE_URL',    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('BASE_PATH',   dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads');
define('UPLOAD_URL',  '/assets/uploads');

// ----------------------------------
// Ma'lumotlar bazasi
// ----------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'vatanparvar_yaypan');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ----------------------------------
// Asosiy ranglar (CSS uchun)
// ----------------------------------
define('PRIMARY_COLOR',   '#3B82F6');
define('PRIMARY_DARK',    '#2563EB');
define('PRIMARY_LIGHT',   '#DBEAFE');
define('SECONDARY_COLOR', '#1E40AF');

// ----------------------------------
// Telegram bot (admin tomonidan to'ldiriladi)
// ----------------------------------
define('TELEGRAM_BOT_USERNAME', 'vatanparvar_bot');

// ----------------------------------
// Yuklash papkasini yaratish
// ----------------------------------
if (!is_dir(UPLOAD_PATH)) {
    @mkdir(UPLOAD_PATH, 0755, true);
}

// Default til
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'uz_latin'; // uz_latin | uz_cyrillic
}

// Tilni o'zgartirish (?setlang=uz_cyrillic)
if (isset($_GET['setlang']) && in_array($_GET['setlang'], ['uz_latin','uz_cyrillic'])) {
    $_SESSION['lang'] = $_GET['setlang'];
    $ref = $_SERVER['HTTP_REFERER'] ?? '/';
    header('Location: ' . $ref);
    exit;
}
