<?php
/**
 * user/profil.php — STANDALONE profile page
 */
require_once __DIR__ . '/../includes/bootstrap.php';
auth_class();
require_login();

$u = current_user();
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = t('csrf_invalid');
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'profile') {
            $first = Security::clean($_POST['first_name'] ?? '', 50);
            $last  = Security::clean($_POST['last_name']  ?? '', 50);
            $email = Security::clean($_POST['email']      ?? '', 100);
            $phone = Security::clean($_POST['phone']      ?? '', 20);
            $langSet = in_array($_POST['language'] ?? '', ['uz_latin','uz_cyrillic'], true) ? $_POST['language'] : 'uz_latin';
            $avatar = $u['avatar'];
            if (!empty($_FILES['avatar']['name'])) {
                $up = Security::upload_image($_FILES['avatar'], 'avatar_' . $u['id']);
                if ($up['ok']) { $avatar = $up['url']; @chmod(BASE_PATH . $up['url'], 0644); }
                else $err = $up['error'];
            }
            if (!$err) {
                if ($email && !Security::valid_email($email)) $err = t('invalid_email');
                elseif ($phone && !Security::valid_phone($phone)) $err = t('invalid_phone');
                else {
                    db()->execute("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, language=?, avatar=? WHERE id=?",
                        [$first, $last, $email ?: null, $phone ?: null, $langSet, $avatar, $u['id']]);
                    $_SESSION['lang'] = $langSet;
                    $msg = t('saved_success');
                    $u = db()->fetch("SELECT * FROM users WHERE id=?", [$u['id']]);
                }
            }
        }
        if ($action === 'password') {
            $r = Auth::change_password((int)$u['id'], $_POST['old_password'] ?? '', $_POST['new_password'] ?? '', $_POST['new_password2'] ?? '');
            if ($r['ok']) $msg = $r['msg']; else $err = $r['msg'];
        }
    }
}

