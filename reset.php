<?php
/**
 * reset.php — STANDALONE password set
 */
require_once __DIR__ . '/includes/bootstrap.php';
auth_class();

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

$site_name = setting('site_name', SITE_NAME);
?><!DOCTYPE html>
<html lang="<?= lang() === 'uz_cyrillic' ? 'uz-Cyrl' : 'uz' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#10B981">
<title><?= e(t('reset_password')) ?> — <?= e($site_name) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(setting('site_logo', '/assets/images/logo.svg')) ?>">
<style>
<?= base_css() ?>

/* ===== RESET.PHP — fresh green theme ===== */
body{background:linear-gradient(135deg,#D1FAE5,#A7F3D0);min-height:100vh;display:flex;flex-direction:column}
.rs-header{padding:18px 22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.rs-logo{display:inline-flex;align-items:center;gap:10px;font-weight:800;font-size:15px;color:var(--text);text-decoration:none}
.rs-logo .li{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,#10B981,#059669);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:13px;box-shadow:0 6px 14px rgba(16,185,129,.3)}
.rs-lang{display:inline-flex;background:rgba(255,255,255,.7);backdrop-filter:blur(10px);border-radius:100px;padding:3px;gap:2px}
.rs-lang a{padding:5px 12px;border-radius:100px;font-size:12px;font-weight:700;color:var(--text-soft);text-decoration:none}
.rs-lang a.active{background:#fff;color:#059669}

.rs-main{flex:1;display:flex;align-items:center;justify-content:center;padding:24px}
.rs-card{
  background:rgba(255,255,255,.92);backdrop-filter:blur(20px);
  border-radius:24px;padding:36px 32px;width:100%;max-width:440px;
  box-shadow:0 24px 60px rgba(16,185,129,.18);
  animation:rsFade .5s ease both;
}
@keyframes rsFade{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.rs-icon{
  width:72px;height:72px;border-radius:20px;
  background:linear-gradient(135deg,#10B981,#059669);color:#fff;
  display:inline-flex;align-items:center;justify-content:center;
  margin:0 auto 18px;box-shadow:0 12px 28px rgba(16,185,129,.35);
}
.rs-card h2{text-align:center;font-size:24px;font-weight:800;margin-bottom:6px;letter-spacing:-.015em}
.rs-card .subtitle{text-align:center;color:var(--text-soft);font-size:13.5px;margin-bottom:24px}

.btn-green{
  display:flex;align-items:center;justify-content:center;gap:8px;width:100%;
  padding:14px 24px;border-radius:12px;border:none;cursor:pointer;
  background:linear-gradient(135deg,#10B981,#059669);color:#fff;
  font-weight:700;font-size:14px;font-family:inherit;
  box-shadow:0 8px 20px rgba(16,185,129,.3);transition:all .25s
}
.btn-green:hover{filter:brightness(1.05);box-shadow:0 12px 28px rgba(16,185,129,.4)}

.rs-back{text-align:center;margin-top:18px}
.rs-back a{color:var(--text-soft);font-size:13px;display:inline-flex;align-items:center;gap:6px}

@media (max-width:480px){
  .rs-card{padding:24px 18px;border-radius:18px}
}
</style>
</head>
<body>

<header class="rs-header">
  <a href="/" class="rs-logo"><span class="li">VP</span><span><?= e($site_name) ?></span></a>
  <div class="rs-lang">
    <a href="?setlang=uz_latin" class="<?= lang()==='uz_latin'?'active':'' ?>">Uz</a>
    <a href="?setlang=uz_cyrillic" class="<?= lang()==='uz_cyrillic'?'active':'' ?>">Кр</a>
  </div>
</header>

<main class="rs-main">
  <div class="rs-card">
    <div style="text-align:center"><div class="rs-icon"><?= icon('check-circle', 32) ?></div></div>
    <h2><?= t('reset_password') ?></h2>
    <p class="subtitle"><?= lang()==='uz_cyrillic' ? "Янги паролингизни киритинг" : "Yangi parolingizni kiriting" ?></p>

    <?php if ($error): ?><div class="alert alert-danger"><?= icon('x-circle', 16) ?> <?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= icon('check-circle', 16) ?> <?= e($success) ?></div>
      <a href="/login.php" class="btn-green" style="text-decoration:none;margin-top:8px"><?= icon('login', 16) ?> <?= t('login') ?></a>
    <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label"><?= t('password') ?> *</label>
        <input type="password" name="password" id="rsPwd" class="form-control" required minlength="8" data-strength="1" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label class="form-label"><?= t('password2') ?> *</label>
        <input type="password" name="password2" id="rsPwd2" class="form-control" required minlength="8" autocomplete="new-password">
      </div>
      <?= Security::password_strength_meter() ?>
      <button type="submit" class="btn-green" style="margin-top:14px"><?= icon('check', 16) ?> <?= lang()==='uz_cyrillic' ? "Сақлаш" : "Saqlash" ?></button>
    </form>
    <?php endif; ?>

    <div class="rs-back">
      <a href="/login.php"><?= icon('arrow-left', 12) ?> <?= t('back_to_login') ?></a>
    </div>
  </div>
</main>

<script>
(function(){
  const p1 = document.getElementById('rsPwd');
  const p2 = document.getElementById('rsPwd2');
  if (!p1 || !p2) return;
  const check = () => p2.style.borderColor = (p2.value && p1.value !== p2.value) ? 'var(--danger)' : '';
  p1.addEventListener('input', check);
  p2.addEventListener('input', check);
})();
</script>
</body></html>
