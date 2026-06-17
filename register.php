<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) { header('Location: /user/'); exit; }

$error = ''; $form = $_POST;
$referral = $_GET['ref'] ?? ($_POST['referral'] ?? '');
$selectedTariff = (int)($_GET['tariff'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = t('csrf_invalid');
    } else {
        $r = Auth::register($_POST);
        if ($r['ok']) {
            header('Location: ' . ($selectedTariff ? '/user/tariflar.php?tariff='.$selectedTariff : $r['redirect']));
            exit;
        } else {
            $error = $r['msg'];
        }
    }
}

render_head(t('register'));
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
  <div class="auth-box fade-up" style="max-width:540px">
    <div class="text-center mb-3">
      <div style="display:inline-flex;width:64px;height:64px;background:linear-gradient(135deg,var(--success),#059669);border-radius:18px;align-items:center;justify-content:center;color:#fff;margin-bottom:14px">
        <?= icon('user', 30) ?>
      </div>
    </div>
    <h2><?= t('register') ?></h2>
    <p class="subtitle"><?= lang()==='uz_cyrillic' ? 'Бепул аккаунт яратинг ва ҳозироқ бошланг' : 'Bepul akkaunt yarating va hoziroq boshlang' ?></p>

    <?php if ($referral): ?>
      <div class="alert alert-success" style="font-size:13px">
        <?= icon('gift', 18) ?>
        <span><?= lang()==='uz_cyrillic' ? "Дўст таклиф қилди! Сиз бонус оласиз" : "Do'st taklif qildi! Siz bonus olasiz" ?>: <strong><?= e($referral) ?></strong></span>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <?= icon('x-circle', 18) ?> <?= e($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" id="regForm">
      <?= csrf_field() ?>
      <input type="hidden" name="referral" value="<?= e($referral) ?>">
      <div class="grid-2" style="gap:14px">
        <div class="form-group">
          <label class="form-label"><?= t('first_name') ?> <span class="required">*</span></label>
          <input type="text" name="first_name" class="form-control" required maxlength="50"
                 value="<?= e($form['first_name'] ?? '') ?>" placeholder="Akmal">
        </div>
        <div class="form-group">
          <label class="form-label"><?= t('last_name') ?> <span class="required">*</span></label>
          <input type="text" name="last_name" class="form-control" required maxlength="50"
                 value="<?= e($form['last_name'] ?? '') ?>" placeholder="Karimov">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label"><?= t('email') ?> <span class="text-mute">(<?= t('optional') ?>)</span></label>
        <div class="input-group">
          <span class="input-icon"><?= icon('mail', 16) ?></span>
          <input type="email" name="email" class="form-control" maxlength="100"
                 value="<?= e($form['email'] ?? '') ?>" placeholder="example@mail.com">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label"><?= t('phone') ?> <span class="required">*</span></label>
        <div class="input-group">
          <span class="input-icon"><?= icon('phone', 16) ?></span>
          <input type="tel" name="phone" class="form-control" required maxlength="20"
                 value="<?= e($form['phone'] ?? '') ?>" placeholder="+998 90 123 45 67"
                 pattern="[\+\d\s\-\(\)]+">
        </div>
        <div class="form-help"><?= lang()==='uz_cyrillic' ? "Телеграмдан ҳам фойдаланиш учун керак" : "Telegram orqali ham foydalanish uchun kerak" ?></div>
      </div>
      <div class="grid-2" style="gap:14px">
        <div class="form-group">
          <label class="form-label"><?= t('password') ?> <span class="required">*</span></label>
          <div class="input-group">
            <span class="input-icon"><?= icon('lock', 16) ?></span>
            <input type="password" name="password" id="reg_pwd" class="form-control" required minlength="8" data-strength="1">
            <button type="button" class="input-action" data-toggle-password="reg_pwd" aria-label="Show/Hide">
              <?= icon('eye', 16) ?>
            </button>
          </div>
          <?= Security::password_strength_meter() ?>
        </div>
        <div class="form-group">
          <label class="form-label"><?= t('password2') ?> <span class="required">*</span></label>
          <div class="input-group">
            <span class="input-icon"><?= icon('lock', 16) ?></span>
            <input type="password" name="password2" id="reg_pwd2" class="form-control" required minlength="8">
            <button type="button" class="input-action" data-toggle-password="reg_pwd2" aria-label="Show/Hide">
              <?= icon('eye', 16) ?>
            </button>
          </div>
          <div class="form-error" id="pwdMatchErr" style="display:none">
            <?= icon('x-circle', 12) ?> <?= t('passwords_dont_match') ?>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-check">
          <input type="checkbox" name="agree" id="agree" required>
          <span><?= t('agree') ?> · <a href="#" style="font-weight:600"><?= lang()==='uz_cyrillic' ? "ўқиш" : "o'qish" ?></a></span>
        </label>
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg">
        <?= t('register') ?> <?= icon('arrow-right', 18) ?>
      </button>
    </form>

    <div class="auth-divider"><?= t('or') ?></div>
    <a href="/login.php" class="btn btn-outline btn-block"><?= t('login') ?></a>
  </div>
</main>

<footer class="footer" style="padding:24px 0;margin-top:0">
  <div class="container text-center" style="font-size:13px;color:#64748B">
    © <?= date('Y') ?> <?= e(setting('site_name', SITE_NAME)) ?>. <?= t('all_rights') ?>.
  </div>
</footer>

<script>
// Password match validation
const p1 = document.getElementById('reg_pwd');
const p2 = document.getElementById('reg_pwd2');
const err = document.getElementById('pwdMatchErr');
function checkMatch(){
  if (p2.value && p1.value !== p2.value) {
    err.style.display = 'flex';
    p2.classList.add('is-error');
  } else {
    err.style.display = 'none';
    p2.classList.remove('is-error');
    if (p2.value) p2.classList.add('is-success');
  }
}
p1.addEventListener('input', checkMatch);
p2.addEventListener('input', checkMatch);
</script>
</body></html>
