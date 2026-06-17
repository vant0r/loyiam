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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = t('csrf_invalid');
    } else {
        $login    = $_POST['login']    ?? '';
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        $r = Auth::login($login, $password, $remember);
        if ($r['ok']) {
            header('Location: ' . $r['redirect']);
            exit;
        } else {
            $error = $r['msg'];
        }
    }
}

render_head(t('login'));
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
      <div style="display:inline-flex;width:64px;height:64px;background:linear-gradient(135deg,var(--primary),var(--secondary));border-radius:18px;align-items:center;justify-content:center;color:#fff;margin-bottom:14px">
        <?= icon('login', 30) ?>
      </div>
    </div>
    <h2><?= t('login') ?></h2>
    <p class="subtitle"><?= lang()==='uz_cyrillic' ? 'Шахсий кабинетингизга киринг' : 'Shaxsiy kabinetingizga kiring' ?></p>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <?= icon('x-circle', 18) ?> <?= e($error) ?>
      </div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label"><?= t('email') ?> / <?= t('phone') ?></label>
        <div class="input-group">
          <span class="input-icon"><?= icon('user', 16) ?></span>
          <input type="text" name="login" class="form-control" required autofocus
                 value="<?= e($_POST['login'] ?? '') ?>"
                 placeholder="example@mail.com <?= t('or') ?> +998901234567">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label"><?= t('password') ?></label>
        <div class="input-group">
          <span class="input-icon"><?= icon('lock', 16) ?></span>
          <input type="password" name="password" id="pwd" class="form-control" required minlength="6">
          <button type="button" class="input-action" data-toggle-password="pwd" aria-label="Show/Hide">
            <?= icon('eye', 16) ?>
          </button>
        </div>
      </div>
      <div class="form-group flex justify-between items-center" style="font-size:13px">
        <label class="form-check">
          <input type="checkbox" name="remember"> <?= t('remember') ?>
        </label>
        <a href="/forgot.php"><?= t('forgot') ?></a>
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg">
        <?= icon('login', 18) ?> <?= t('login') ?>
      </button>
    </form>

    <div class="auth-divider"><?= t('or') ?></div>
    <a href="/register.php" class="btn btn-outline btn-block"><?= t('register') ?></a>

    <div class="alert alert-info mt-3" style="font-size:12px;padding:10px 14px">
      <strong>Demo:</strong> admin@vatanparvar.uz / admin123 · user@vatanparvar.uz / user123 · dev@vatanparvar.uz / dev123
    </div>
  </div>
</main>

<footer class="footer" style="padding:24px 0;margin-top:0">
  <div class="container text-center" style="font-size:13px;color:#64748B">
    © <?= date('Y') ?> <?= e(setting('site_name', SITE_NAME)) ?>. <?= t('all_rights') ?>.
  </div>
</footer>
</body></html>
