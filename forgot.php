<?php
/**
 * forgot.php — STANDALONE password reset request
 */
require_once __DIR__ . '/includes/bootstrap.php';
auth_class();

if (is_logged_in()) { header('Location: /user/'); exit; }

$error = ''; $success = ''; $demoCode = '';
$step = 1;
if (!empty($_SESSION['reset_user'])) $step = 2;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = t('csrf_invalid');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'send_code') {
            $login = trim($_POST['login'] ?? '');
            if (!$login) {
                $error = t('fill_required');
            } else {
                $rl = Security::rate_limit('forgot_'.client_ip(), 5, 1800);
                if (!$rl['allowed']) {
                    $error = t('too_many_attempts');
                } else {
                    $r = Auth::create_reset_code($login);
                    if ($r['ok']) {
                        $success = $r['msg'];
                        if (defined('APP_DEBUG') && APP_DEBUG && !empty($r['debug_code'])) {
                            $demoCode = $r['debug_code'];
                        }
                        $step = 2;
                    } else {
                        $error = $r['msg'];
                    }
                }
            }
        }

        if ($action === 'verify_code') {
            $code = trim($_POST['code'] ?? '');
            if (Auth::verify_reset_code($code)) {
                $_SESSION['reset_verified'] = true;
                header('Location: /reset.php');
                exit;
            } else {
                $error = lang()==='uz_cyrillic' ? "Код нотўғри ёки муддати ўтган" : "Kod noto'g'ri yoki muddati o'tgan";
            }
        }

        if ($action === 'resend') {
            unset($_SESSION['reset_code'], $_SESSION['reset_user'], $_SESSION['reset_expire']);
            $step = 1;
        }
    }
}

$site_name = setting('site_name', SITE_NAME);
?><!DOCTYPE html>
<html lang="<?= lang() === 'uz_cyrillic' ? 'uz-Cyrl' : 'uz' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#F59E0B">
<title><?= e(t('forgot_password')) ?> — <?= e($site_name) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(setting('site_logo', '/assets/images/logo.svg')) ?>">
<style>
<?= base_css() ?>

