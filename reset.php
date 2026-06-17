<?php
require_once __DIR__ . '/includes/auth.php';

// Verifikatsiyasiz reset.php ga kirilmaydi
if (empty($_SESSION['reset_verified']) || empty($_SESSION['reset_user'])) {
    header('Location: /forgot.php');
    exit;
}

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = t('csrf_invalid');
    } else {
        $newPass  = $_POST['password'] ?? '';
        $newPass2 = $_POST['password2'] ?? '';

        $check = Security::validate_password($newPass);
        if (!$check['ok']) {
            $error = $check['errors'][0] ?? t('password_min');
        } elseif ($newPass !== $newPass2) {
            $error = t('passwords_dont_match');
        } else {
            $userId = (int)$_SESSION['reset_user'];
            db()->execute("UPDATE users SET password = ? WHERE id = ?",
                [password_hash($newPass, PASSWORD_DEFAULT), $userId]);

            unset($_SESSION['reset_code'], $_SESSION['reset_user'],
                  $_SESSION['reset_expire'], $_SESSION['reset_verified']);

            audit('password_reset', '', 'info');
            $success = lang()==='uz_cyrillic'
                ? "Парол муваффақиятли янгиланди! Энди киришингиз мумкин."
                : "Parol muvaffaqiyatli yangilandi! Endi kirishingiz mumkin.";
        }
    }
}

render_head(t('reset_password'));
?>
<header class="header">
  <div class="container nav">
    <a href="/" class="logo"><span class="logo-icon">VP</span><span><?= e(setting('site_name', SITE_NAME)) ?></span></a>
    <div class="nav-actions">
      <div class="lang-switch">
        <a href="?setlang=uz_latin" class="<?= lang()==='uz_latin'?'active':'' ?>">Uz</a>
        <a href="?setlang=uz_cyrillic" class="<?= lang()==='uz_cyrillic'?'active':'' ?>">Кр</a>
      </div>
    </div>
  </div>
</header>

<main class="auth-page">
  <div class="auth-box fade-up">
    <div class="text-center mb-3">
      <div style="display:inline-flex;width:64px;height:64px;background:linear-gradient(135deg,var(--success),#059669);border-radius:18px;align-items:center;justify-content:center;color:#fff;margin-bottom:14px">
        <?= icon('check-circle', 30) ?>
      </div>
    </div>

    <h2><?= t('reset_password') ?></h2>
    <p class="subtitle"><?= lang()==='uz_cyrillic' ? "Янги парол киритинг" : "Yangi parol kiriting" ?></p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= icon('x-circle', 18) ?> <?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= icon('check-circle', 18) ?> <?= e($success) ?></div>
      <a href="/login.php" class="btn btn-primary btn-block btn-lg">
        <?= icon('login', 16) ?> <?= t('login') ?>
      </a>
    <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label"><?= t('new_password') ?> <span class="required">*</span></label>
        <div class="input-group">
          <span class="input-icon"><?= icon('lock', 16) ?></span>
          <input type="password" name="password" id="rs_pwd" class="form-control" required minlength="8" data-strength="1" autofocus>
          <button type="button" class="input-action" data-toggle-password="rs_pwd"><?= icon('eye', 16) ?></button>
        </div>
        <?= Security::password_strength_meter() ?>
      </div>
      <div class="form-group">
        <label class="form-label"><?= t('password2') ?> <span class="required">*</span></label>
        <div class="input-group">
          <span class="input-icon"><?= icon('lock', 16) ?></span>
          <input type="password" name="password2" id="rs_pwd2" class="form-control" required minlength="8">
          <button type="button" class="input-action" data-toggle-password="rs_pwd2"><?= icon('eye', 16) ?></button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg">
        <?= icon('check', 16) ?> <?= t('reset_password') ?>
      </button>
    </form>
    <?php endif; ?>
  </div>
</main>

<footer class="footer" style="padding:24px 0;margin-top:0">
  <div class="container text-center" style="font-size:13px;color:#64748B">
    © <?= date('Y') ?> <?= e(setting('site_name', SITE_NAME)) ?>. <?= t('all_rights') ?>.
  </div>
</footer>
</body></html>
