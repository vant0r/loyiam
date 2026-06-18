<?php
/**
 * logout.php — STANDALONE single-file
 * Tasdiqlash sahifasi (POST + CSRF bilan xavfsiz).
 */
require_once __DIR__ . '/includes/bootstrap.php';
auth_class();

// XAVFSIZ logout — POST + CSRF tekshiruvi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_check()) {
        Auth::logout();
        header('Location: /');
        exit;
    }
    http_response_code(419);
    die('CSRF token noto\'g\'ri');
}

if (!is_logged_in()) {
    header('Location: /');
    exit;
}

$site_name = setting('site_name', SITE_NAME);
?><!DOCTYPE html>
<html lang="<?= lang() === 'uz_cyrillic' ? 'uz-Cyrl' : 'uz' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#3B82F6">
<title><?= e(t('logout')) ?> — <?= e($site_name) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(setting('site_logo', '/assets/images/logo.svg')) ?>">
<style>
<?= base_css() ?>

/* ===== LOGOUT.PHP custom design ===== */
body{background:linear-gradient(135deg,#FEF3C7,#FFEDD5);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.logout-card{
  background:#fff;border-radius:24px;padding:40px 32px;
  max-width:420px;width:100%;text-align:center;
  box-shadow:0 24px 60px rgba(245,158,11,.18);
  animation:logoutFade .4s ease both;
}
@keyframes logoutFade{from{opacity:0;transform:translateY(20px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
.logout-icon{
  width:80px;height:80px;border-radius:24px;
  background:linear-gradient(135deg,var(--warning),#D97706);
  color:#fff;display:inline-flex;align-items:center;justify-content:center;
  margin-bottom:20px;
  box-shadow:0 12px 32px rgba(245,158,11,.4);
}
.logout-title{font-size:24px;font-weight:800;letter-spacing:-.015em;margin-bottom:8px;color:var(--text)}
.logout-sub{color:var(--text-soft);font-size:14px;margin-bottom:28px;line-height:1.6}
.logout-actions{display:flex;gap:10px;flex-wrap:wrap}
.logout-actions .btn{flex:1;min-width:140px;justify-content:center}
@media (max-width:480px){
  .logout-card{padding:28px 20px;border-radius:20px}
  .logout-icon{width:64px;height:64px;border-radius:18px}
  .logout-title{font-size:20px}
}
</style>
</head>
<body>
  <div class="logout-card">
    <div class="logout-icon"><?= icon('logout', 36) ?></div>
    <h1 class="logout-title"><?= t('logout') ?>?</h1>
    <p class="logout-sub"><?= lang()==='uz_cyrillic' ? "Тизимдан чиқишни тасдиқлайсизми? Кейинроқ қайтиб киришингиз мумкин." : "Tizimdan chiqishni tasdiqlaysizmi? Keyinroq qaytib kirishingiz mumkin." ?></p>

    <form method="post" class="logout-actions">
      <?= csrf_field() ?>
      <a href="/" class="btn btn-light"><?= icon('arrow-left', 14) ?> <?= lang()==='uz_cyrillic' ? "Бекор" : "Bekor" ?></a>
      <button type="submit" class="btn btn-danger"><?= icon('logout', 14) ?> <?= t('logout') ?></button>
    </form>
  </div>
</body>
</html>