$total_tests = (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts WHERE user_id=? AND status='completed'", [$u['id']])['c'] ?? 0);
$avg_score = (float)(db()->fetch("SELECT COALESCE(AVG(score_percent),0) c FROM test_attempts WHERE user_id=? AND status='completed'", [$u['id']])['c'] ?? 0);
$site_name = setting('site_name', SITE_NAME);
?><!DOCTYPE html>
<html lang="<?= lang() === 'uz_cyrillic' ? 'uz-Cyrl' : 'uz' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#3B82F6">
<title><?= e(t('profile')) ?> — <?= e($site_name) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(setting('site_logo', '/assets/images/logo.svg')) ?>">
<style>
<?= base_css() ?>
<?= panel_css() ?>

/* === USER/PROFIL.PHP custom === */
.profile-banner{position:relative;background:linear-gradient(135deg,#4F46E5 0%,#3B82F6 50%,#06B6D4 100%);border-radius:20px;padding:24px;color:#fff;overflow:hidden;box-shadow:0 16px 40px rgba(79,70,229,.3);margin-bottom:18px}
.profile-banner::before{content:'';position:absolute;top:-30%;right:-10%;width:280px;height:280px;background:radial-gradient(circle,rgba(255,255,255,.18),transparent 70%);border-radius:50%}
.pb-inner{position:relative;z-index:1;display:flex;align-items:center;gap:18px;flex-wrap:wrap}
.pb-avatar{width:88px;height:88px;border-radius:50%;border:3px solid rgba(255,255,255,.3);flex-shrink:0;overflow:hidden;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center}
.pb-avatar img{width:100%;height:100%;object-fit:cover}
.pb-letter{font-size:38px;font-weight:900}
.pb-meta{flex:1;min-width:200px}
.pb-meta h2{color:#fff;font-size:22px;font-weight:800;margin:0 0 8px;letter-spacing:-.01em}
.pb-tags{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:12px}
.pb-tags .badge{background:rgba(255,255,255,.2);color:#fff;backdrop-filter:blur(10px)}
.pb-stats{display:flex;gap:18px;flex-wrap:wrap;padding-top:12px;border-top:1px solid rgba(255,255,255,.2)}
.pb-stats > div{display:flex;flex-direction:column}
.pb-stats strong{font-size:18px;font-weight:800}
.pb-stats span{font-size:11px;opacity:.8}

.dash-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media (max-width:880px){.dash-grid{grid-template-columns:1fr}}
.card{background:#fff;border:1px solid #EEF1F5;border-radius:14px;padding:18px}
.card h3{font-size:15px;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px}

.avatar-section{margin-bottom:18px;text-align:center}
.avatar-display{position:relative;width:110px;height:110px;border-radius:50%;margin:0 auto;cursor:pointer;overflow:hidden;border:3px solid #EEF1F5;transition:border-color .2s}
.avatar-display:hover{border-color:var(--primary)}
.avatar-display img,.avatar-letter-big{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#3B82F6,#1E40AF);color:#fff;font-size:42px;font-weight:800;object-fit:cover}
.avatar-overlay{position:absolute;inset:0;background:rgba(15,23,42,.6);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;color:#fff;font-size:11px;font-weight:600;opacity:0;transition:opacity .2s}
.avatar-display:hover .avatar-overlay{opacity:1}
.form-help{font-size:11px;color:var(--text-mute);margin-top:6px}

.input-group{position:relative}
.input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-mute);pointer-events:none}
.input-group .form-control{padding-left:42px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media (max-width:520px){.form-row{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="layout">
<?= panel_sidebar('user', 'profile') ?>
<main class="main">

<div class="page-header-modern">
  <div>
    <div class="page-eyebrow"><?= icon('user', 12) ?> <?= lang()==='uz_cyrillic' ? "Шахсий" : "Shaxsiy" ?></div>
    <h1><?= t('profile') ?></h1>
    <div class="page-subtitle"><?= lang()==='uz_cyrillic' ? "Шахсий маълумотларингизни бошқаринг" : "Shaxsiy ma'lumotlaringizni boshqaring" ?></div>
  </div>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= icon('check-circle', 18) ?> <?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= icon('x-circle', 18) ?> <?= e($err) ?></div><?php endif; ?>

<div class="profile-banner">
  <div class="pb-inner">
    <div class="pb-avatar">
      <?php if (!empty($u['avatar'])): ?><img src="<?= e($u['avatar']) ?>" alt=""><?php else: ?><span class="pb-letter"><?= mb_substr($u['first_name'],0,1) ?></span><?php endif; ?>
    </div>
    <div class="pb-meta">
      <h2><?= e($u['first_name'].' '.$u['last_name']) ?></h2>
      <div class="pb-tags">
        <span class="badge"><?= e(t($u['role'])) ?></span>
        <span class="badge"><?= e(t($u['status'])) ?></span>
        <span style="color:rgba(255,255,255,.85);font-size:12px"><?= icon('calendar', 11) ?> <?= date('d.m.Y', strtotime($u['created_at'])) ?> dan beri</span>
      </div>
      <div class="pb-stats">
        <div><strong><?= $total_tests ?></strong><span>test</span></div>
        <div><strong><?= round($avg_score,1) ?>%</strong><span>o'rtacha</span></div>
        <div><strong><?= e($u['referral_code']) ?></strong><span>referal</span></div>
      </div>
    </div>
  </div>
</div>

<div class="dash-grid">

<div class="card">
  <h3><?= icon('edit', 16) ?> <?= t('personal_info') ?></h3>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="profile">

    <div class="avatar-section">
      <div class="avatar-display" onclick="document.getElementById('avatarInput').click()">
        <?php if (!empty($u['avatar'])): ?><img src="<?= e($u['avatar']) ?>" alt=""><?php else: ?><div class="avatar-letter-big"><?= mb_substr($u['first_name'],0,1) ?></div><?php endif; ?>
        <div class="avatar-overlay"><?= icon('upload', 22) ?><span><?= lang()==='uz_cyrillic' ? "Расм танлаш" : "Rasm tanlash" ?></span></div>
        <input type="file" name="avatar" accept="image/*" id="avatarInput" hidden onchange="this.form.submit()">
      </div>
      <div class="form-help">JPG/PNG/WebP, max 5MB</div>
    </div>

    <div class="form-row">
      <div class="form-group"><label class="form-label"><?= t('first_name') ?></label><input type="text" name="first_name" class="form-control" required value="<?= e($u['first_name']) ?>" maxlength="50"></div>
      <div class="form-group"><label class="form-label"><?= t('last_name') ?></label><input type="text" name="last_name" class="form-control" required value="<?= e($u['last_name']) ?>" maxlength="50"></div>
    </div>
    <div class="form-group"><label class="form-label"><?= t('email') ?></label>
      <div class="input-group"><span class="input-icon"><?= icon('mail', 16) ?></span><input type="email" name="email" class="form-control" value="<?= e($u['email']) ?>" maxlength="100"></div>
    </div>
    <div class="form-group"><label class="form-label"><?= t('phone') ?></label>
      <div class="input-group"><span class="input-icon"><?= icon('phone', 16) ?></span><input type="tel" name="phone" class="form-control" value="<?= e($u['phone']) ?>" maxlength="20"></div>
    </div>
    <div class="form-group"><label class="form-label"><?= icon('globe', 14) ?> <?= t('language_setting') ?></label>
      <select name="language" class="form-control">
        <option value="uz_latin" <?= $u['language']==='uz_latin'?'selected':'' ?>>O'zbek (Lotin)</option>
        <option value="uz_cyrillic" <?= $u['language']==='uz_cyrillic'?'selected':'' ?>>Ўзбек (Кирилл)</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary btn-block"><?= icon('check', 14) ?> <?= t('save') ?></button>
  </form>
</div>

<div class="card">
  <h3><?= icon('lock', 16) ?> <?= t('change_password') ?></h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <div class="form-group"><label class="form-label"><?= t('old_password') ?></label>
      <div class="input-group"><span class="input-icon"><?= icon('lock', 16) ?></span><input type="password" name="old_password" class="form-control" required></div>
    </div>
    <div class="form-group"><label class="form-label"><?= t('new_password') ?></label>
      <div class="input-group"><span class="input-icon"><?= icon('lock', 16) ?></span><input type="password" name="new_password" class="form-control" required minlength="8" data-strength="1"></div>
    </div>
    <div class="form-group"><label class="form-label"><?= t('confirm_password') ?></label>
      <div class="input-group"><span class="input-icon"><?= icon('lock', 16) ?></span><input type="password" name="new_password2" class="form-control" required minlength="8"></div>
    </div>
    <?= Security::password_strength_meter() ?>
    <button type="submit" class="btn btn-primary btn-block" style="margin-top:14px"><?= icon('check', 14) ?> <?= t('change_password') ?></button>
  </form>
</div>

</div>

</main>
</div>
<script><?= panel_js() ?></script>
</body></html>
