<?php
require_once __DIR__ . '/includes/auth.php';

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
if (!in_array($mode, ['login','register'])) $mode = 'login';

$loginErr = ''; $registerErr = '';
$registerForm = [];
$referral = $_GET['ref'] ?? ($_POST['referral'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        if (($_POST['form'] ?? '') === 'register') $registerErr = t('csrf_invalid');
        else $loginErr = t('csrf_invalid');
    } else {
        // LOGIN
        if (($_POST['form'] ?? '') === 'login') {
            $r = Auth::login(
                $_POST['login'] ?? '',
                $_POST['password'] ?? '',
                !empty($_POST['remember'])
            );
            if ($r['ok']) { header('Location: ' . $r['redirect']); exit; }
            $loginErr = $r['msg'];
            $mode = 'login';
        }
        // REGISTER
        elseif (($_POST['form'] ?? '') === 'register') {
            $r = Auth::register($_POST);
            if ($r['ok']) { header('Location: ' . $r['redirect']); exit; }
            $registerErr = $r['msg'];
            $registerForm = $_POST;
            $mode = 'register';
        }
    }
}

render_head($mode === 'register' ? t('register') : t('login'));
?>
<header class="auth-topbar">
  <div class="container nav" style="padding:14px 0">
    <a href="/" class="logo"><span class="logo-icon">VP</span><span><?= e(setting('site_name', SITE_NAME)) ?></span></a>
    <div class="lang-switch">
      <a href="?setlang=uz_latin" class="<?= lang()==='uz_latin'?'active':'' ?>">Uz</a>
      <a href="?setlang=uz_cyrillic" class="<?= lang()==='uz_cyrillic'?'active':'' ?>">Кр</a>
    </div>
  </div>
</header>

<main class="auth-page-v2">
  <div class="auth-card <?= $mode === 'register' ? 'is-register' : '' ?>" id="authCard">
    <!-- Forms side -->
    <div class="auth-side auth-side-form">
      <!-- LOGIN form -->
      <div class="auth-form auth-form-login">
        <div class="auth-form-inner">
          <div class="auth-icon">
            <?= icon('login', 26) ?>
          </div>
          <h1><?= t('login') ?></h1>
          <p class="auth-sub"><?= lang()==='uz_cyrillic' ? 'Шахсий кабинетингизга киринг' : "Shaxsiy kabinetingizga kiring" ?></p>

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
                       value="<?= e($_POST['login'] ?? '') ?>"
                       placeholder="example@mail.com">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label"><?= t('password') ?></label>
              <div class="input-group">
                <span class="input-icon"><?= icon('lock', 16) ?></span>
                <input type="password" name="password" id="loginPwd" class="form-control" required minlength="6" autocomplete="current-password">
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

          <!-- Mobile toggle -->
          <div class="auth-mobile-toggle">
            <?= lang()==='uz_cyrillic' ? "Аккаунтингиз йўқми?" : "Akkauntingiz yo'qmi?" ?>
            <a href="/login.php?mode=register" onclick="event.preventDefault();toggleAuth()"><?= t('register') ?></a>
          </div>

          <div class="auth-demo">
            <strong>Demo:</strong> admin@vatanparvar.uz / admin123
          </div>
        </div>
      </div>

      <!-- REGISTER form -->
      <div class="auth-form auth-form-register">
        <div class="auth-form-inner">
          <div class="auth-icon" style="background:linear-gradient(135deg,#10B981,#059669)">
            <?= icon('user', 26) ?>
          </div>
          <h1><?= t('register') ?></h1>
          <p class="auth-sub"><?= lang()==='uz_cyrillic' ? "Бепул аккаунт яратинг" : "Bepul akkaunt yarating" ?></p>

          <?php if ($referral): ?>
            <div class="alert alert-success" style="font-size:12px">
              <?= icon('gift', 14) ?>
              <span><?= lang()==='uz_cyrillic' ? "Дўст таклифи:" : "Do'st taklifi:" ?> <strong><?= e($referral) ?></strong></span>
            </div>
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
                       value="<?= e($registerForm['phone'] ?? '') ?>"
                       placeholder="+998 90 123 45 67">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label"><?= t('email') ?> <span class="text-mute">(ixtiyoriy)</span></label>
              <div class="input-group">
                <span class="input-icon"><?= icon('mail', 16) ?></span>
                <input type="email" name="email" class="form-control" maxlength="100"
                       value="<?= e($registerForm['email'] ?? '') ?>" placeholder="example@mail.com">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label"><?= t('password') ?> *</label>
                <div class="input-group">
                  <span class="input-icon"><?= icon('lock', 16) ?></span>
                  <input type="password" name="password" id="regPwd" class="form-control" required minlength="8"
                         data-strength="1" autocomplete="new-password">
                  <button type="button" class="input-action" data-toggle-password="regPwd"><?= icon('eye', 16) ?></button>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label"><?= t('password2') ?> *</label>
                <div class="input-group">
                  <span class="input-icon"><?= icon('lock', 16) ?></span>
                  <input type="password" name="password2" id="regPwd2" class="form-control" required minlength="8"
                         autocomplete="new-password">
                </div>
              </div>
            </div>

            <?= Security::password_strength_meter() ?>

            <div class="form-group" style="margin-top:14px">
              <label class="form-check" style="font-size:13px">
                <input type="checkbox" name="agree" required>
                <span><?= t('agree') ?></span>
              </label>
            </div>

            <button type="submit" class="btn-auth-primary" style="background:linear-gradient(135deg,#10B981,#059669);box-shadow:0 8px 20px rgba(16,185,129,.3)">
              <?= icon('check-circle', 16) ?> <?= t('register') ?>
            </button>
          </form>

          <!-- Mobile toggle -->
          <div class="auth-mobile-toggle">
            <?= lang()==='uz_cyrillic' ? "Аккаунтингиз борми?" : "Akkauntingiz bormi?" ?>
            <a href="/login.php?mode=login" onclick="event.preventDefault();toggleAuth()"><?= t('login') ?></a>
          </div>
        </div>
      </div>
    </div>

    <!-- Image side (overlay) -->
    <div class="auth-side auth-side-image">
      <!-- Login mode content -->
      <div class="auth-image-content auth-image-login">
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
            <div><?= icon('check-circle', 16) ?> <?= lang()==='uz_cyrillic' ? "Бепул аккаунт" : "Bepul akkaunt" ?></div>
            <div><?= icon('check-circle', 16) ?> <?= lang()==='uz_cyrillic' ? "3000+ савол" : "3000+ savol" ?></div>
            <div><?= icon('check-circle', 16) ?> <?= lang()==='uz_cyrillic' ? "98% муваффақият" : "98% muvaffaqiyat" ?></div>
          </div>
        </div>
      </div>

      <!-- Register mode content -->
      <div class="auth-image-content auth-image-register">
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
            <div><?= icon('check-circle', 16) ?> <?= lang()==='uz_cyrillic' ? "Тарихингиз сақланган" : "Tarixingiz saqlangan" ?></div>
            <div><?= icon('check-circle', 16) ?> <?= lang()==='uz_cyrillic' ? "Кенгайтирилган статистика" : "Kengaytirilgan statistika" ?></div>
            <div><?= icon('check-circle', 16) ?> <?= lang()==='uz_cyrillic' ? "Реал вақтда натижа" : "Real vaqtda natija" ?></div>
          </div>
        </div>
      </div>

      <!-- Floating decorative shapes -->
      <div class="auth-shape auth-shape-1"></div>
      <div class="auth-shape auth-shape-2"></div>
      <div class="auth-shape auth-shape-3"></div>
    </div>
  </div>
