<?php
/**
 * login.php — STANDALONE single-file
 * Faqat backend includes (bootstrap.php) — render funksiyalari yo'q.
 * Hamma HTML/CSS/JS shu fayl ichida.
 */
require_once __DIR__ . '/includes/bootstrap.php';
auth_class(); // Auth class'ni yuklash

if (is_logged_in()) {
    $u = current_user();
    $redirect = match($u['role']) {
        'admin' => '/admin/',
        'developer' => '/developer/',
        default => '/user/',
    };
    header('Location: ' . $redirect);
    exit;
}

$mode = $_GET['mode'] ?? 'login';
if (!in_array($mode, ['login','register'], true)) $mode = 'login';

$loginErr = ''; $registerErr = '';
$registerForm = [];
$referral = $_GET['ref'] ?? ($_POST['referral'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        if (($_POST['form'] ?? '') === 'register') $registerErr = t('csrf_invalid');
        else $loginErr = t('csrf_invalid');
    } else {
        if (($_POST['form'] ?? '') === 'login') {
            $r = Auth::login($_POST['login'] ?? '', $_POST['password'] ?? '', !empty($_POST['remember']));
            if ($r['ok']) { header('Location: ' . $r['redirect']); exit; }
            $loginErr = $r['msg'];
            $mode = 'login';
        } elseif (($_POST['form'] ?? '') === 'register') {
            $r = Auth::register($_POST);
            if ($r['ok']) { header('Location: ' . $r['redirect']); exit; }
            $registerErr = $r['msg'];
            $registerForm = $_POST;
            $mode = 'register';
        }
    }
}

$auth_login_image    = setting('auth_login_image', '');
$auth_register_image = setting('auth_register_image', '');
$google_client_id    = setting('google_client_id', '');
$site_name = setting('site_name', SITE_NAME);
$pageTitle = ($mode === 'register' ? t('register') : t('login')) . ' — ' . $site_name;
?><!DOCTYPE html>
<html lang="<?= lang() === 'uz_cyrillic' ? 'uz-Cyrl' : 'uz' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#3B82F6">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="format-detection" content="telephone=no, address=no, email=no">
<meta name="color-scheme" content="light">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e(setting('seo_description', 'Avtomaktab imtihoniga onlayn tayyorlanish')) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(setting('site_logo', '/assets/images/logo.svg')) ?>">
<style>
<?= base_css() ?>

/* ============================================================
   LOGIN.PHP — auth-specific custom design
   ============================================================ */