/* ===== FORGOT.PHP — custom warm design ===== */
body{background:linear-gradient(135deg,#FEF3C7 0%,#FFEDD5 50%,#FED7AA 100%);min-height:100vh;display:flex;flex-direction:column}
.fp-header{padding:18px 22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.fp-logo{display:inline-flex;align-items:center;gap:10px;font-weight:800;font-size:15px;color:var(--text);text-decoration:none}
.fp-logo .li{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,#F59E0B,#D97706);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:13px;box-shadow:0 6px 14px rgba(245,158,11,.3)}
.fp-lang{display:inline-flex;background:rgba(255,255,255,.7);backdrop-filter:blur(10px);border-radius:100px;padding:3px;gap:2px}
.fp-lang a{padding:5px 12px;border-radius:100px;font-size:12px;font-weight:700;color:var(--text-soft);text-decoration:none}
.fp-lang a.active{background:#fff;color:#D97706}

.fp-main{flex:1;display:flex;align-items:center;justify-content:center;padding:24px}
.fp-card{
  background:rgba(255,255,255,.9);backdrop-filter:blur(20px);
  border-radius:24px;padding:36px 32px;width:100%;max-width:440px;
  box-shadow:0 24px 60px rgba(245,158,11,.18);
  animation:fpFade .5s ease both;
}
@keyframes fpFade{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.fp-icon{
  width:72px;height:72px;border-radius:20px;
  background:linear-gradient(135deg,#F59E0B,#D97706);color:#fff;
  display:inline-flex;align-items:center;justify-content:center;
  margin:0 auto 18px;box-shadow:0 12px 28px rgba(245,158,11,.35);
}
.fp-card h2{text-align:center;font-size:24px;font-weight:800;margin-bottom:6px;letter-spacing:-.015em}
.fp-card .subtitle{text-align:center;color:var(--text-soft);font-size:13.5px;margin-bottom:24px}

.fp-steps{display:flex;align-items:center;justify-content:center;gap:6px;font-size:12px;margin-bottom:24px}
.fp-step{display:flex;align-items:center;gap:6px;color:var(--text-mute)}
.fp-step.is-active{color:#D97706;font-weight:600}
.fp-step .num{width:22px;height:22px;border-radius:50%;background:var(--bg-mute);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px}
.fp-step.is-active .num{background:#F59E0B}
.fp-step.is-done .num{background:#10B981}
.fp-step .line{width:24px;height:2px;background:var(--bg-mute)}
.fp-step.is-done .line, .fp-step.is-active .line{background:#F59E0B}

.fp-input{position:relative}
.fp-input .icn{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-mute);pointer-events:none}
.fp-input .form-control{padding-left:42px}
.code-input{text-align:center;font-size:24px;letter-spacing:8px;font-weight:700}

.btn-warm{
  display:flex;align-items:center;justify-content:center;gap:8px;width:100%;
  padding:14px 24px;border-radius:12px;border:none;cursor:pointer;
  background:linear-gradient(135deg,#F59E0B,#D97706);color:#fff;
  font-weight:700;font-size:14px;font-family:inherit;
  box-shadow:0 8px 20px rgba(245,158,11,.3);transition:all .25s
}
.btn-warm:hover{filter:brightness(1.05);box-shadow:0 12px 28px rgba(245,158,11,.4)}

.fp-back{text-align:center;margin-top:18px}
.fp-back a{color:var(--text-soft);font-size:13px;display:inline-flex;align-items:center;gap:6px}

@media (max-width:480px){
  .fp-card{padding:24px 18px;border-radius:18px}
  .fp-icon{width:56px;height:56px;border-radius:16px}
  .fp-card h2{font-size:20px}
  .code-input{font-size:20px;letter-spacing:6px}
}
</style>
</head>
<body>

<header class="fp-header">
  <a href="/" class="fp-logo">
    <span class="li">VP</span>
    <span><?= e($site_name) ?></span>
  </a>
  <div class="fp-lang">
    <a href="?setlang=uz_latin" class="<?= lang()==='uz_latin'?'active':'' ?>">Uz</a>
    <a href="?setlang=uz_cyrillic" class="<?= lang()==='uz_cyrillic'?'active':'' ?>">Кр</a>
  </div>
</header>

<main class="fp-main">
  <div class="fp-card">
    <div style="text-align:center"><div class="fp-icon"><?= icon('lock', 32) ?></div></div>

    <div class="fp-steps">
      <div class="fp-step <?= $step>=1?($step>1?'is-done':'is-active'):'' ?>">
        <span class="num"><?= $step>1 ? '✓' : '1' ?></span>
        <span><?= lang()==='uz_cyrillic' ? "Логин" : "Login" ?></span>
      </div>
      <span class="line"></span>
      <div class="fp-step <?= $step>=2?'is-active':'' ?>">
        <span class="num">2</span>
        <span><?= lang()==='uz_cyrillic' ? "Код" : "Kod" ?></span>
      </div>
      <span class="line"></span>
      <div class="fp-step">
        <span class="num">3</span>
        <span><?= lang()==='uz_cyrillic' ? "Янги парол" : "Yangi parol" ?></span>
      </div>
    </div>

    <h2><?= t('forgot_password') ?></h2>
    <p class="subtitle"><?= t('forgot_d') ?></p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= icon('x-circle', 16) ?> <?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success && $step === 2): ?>
      <div class="alert alert-success"><?= icon('check-circle', 16) ?> <?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($demoCode): ?>
      <div class="alert alert-warning" style="font-size:12px">
        <?= icon('flame', 14) ?>
        <span><strong>Debug:</strong> <code style="font-size:14px;font-weight:700;color:#D97706"><?= e($demoCode) ?></code></span>
      </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="send_code">
      <div class="form-group">
        <label class="form-label"><?= t('email') ?> / <?= t('phone') ?></label>
        <div class="fp-input">
          <span class="icn"><?= icon('user', 16) ?></span>
          <input type="text" name="login" class="form-control" required autofocus value="<?= e($_POST['login'] ?? '') ?>">
        </div>
      </div>
      <button type="submit" class="btn-warm"><?= icon('send', 16) ?> <?= t('send_code') ?></button>
    </form>
    <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="verify_code">
      <div class="form-group">
        <label class="form-label"><?= t('enter_code') ?> (6 <?= lang()==='uz_cyrillic' ? "рақамли" : "raqamli" ?>)</label>
        <input type="text" name="code" class="form-control code-input" required autofocus
               maxlength="6" pattern="\d{6}" inputmode="numeric">
        <small class="text-mute" style="font-size:12px;display:block;margin-top:6px"><?= lang()==='uz_cyrillic' ? "10 дақиқа давомида амал қилади" : "10 daqiqa davomida amal qiladi" ?></small>
      </div>
      <button type="submit" class="btn-warm"><?= icon('check', 16) ?> <?= lang()==='uz_cyrillic' ? "Тасдиқлаш" : "Tasdiqlash" ?></button>
    </form>
    <form method="post" style="margin-top:10px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="resend">
      <button class="btn btn-ghost btn-block btn-sm"><?= icon('refresh', 14) ?> <?= lang()==='uz_cyrillic' ? "Қайта юбориш" : "Qayta yuborish" ?></button>
    </form>
    <?php endif; ?>

    <div class="fp-back">
      <a href="/login.php"><?= icon('arrow-left', 12) ?> <?= t('back_to_login') ?></a>
    </div>
  </div>
</main>

</body></html>