</main>

<style>
/* ============================================================
   SPLIT-SCREEN AUTH (v2.8)
   ============================================================ */
body{background:linear-gradient(135deg,#F0F9FF 0%,#DBEAFE 100%);min-height:100vh}
.header,.footer{display:none}

.auth-topbar{position:absolute;top:0;left:0;right:0;z-index:10}
.auth-topbar .nav{padding:18px 24px;display:flex;justify-content:space-between;align-items:center}
.auth-topbar .logo{color:var(--text)}

.auth-page-v2{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:80px 20px 40px}

.auth-card{position:relative;width:100%;max-width:980px;min-height:600px;background:#fff;
  border-radius:24px;overflow:hidden;display:flex;
  box-shadow:0 30px 80px rgba(15,23,42,.15),0 0 0 1px rgba(15,23,42,.04)}

/* Sides */
.auth-side{flex:0 0 50%;position:relative;transition:transform .8s cubic-bezier(.65,0,.35,1)}

/* Form side */
.auth-side-form{background:#fff;display:flex;align-items:center;justify-content:center;padding:50px 40px}
.auth-form{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  padding:50px 40px;opacity:0;pointer-events:none;transition:opacity .4s ease;overflow-y:auto}
.auth-form.auth-form-login{opacity:1;pointer-events:auto}
.auth-form-inner{width:100%;max-width:380px;animation:authFadeIn .6s var(--ease-soft) both}
@keyframes authFadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

/* Image side */
.auth-side-image{background:linear-gradient(135deg,#3B82F6 0%,#2563EB 50%,#1E40AF 100%);
  color:#fff;position:relative;overflow:hidden}
.auth-image-content{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  padding:50px 40px;text-align:center;opacity:0;pointer-events:none;transition:opacity .4s ease;z-index:2}
.auth-image-content.auth-image-login{opacity:1;pointer-events:auto}
.auth-image-inner{max-width:340px;animation:authFadeIn .6s var(--ease-soft) both;position:relative;z-index:2}

.auth-decorative{font-size:80px;margin-bottom:14px;animation:float 4s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0) rotate(-2deg)}50%{transform:translateY(-12px) rotate(2deg)}}

.auth-image-inner h2{color:#fff;font-size:30px;font-weight:800;margin-bottom:14px;line-height:1.2}
.auth-image-inner p{font-size:15px;line-height:1.6;opacity:.95;margin-bottom:28px}

.btn-auth-ghost{display:inline-flex;align-items:center;gap:8px;padding:13px 28px;
  background:rgba(255,255,255,.15);backdrop-filter:blur(10px);
  color:#fff;border:1.5px solid rgba(255,255,255,.4);border-radius:12px;
  font-weight:600;font-size:14px;cursor:pointer;transition:all .25s var(--ease-soft);font-family:inherit}
.btn-auth-ghost:hover{background:rgba(255,255,255,.25);border-color:#fff;color:#fff}

.auth-features{display:flex;flex-direction:column;gap:10px;margin-top:32px;font-size:14px}
.auth-features > div{display:flex;align-items:center;gap:8px;justify-content:center;opacity:.95}

/* Decorative shapes */
.auth-shape{position:absolute;border-radius:50%;filter:blur(40px);z-index:1;pointer-events:none}
.auth-shape-1{width:200px;height:200px;background:rgba(255,255,255,.15);top:-50px;right:-50px;
  animation:shapeFloat 8s ease-in-out infinite}
.auth-shape-2{width:150px;height:150px;background:rgba(168,85,247,.25);bottom:50px;left:-30px;
  animation:shapeFloat 10s ease-in-out infinite reverse}
.auth-shape-3{width:120px;height:120px;background:rgba(236,72,153,.2);top:50%;right:20%;
  animation:shapeFloat 12s ease-in-out infinite}
@keyframes shapeFloat{0%,100%{transform:translate(0,0)}50%{transform:translate(20px,-20px)}}

/* Form components */
.auth-icon{width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,var(--primary),var(--secondary));
  color:#fff;display:flex;align-items:center;justify-content:center;margin-bottom:18px;
  box-shadow:0 8px 20px rgba(59,130,246,.3)}
.auth-form-inner h1{font-size:28px;font-weight:800;letter-spacing:-.02em;margin-bottom:6px;color:var(--text)}
.auth-sub{color:var(--text-soft);font-size:14px;margin-bottom:24px}
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
  transition:all .25s var(--ease-soft);box-shadow:0 8px 20px rgba(59,130,246,.3)}
.btn-auth-primary:hover{filter:brightness(1.05);box-shadow:0 12px 28px rgba(59,130,246,.4)}
.btn-auth-primary:active{filter:brightness(.95)}

/* ============== TOGGLE STATE — Image swaps to LEFT ============== */
.auth-card.is-register .auth-side-form{transform:translateX(100%)}
.auth-card.is-register .auth-side-image{transform:translateX(-100%)}
.auth-card.is-register .auth-form-login{opacity:0;pointer-events:none}
.auth-card.is-register .auth-form-register{opacity:1;pointer-events:auto}
.auth-card.is-register .auth-image-login{opacity:0;pointer-events:none}
.auth-card.is-register .auth-image-register{opacity:1;pointer-events:auto}

/* ============== RESPONSIVE ============== */
@media (max-width:880px){
  .auth-page-v2{padding:80px 14px 40px}
  .auth-card{flex-direction:column;min-height:auto;max-width:480px}
  .auth-side{flex:none;width:100%}
  .auth-side-form{padding:32px 24px;min-height:auto}
  .auth-side-image{display:none} /* Mobile'da image yashirinadi */
  .auth-form{position:relative;padding:0;display:none}
  .auth-form.auth-form-login{display:flex}
  .auth-card.is-register .auth-form-login{display:none}
  .auth-card.is-register .auth-form-register{display:flex}
  .auth-card.is-register .auth-side-form{transform:none}
  .auth-mobile-toggle{display:block}
  .auth-form-inner{max-width:none;animation:none}
  .auth-form-inner h1{font-size:24px}
  .form-row{grid-template-columns:1fr;gap:14px}
}

@media (max-width:480px){
  .auth-side-form{padding:24px 18px}
  .auth-icon{width:48px;height:48px;border-radius:12px}
  .auth-icon svg{width:22px;height:22px}
  .auth-form-inner h1{font-size:22px}
  .auth-sub{font-size:13px}
}

/* Form inputs already styled by global CSS */
</style>

<script>
function toggleAuth() {
  const card = document.getElementById('authCard');
  card.classList.toggle('is-register');
  // URL update without reload
  const newMode = card.classList.contains('is-register') ? 'register' : 'login';
  history.replaceState(null, '', '/login.php?mode=' + newMode);
  // Scroll to top on mobile
  if (window.innerWidth < 880) window.scrollTo({top: 0, behavior: 'smooth'});
}

// Password match validation
(function(){
  const p1 = document.getElementById('regPwd');
  const p2 = document.getElementById('regPwd2');
  if (!p1 || !p2) return;
  const check = () => {
    if (p2.value && p1.value !== p2.value) {
      p2.style.borderColor = 'var(--danger)';
    } else {
      p2.style.borderColor = '';
    }
  };
  p1.addEventListener('input', check);
  p2.addEventListener('input', check);
})();
</script>
</body></html>
