<?php
require_once __DIR__ . '/includes/auth.php';

// XAVFSIZ logout — POST + CSRF tekshiruvi
// GET orqali logout-CSRF hujumi bo'lishi mumkin (boshqa sayt
// <img src="/logout.php"> qo'yib foydalanuvchini chiqarib yuboradi).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_check()) {
        Auth::logout();
        header('Location: /');
        exit;
    }
    http_response_code(419);
    die('CSRF token noto\'g\'ri');
}

// GET — tasdiqlash sahifasini ko'rsatamiz
if (!is_logged_in()) {
    header('Location: /');
    exit;
}

render_head(t('logout'));
?>
<header class="header">
  <div class="container nav">
    <a href="/" class="logo"><span class="logo-icon">VP</span><span><?= e(setting('site_name', SITE_NAME)) ?></span></a>
  </div>
</header>

<main class="auth-page">
  <div class="auth-box fade-up" style="max-width:420px">
    <div class="text-center mb-3">
      <div style="display:inline-flex;width:64px;height:64px;background:linear-gradient(135deg,var(--warning),#D97706);border-radius:18px;align-items:center;justify-content:center;color:#fff;margin-bottom:14px">
        <?= icon('logout', 30) ?>
      </div>
    </div>
    <h2 class="text-center"><?= t('logout') ?>?</h2>
    <p class="subtitle text-center"><?= lang()==='uz_cyrillic' ? "Тизимдан чиқишни тасдиқлайсизми?" : "Tizimdan chiqishni tasdiqlaysizmi?" ?></p>

    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px">
      <?= csrf_field() ?>
      <a href="/" class="btn btn-light" style="flex:1"><?= icon('arrow-left', 14) ?> <?= lang()==='uz_cyrillic' ? "Бекор қилиш" : "Bekor qilish" ?></a>
      <button type="submit" class="btn btn-danger" style="flex:1"><?= icon('logout', 14) ?> <?= t('logout') ?></button>
    </form>
  </div>
</main>
</body></html>
