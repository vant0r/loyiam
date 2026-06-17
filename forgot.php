<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) { header('Location: /user/'); exit; }

$error = ''; $success = ''; $demoCode = '';
$step = 1; // 1: login kiritish, 2: kod kiritish

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
                        $demoCode = $r['code']; // Demo uchun ko'rsatamiz
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

render_head(t('forgot_password'));
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
      <div style="display:inline-flex;width:64px;height:64px;background:linear-gradient(135deg,var(--warning),#D97706);border-radius:18px;align-items:center;justify-content:center;color:#fff;margin-bottom:14px">
        <?= icon('lock', 30) ?>
      </div>
    </div>

    <!-- Steps indicator -->
    <div class="flex justify-center items-center gap-2 mb-3" style="font-size:13px">
      <div class="flex items-center gap-1" style="color:<?= $step>=1?'var(--primary)':'var(--text-mute)' ?>">
        <span style="width:24px;height:24px;border-radius:50%;background:<?= $step>=1?'var(--primary)':'var(--bg-mute)' ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px">1</span>
        <span><?= lang()==='uz_cyrillic' ? "Логин" : "Login" ?></span>
      </div>
      <span style="width:30px;height:2px;background:<?= $step>=2?'var(--primary)':'var(--bg-mute)' ?>"></span>
      <div class="flex items-center gap-1" style="color:<?= $step>=2?'var(--primary)':'var(--text-mute)' ?>">
        <span style="width:24px;height:24px;border-radius:50%;background:<?= $step>=2?'var(--primary)':'var(--bg-mute)' ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px">2</span>
        <span><?= lang()==='uz_cyrillic' ? "Код" : "Kod" ?></span>
      </div>
      <span style="width:30px;height:2px;background:var(--bg-mute)"></span>
      <div class="flex items-center gap-1" style="color:var(--text-mute)">
        <span style="width:24px;height:24px;border-radius:50%;background:var(--bg-mute);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px">3</span>
        <span><?= lang()==='uz_cyrillic' ? "Янги парол" : "Yangi parol" ?></span>
      </div>
    </div>

    <h2><?= t('forgot_password') ?></h2>
    <p class="subtitle"><?= t('forgot_d') ?></p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= icon('x-circle', 18) ?> <?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success && $step === 2): ?>
      <div class="alert alert-success"><?= icon('check-circle', 18) ?> <?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($demoCode): ?>
      <div class="alert alert-info" style="font-size:13px">
        <?= icon('help', 18) ?>
        <span><strong>Demo rejimi:</strong> Sizning kodingiz: <code style="font-size:16px;font-weight:700;color:var(--primary)"><?= e($demoCode) ?></code></span>
      </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="send_code">
      <div class="form-group">
        <label class="form-label"><?= t('email') ?> / <?= t('phone') ?></label>
        <div class="input-group">
          <span class="input-icon"><?= icon('user', 16) ?></span>
          <input type="text" name="login" class="form-control" required autofocus value="<?= e($_POST['login'] ?? '') ?>">
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg">
        <?= icon('send', 16) ?> <?= t('send_code') ?>
      </button>
    </form>
    <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="verify_code">
      <div class="form-group">
        <label class="form-label"><?= t('enter_code') ?> (6 <?= lang()==='uz_cyrillic' ? "рақамли" : "raqamli" ?>)</label>
        <input type="text" name="code" class="form-control" required autofocus
               maxlength="6" pattern="\d{6}" inputmode="numeric"
               style="text-align:center;font-size:24px;letter-spacing:8px;font-weight:700">
        <div class="form-help"><?= lang()==='uz_cyrillic' ? "10 дақиқа давомида амал қилади" : "10 daqiqa davomida amal qiladi" ?></div>
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg">
        <?= icon('check', 16) ?> <?= lang()==='uz_cyrillic' ? "Тасдиқлаш" : "Tasdiqlash" ?>
      </button>
    </form>
    <form method="post" style="margin-top:10px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="resend">
      <button class="btn btn-ghost btn-block btn-sm" data-no-loading>
        <?= icon('refresh', 14) ?> <?= lang()==='uz_cyrillic' ? "Кодни қайта юбориш" : "Kodni qayta yuborish" ?>
      </button>
    </form>
    <?php endif; ?>

    <div class="text-center mt-3">
      <a href="/login.php" class="text-soft" style="font-size:13px">
        <?= icon('arrow-left', 12) ?> <?= t('back_to_login') ?>
      </a>
    </div>
  </div>
</main>

<footer class="footer" style="padding:24px 0;margin-top:0">
  <div class="container text-center" style="font-size:13px;color:#64748B">
    © <?= date('Y') ?> <?= e(setting('site_name', SITE_NAME)) ?>. <?= t('all_rights') ?>.
  </div>
</footer>
</body></html>
