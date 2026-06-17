<?php
/**
 * Foydalanuvchi uchun chek/invoice ko'rsatish
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/payments/invoice.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
$u = current_user();

// Foydalanuvchi faqat o'z chekini ko'ra oladi (admin barchasini)
$p = db()->fetch("SELECT * FROM payments WHERE id = ?", [$id]);
if (!$p) { http_response_code(404); echo 'Topilmadi'; exit; }

if ($p['user_id'] != $u['id'] && !is_admin()) {
    http_response_code(403); echo 'Ruxsat yo\'q'; exit;
}

Invoice::render($id);