body{margin:0;padding:0;overflow-x:hidden;background:#fff}

/* Floating top controls */
.back-home{position:fixed;top:24px;left:24px;z-index:100;display:inline-flex;align-items:center;gap:8px;
  padding:10px 18px;background:rgba(255,255,255,.95);backdrop-filter:blur(10px);
  border:1px solid rgba(0,0,0,.06);border-radius:100px;color:var(--text);text-decoration:none;
  font-size:13px;font-weight:600;box-shadow:0 4px 14px rgba(0,0,0,.06);transition:all .25s}
.back-home:hover{background:#fff;color:var(--primary);box-shadow:0 6px 20px rgba(0,0,0,.1)}

.auth-lang{position:fixed;top:24px;right:24px;z-index:100;display:inline-flex;
  background:rgba(255,255,255,.95);backdrop-filter:blur(10px);border-radius:100px;padding:4px;gap:2px;
  border:1px solid rgba(0,0,0,.06);box-shadow:0 4px 14px rgba(0,0,0,.06)}
.auth-lang a{padding:6px 14px;border-radius:100px;font-size:12px;font-weight:700;color:var(--text-soft);text-decoration:none;transition:all .2s}
.auth-lang a.active{background:var(--primary);color:#fff}

/* Layout: split-screen */
.auth-fullscreen{min-height:100vh;width:100vw;position:relative;display:flex}
.auth-card-fs{flex:1;display:flex;width:100%;min-height:100vh;background:#fff;overflow:hidden;position:relative}
.auth-side{flex:0 0 50%;width:50%;position:relative;transition:transform .8s cubic-bezier(.65,0,.35,1)}

/* Form side */
.auth-side-form{display:flex;align-items:center;justify-content:center;padding:60px 40px;background:#fff;overflow-y:auto}
.auth-form{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  padding:60px 40px;opacity:0;pointer-events:none;transition:opacity .4s ease;overflow-y:auto}
.auth-form.auth-form-login{opacity:1;pointer-events:auto}
.auth-form-inner{width:100%;max-width:400px;animation:authFadeIn .6s ease both}
@keyframes authFadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

/* Image side */
.auth-side-image{background:linear-gradient(135deg,#3B82F6 0%,#2563EB 50%,#1E40AF 100%);
  color:#fff;position:relative;overflow:hidden}
.auth-image-content{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  padding:60px 40px;text-align:center;opacity:0;pointer-events:none;transition:opacity .4s ease;z-index:2}
.auth-image-content.auth-image-login{opacity:1;pointer-events:auto;
  background:linear-gradient(135deg,#3B82F6 0%,#2563EB 50%,#1E40AF 100%)}
.auth-image-content.auth-image-register{
  background:linear-gradient(135deg,#10B981 0%,#059669 100%)}
.auth-image-inner{max-width:380px;animation:authFadeIn .6s ease both;position:relative;z-index:2}
.auth-decorative{font-size:90px;margin-bottom:18px;animation:float 4s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0) rotate(-2deg)}50%{transform:translateY(-12px) rotate(2deg)}}
.auth-image-inner h2{color:#fff;font-size:34px;font-weight:800;margin-bottom:14px;line-height:1.2}
.auth-image-inner p{font-size:16px;line-height:1.6;opacity:.95;margin-bottom:32px}

.btn-auth-ghost{display:inline-flex;align-items:center;gap:8px;padding:14px 32px;
  background:rgba(255,255,255,.18);backdrop-filter:blur(10px);
  color:#fff;border:1.5px solid rgba(255,255,255,.4);border-radius:12px;
  font-weight:600;font-size:14px;cursor:pointer;transition:all .25s ease;font-family:inherit}
.btn-auth-ghost:hover{background:rgba(255,255,255,.28);border-color:#fff;color:#fff}

.auth-features{display:flex;flex-direction:column;gap:12px;margin-top:36px;font-size:14px}
.auth-features > div{display:flex;align-items:center;gap:8px;justify-content:center;opacity:.95}

/* Decorative shapes */
.auth-shape{position:absolute;border-radius:50%;filter:blur(50px);z-index:1;pointer-events:none}
.auth-shape-1{width:280px;height:280px;background:rgba(255,255,255,.18);top:-80px;right:-80px;animation:shapeFloat 8s ease-in-out infinite}
.auth-shape-2{width:200px;height:200px;background:rgba(168,85,247,.3);bottom:80px;left:-50px;animation:shapeFloat 10s ease-in-out infinite reverse}
.auth-shape-3{width:160px;height:160px;background:rgba(236,72,153,.25);top:50%;right:20%;animation:shapeFloat 12s ease-in-out infinite}
@keyframes shapeFloat{0%,100%{transform:translate(0,0)}50%{transform:translate(30px,-30px)}}

/* Form components */
.auth-brand{display:flex;align-items:center;gap:12px;margin-bottom:24px}
.brand-icon{width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#3B82F6,#1E40AF);
  color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px;
  box-shadow:0 8px 18px rgba(59,130,246,.3);flex-shrink:0}
.auth-brand strong{display:block;font-size:15px;font-weight:800;line-height:1.1;color:var(--text)}
.auth-brand small{display:block;font-size:11px;color:var(--text-soft);text-transform:uppercase;letter-spacing:.04em;margin-top:2px}
.auth-form-inner h1{font-size:32px;font-weight:800;letter-spacing:-.02em;margin-bottom:6px;color:var(--text)}
.auth-sub{color:var(--text-soft);font-size:14px;margin-bottom:24px}

/* Input groups */
.input-group{position:relative}
.input-group .input-icon{position:absolute;top:50%;left:14px;transform:translateY(-50%);color:var(--text-mute);pointer-events:none;z-index:1}
.input-group .form-control{padding-left:42px}
.input-group .input-action{position:absolute;top:50%;right:8px;transform:translateY(-50%);
  background:transparent;border:none;width:32px;height:32px;border-radius:6px;color:var(--text-mute);cursor:pointer}
.input-group .input-action:hover{background:var(--bg-mute);color:var(--text)}

.form-check{display:inline-flex;align-items:center;gap:8px;cursor:pointer;user-select:none;font-size:14px;color:var(--text-soft)}
.form-check input{width:18px;height:18px;cursor:pointer;accent-color:var(--primary)}

.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}

/* Auth divider */
.auth-divider{position:relative;text-align:center;margin:18px 0;font-size:12px;color:var(--text-mute);text-transform:uppercase;letter-spacing:.05em}
.auth-divider::before,.auth-divider::after{content:'';position:absolute;top:50%;width:42%;height:1px;background:var(--border)}
.auth-divider::before{left:0}.auth-divider::after{right:0}
.auth-divider span{position:relative;background:#fff;padding:0 12px;z-index:1}

.auth-mobile-toggle{display:none;text-align:center;margin-top:18px;padding-top:18px;
  border-top:1px solid var(--border);font-size:13px;color:var(--text-soft)}
.auth-mobile-toggle a{font-weight:700;margin-left:4px}

.auth-demo{margin-top:14px;padding:8px 12px;background:var(--bg-soft);border:1px dashed var(--border);
  border-radius:8px;font-size:11px;color:var(--text-soft);text-align:center}
.auth-demo strong{color:var(--primary)}

.btn-auth-primary{display:inline-flex;align-items:center;justify-content:center;gap:8px;
  width:100%;padding:14px 24px;border-radius:12px;
  background:linear-gradient(135deg,#3B82F6,#2563EB);color:#fff;border:none;
  font-weight:700;font-size:14px;cursor:pointer;font-family:inherit;
  transition:all .25s ease;box-shadow:0 8px 20px rgba(59,130,246,.3)}
.btn-auth-primary:hover{filter:brightness(1.05);box-shadow:0 12px 28px rgba(59,130,246,.4)}

.btn-google-disabled{display:inline-flex;align-items:center;justify-content:center;gap:10px;
  width:100%;padding:12px 20px;border-radius:10px;background:#fff;color:#3c4043;
  border:1.5px solid #dadce0;font-weight:500;font-size:14px;cursor:pointer;font-family:inherit;
  transition:all .25s;opacity:.7}
.btn-google-disabled:hover{background:#F8F9FA;border-color:#dadce0;opacity:1}
.g_id_signin{margin-bottom:14px}

/* Toggle state */
.auth-card-fs.is-register .auth-side-form{transform:translateX(100%)}
.auth-card-fs.is-register .auth-side-image{transform:translateX(-100%)}
.auth-card-fs.is-register .auth-form-login{opacity:0;pointer-events:none}
.auth-card-fs.is-register .auth-form-register{opacity:1;pointer-events:auto}
.auth-card-fs.is-register .auth-image-login{opacity:0;pointer-events:none}
.auth-card-fs.is-register .auth-image-register{opacity:1;pointer-events:auto}

/* Password strength meter */
.pw-strength{margin-top:8px;display:none}
.pw-strength .pw-bar{flex:1;height:4px;border-radius:2px;background:var(--bg-mute);transition:background .2s}
.pw-text{font-size:12px;color:var(--text-mute)}

/* RESPONSIVE */
@media (max-width:880px){
  .auth-fullscreen{min-height:auto}
  .auth-card-fs{flex-direction:column;min-height:100vh}
  .auth-side{flex:none;width:100%;transform:none !important}
  .auth-side-form{padding:64px 18px 28px;min-height:100dvh}
  .auth-side-image{display:none}
  .auth-form{position:relative;padding:0;display:none;inset:auto;width:100%}
  .auth-form.auth-form-login{display:flex}
  .auth-card-fs.is-register .auth-form-login{display:none}
  .auth-card-fs.is-register .auth-form-register{display:flex}
  .auth-mobile-toggle{display:block}
  .auth-form-inner{max-width:none;width:100%;animation:none}
  .auth-form-inner h1{font-size:clamp(22px, 6vw, 28px);margin-bottom:4px}
  .auth-sub{font-size:13px;margin-bottom:18px}
  .auth-brand{margin-bottom:18px}
  .brand-icon{width:42px;height:42px;font-size:13px;border-radius:10px}
  .form-row{grid-template-columns:1fr;gap:0}
  .btn-auth-primary{min-height:50px;font-size:15px}
  .auth-divider{margin:14px 0;font-size:11px}
  .back-home{top:12px;left:12px;padding:7px 12px;font-size:12px}
  .auth-lang{top:12px;right:12px;padding:3px}
  .auth-lang a{padding:5px 10px;font-size:11px}
  .auth-decorative,.auth-shape{animation:none !important}
}
@media (max-width:480px){
  .auth-side-form{padding:56px 14px 20px}
  .auth-form-inner h1{font-size:22px}
  .auth-sub{font-size:12px;margin-bottom:14px}
  .brand-icon{width:38px;height:38px}
}
@media (max-width:360px){
  .back-home span{display:none}
  .back-home{padding:8px;width:36px;justify-content:center}
}
@media (hover:none){
  .back-home:hover,.btn-auth-primary:hover,.btn-auth-ghost:hover{transform:none !important}
}
</style>
</head>
<body>

<a href="/" class="back-home" title="Bosh sahifa">
  <?= icon('arrow-left', 18) ?>
  <span><?= lang()==='uz_cyrillic' ? "Бош саҳифа" : "Bosh sahifa" ?></span>
</a>

<div class="auth-fullscreen">
  <div class="auth-card-fs <?= $mode === 'register' ? 'is-register' : '' ?>" id="authCard">

    <!-- ============== FORMS SIDE ============== -->
    <div class="auth-side auth-side-form">

      <!-- LOGIN form -->
      <div class="auth-form auth-form-login">
        <div class="auth-form-inner">
          <div class="auth-brand">
            <div class="brand-icon">VP</div>
            <div>
              <strong><?= e($site_name) ?></strong>
              <small><?= lang()==='uz_cyrillic' ? "Шахсий кабинет" : "Shaxsiy kabinet" ?></small>
            </div>
          </div>

          <h1><?= t('login') ?></h1>
          <p class="auth-sub"><?= lang()==='uz_cyrillic' ? 'Шахсий кабинетингизга киринг' : "Shaxsiy kabinetingizga kiring" ?></p>

          <?php if ($google_client_id): ?>
          <div id="g_id_onload"
               data-client_id="<?= e($google_client_id) ?>"
               data-callback="handleGoogleSignIn"
               data-auto_prompt="false"></div>
          <div class="g_id_signin" data-type="standard" data-shape="rectangular" data-theme="outline"
               data-text="signin_with" data-size="large" data-logo_alignment="left" data-width="100%"></div>
          <div class="auth-divider"><span><?= lang()==='uz_cyrillic' ? "ёки" : "yoki" ?></span></div>
          <?php endif; ?>

          <?php if ($loginErr): ?>
            <div class="alert alert-danger">
              <?= icon('x-circle', 16) ?> <span><?= e($loginErr) ?></span>
            </div>
          <?php endif; ?>

          <form method="post" action="/login.php">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="login">

            <div class="form-group">
              <label class="form-label"><?= t('email') ?> / <?= t('phone') ?></label>
              <div class="input-group">
                <span class="input-icon"><?= icon('user', 16) ?></span>
                <input type="text" name="login" class="form-control" required autocomplete="username"
                       value="<?= e($_POST['login'] ?? '') ?>" placeholder="example@mail.com" style="padding-left:42px">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label"><?= t('password') ?></label>
              <div class="input-group">
                <span class="input-icon"><?= icon('lock', 16) ?></span>
                <input type="password" name="password" id="loginPwd" class="form-control" required minlength="6" autocomplete="current-password" style="padding-left:42px">
                <button type="button" class="input-action" data-toggle-password="loginPwd"><?= icon('eye', 16) ?></button>
              </div>
            </div>

            <div class="form-group flex justify-between items-center" style="font-size:13px">
              <label class="form-check">
                <input type="checkbox" name="remember"> <?= t('remember') ?>
              </label>
              <a href="/forgot.php"><?= t('forgot') ?></a>
            </div>

            <button type="submit" class="btn-auth-primary">
              <?= icon('login', 16) ?> <?= t('login') ?>
            </button>
          </form>

          <div class="auth-mobile-toggle">
            <?= lang()==='uz_cyrillic' ? "Аккаунтингиз йўқми?" : "Akkauntingiz yo'qmi?" ?>
            <a href="#" onclick="event.preventDefault();toggleAuth()"><?= t('register') ?></a>
          </div>
        </div>
      </div>

      <!-- REGISTER form -->
      <div class="auth-form auth-form-register">
        <div class="auth-form-inner">
          <div class="auth-brand">
            <div class="brand-icon" style="background:linear-gradient(135deg,#10B981,#059669)">VP</div>
            <div>
              <strong><?= e($site_name) ?></strong>
              <small><?= lang()==='uz_cyrillic' ? "Янги аккаунт" : "Yangi akkaunt" ?></small>
            </div>
          </div>

          <h1><?= t('register') ?></h1>
          <p class="auth-sub"><?= lang()==='uz_cyrillic' ? "Бепул аккаунт яратинг" : "Bepul akkaunt yarating" ?></p>

          <?php if ($referral): ?>
            <div class="alert alert-success" style="font-size:12px">
              <?= icon('gift', 14) ?>
              <span>Do'st taklifi: <strong><?= e($referral) ?></strong></span>
            </div>
          <?php endif; ?>

          <?php if ($google_client_id): ?>
          <div class="g_id_signin" data-type="standard" data-shape="rectangular" data-theme="outline"
               data-text="signup_with" data-size="large" data-logo_alignment="left" data-width="100%"></div>
          <div class="auth-divider"><span><?= lang()==='uz_cyrillic' ? "ёки" : "yoki" ?></span></div>
          <?php endif; ?>

          <?php if ($registerErr): ?>
            <div class="alert alert-danger">
              <?= icon('x-circle', 16) ?> <span><?= e($registerErr) ?></span>
            </div>
          <?php endif; ?>

          <form method="post" action="/login.php" id="registerForm">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="register">
            <input type="hidden" name="referral" value="<?= e($referral) ?>">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label"><?= t('first_name') ?> *</label>
                <input type="text" name="first_name" class="form-control" required maxlength="50"
                       value="<?= e($registerForm['first_name'] ?? '') ?>" placeholder="Akmal">
              </div>
              <div class="form-group">
                <label class="form-label"><?= t('last_name') ?> *</label>
                <input type="text" name="last_name" class="form-control" required maxlength="50"
                       value="<?= e($registerForm['last_name'] ?? '') ?>" placeholder="Karimov">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label"><?= t('phone') ?> *</label>
              <div class="input-group">
                <span class="input-icon"><?= icon('phone', 16) ?></span>
                <input type="tel" name="phone" class="form-control" required maxlength="20"
                       value="<?= e($registerForm['phone'] ?? '') ?>" placeholder="+998 90 123 45 67" style="padding-left:42px">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label"><?= t('email') ?> <span class="text-mute">(ixtiyoriy)</span></label>
              <div class="input-group">
                <span class="input-icon"><?= icon('mail', 16) ?></span>
                <input type="email" name="email" class="form-control" maxlength="100"
                       value="<?= e($registerForm['email'] ?? '') ?>" style="padding-left:42px">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label"><?= t('password') ?> *</label>
                <div class="input-group">
                  <span class="input-icon"><?= icon('lock', 16) ?></span>
                  <input type="password" name="password" id="regPwd" class="form-control" required minlength="8" data-strength="1" autocomplete="new-password" style="padding-left:42px">
                  <button type="button" class="input-action" data-toggle-password="regPwd"><?= icon('eye', 16) ?></button>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label"><?= t('password2') ?> *</label>
                <div class="input-group">
                  <span class="input-icon"><?= icon('lock', 16) ?></span>
                  <input type="password" name="password2" id="regPwd2" class="form-control" required minlength="8" autocomplete="new-password" style="padding-left:42px">
                </div>
              </div>
            </div>

            <?= Security::password_strength_meter() ?>

            <div class="form-group" style="margin-top:14px">
              <label class="form-check">
                <input type="checkbox" name="agree" required>
                <span><?= t('agree') ?></span>
              </label>
            </div>

            <button type="submit" class="btn-auth-primary" style="background:linear-gradient(135deg,#10B981,#059669);box-shadow:0 8px 20px rgba(16,185,129,.3)">
              <?= icon('check-circle', 16) ?> <?= t('register') ?>
            </button>
          </form>

          <div class="auth-mobile-toggle">
            <?= lang()==='uz_cyrillic' ? "Аккаунтингиз борми?" : "Akkauntingiz bormi?" ?>
            <a href="#" onclick="event.preventDefault();toggleAuth()"><?= t('login') ?></a>
          </div>
        </div>
      </div>
    </div>

    <!-- ============== IMAGE SIDE ============== -->
    <div class="auth-side auth-side-image">
      <!-- LOGIN image -->
      <div class="auth-image-content auth-image-login"
           <?= $auth_login_image ? 'style="background-image:linear-gradient(135deg,rgba(59,130,246,.85),rgba(30,64,175,.85)),url('.e($auth_login_image).');background-size:cover;background-position:center"' : '' ?>>
        <div class="auth-image-inner">
          <div class="auth-decorative">🚗</div>
          <h2><?= lang()==='uz_cyrillic' ? "Хуш келибсиз!" : "Xush kelibsiz!" ?></h2>
          <p><?= lang()==='uz_cyrillic'
              ? "Аккаунтингиз йўқми? Бепул рўйхатдан ўтинг ва имтиҳонга тайёрланинг."
              : "Akkauntingiz yo'qmi? Bepul ro'yxatdan o'ting va imtihonga tayyorlaning." ?></p>
          <button type="button" class="btn-auth-ghost" onclick="toggleAuth()">
            <?= t('register') ?> <?= icon('arrow-right', 16) ?>
          </button>
          <div class="auth-features">
            <div><?= icon('check-circle', 16) ?> Bepul akkaunt</div>
            <div><?= icon('check-circle', 16) ?> 3000+ savol</div>
            <div><?= icon('check-circle', 16) ?> 98% muvaffaqiyat</div>
          </div>
        </div>
      </div>

      <!-- REGISTER image -->
      <div class="auth-image-content auth-image-register"
           <?= $auth_register_image ? 'style="background-image:linear-gradient(135deg,rgba(16,185,129,.85),rgba(5,150,105,.85)),url('.e($auth_register_image).');background-size:cover;background-position:center"' : '' ?>>
        <div class="auth-image-inner">
          <div class="auth-decorative">🎓</div>
          <h2><?= lang()==='uz_cyrillic' ? "Қайтиб келдингизми?" : "Qaytib keldingizmi?" ?></h2>
          <p><?= lang()==='uz_cyrillic'
              ? "Аккаунтингиз бор бўлса, тўғридан-тўғри киринг ва ўрганишда давом этинг!"
              : "Akkauntingiz bor bo'lsa, to'g'ridan-to'g'ri kiring va o'rganishda davom eting!" ?></p>
          <button type="button" class="btn-auth-ghost" onclick="toggleAuth()">
            <?= icon('arrow-left', 16) ?> <?= t('login') ?>
          </button>
          <div class="auth-features">
            <div><?= icon('check-circle', 16) ?> Tarixingiz saqlangan</div>
            <div><?= icon('check-circle', 16) ?> Kengaytirilgan statistika</div>
            <div><?= icon('check-circle', 16) ?> Real vaqtda natija</div>
          </div>
        </div>
      </div>

      <div class="auth-shape auth-shape-1"></div>
      <div class="auth-shape auth-shape-2"></div>
      <div class="auth-shape auth-shape-3"></div>
    </div>
  </div>

  <!-- Lang switcher -->
  <div class="auth-lang">
    <a href="?setlang=uz_latin" class="<?= lang()==='uz_latin'?'active':'' ?>">Uz</a>
    <a href="?setlang=uz_cyrillic" class="<?= lang()==='uz_cyrillic'?'active':'' ?>">Кр</a>
  </div>
</div>

<?php if ($google_client_id): ?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
function handleGoogleSignIn(response) {
  fetch('/api/?action=google_signin', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({credential: response.credential})
  }).then(r => r.json()).then(data => {
    if (data.ok) window.location.href = data.redirect || '/user/';
    else alert('Google login error: ' + (data.error || 'Unknown'));
  }).catch(e => alert('Network error'));
}
</script>
<?php endif; ?>

<script>
/* === LOGIN.PHP — page-specific JS only === */

// Auth toggle (login <-> register)
function toggleAuth() {
  const card = document.getElementById('authCard');
  card.classList.toggle('is-register');
  const newMode = card.classList.contains('is-register') ? 'register' : 'login';
  history.replaceState(null, '', '/login.php?mode=' + newMode);
  if (window.innerWidth < 880) window.scrollTo({top: 0, behavior: 'smooth'});
}

// Show password toggle
document.querySelectorAll('[data-toggle-password]').forEach(btn => {
  btn.addEventListener('click', () => {
    const target = document.getElementById(btn.dataset.togglePassword);
    if (target) target.type = target.type === 'password' ? 'text' : 'password';
  });
});

// Password match indicator
(function(){
  const p1 = document.getElementById('regPwd');
  const p2 = document.getElementById('regPwd2');
  if (!p1 || !p2) return;
  const check = () => {
    p2.style.borderColor = (p2.value && p1.value !== p2.value) ? 'var(--danger)' : '';
  };
  p1.addEventListener('input', check);
  p2.addEventListener('input', check);
})();

// Form submit loading state
document.addEventListener('submit', e => {
  const btn = e.submitter;
  if (btn && !btn.disabled) {
    setTimeout(() => {
      btn.disabled = true;
      btn.style.opacity = '.7';
    }, 0);
  }
});
</script>
</body>
</html>
