<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$u = current_user();
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = t('csrf_invalid');
    } else {
        $action = $_POST['action'] ?? '';

        // Profilni yangilash
        if ($action === 'profile') {
            $first = Security::clean($_POST['first_name'] ?? '', 50);
            $last  = Security::clean($_POST['last_name']  ?? '', 50);
            $email = Security::clean($_POST['email']      ?? '', 100);
            $phone = Security::clean($_POST['phone']      ?? '', 20);
            $lang  = in_array($_POST['language'] ?? '', ['uz_latin','uz_cyrillic']) ? $_POST['language'] : 'uz_latin';

            // Avatar
            $avatar = $u['avatar'];
            if (!empty($_FILES['avatar']['name'])) {
                $up = Security::upload_image($_FILES['avatar'], 'avatar_' . $u['id']);
                if ($up['ok']) {
                    $avatar = $up['url'];
                    @chmod(BASE_PATH . $up['url'], 0644);
                } else {
                    $err = $up['error'];
                }
            }

            if (!$err) {
                if ($email && !Security::valid_email($email)) {
                    $err = t('invalid_email');
                } elseif ($phone && !Security::valid_phone($phone)) {
                    $err = t('invalid_phone');
                } else {
                    db()->execute(
                        "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, language=?, avatar=? WHERE id=?",
                        [$first, $last, $email ?: null, $phone ?: null, $lang, $avatar, $u['id']]);
                    $_SESSION['lang'] = $lang;
                    $msg = t('saved_success');
                    $u = db()->fetch("SELECT * FROM users WHERE id=?", [$u['id']]);
                }
            }
        }

        // Parolni o'zgartirish
        if ($action === 'password') {
            $r = Auth::change_password((int)$u['id'],
                $_POST['old_password'] ?? '',
                $_POST['new_password'] ?? '',
                $_POST['new_password2'] ?? '');
            if ($r['ok']) $msg = $r['msg'];
            else $err = $r['msg'];
        }
    }
}

// Statistika (profil banner uchun)
$total_tests = (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts WHERE user_id=? AND status='completed'", [$u['id']])['c'] ?? 0);
$avg_score = (float)(db()->fetch("SELECT COALESCE(AVG(score_percent),0) c FROM test_attempts WHERE user_id=? AND status='completed'", [$u['id']])['c'] ?? 0);

render_head(t('profile'));
?>
<div class="layout">
<?php render_sidebar('user', 'profile'); ?>
<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title"><?= icon('user', 28) ?> <?= t('profile') ?></div>
      <div class="page-subtitle"><?= lang()==='uz_cyrillic' ? "Шахсий маълумотларингизни бошқаринг" : "Shaxsiy ma'lumotlaringizni boshqaring" ?></div>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= icon('check-circle', 18) ?> <?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= icon('x-circle', 18) ?> <?= e($err) ?></div><?php endif; ?>

  <!-- Profile banner -->
  <div class="profile-banner mb-3">
    <div class="profile-banner-inner">
      <div class="profile-avatar-wrap">
        <?php if (!empty($u['avatar'])): ?>
          <img src="<?= e($u['avatar']) ?>" alt="" class="profile-avatar-img">
        <?php else: ?>
          <div class="profile-avatar-letter"><?= mb_substr($u['first_name'],0,1) ?></div>
        <?php endif; ?>
      </div>
      <div class="profile-meta">
        <h2><?= e($u['first_name'].' '.$u['last_name']) ?></h2>
        <div class="profile-tags">
          <span class="badge badge-info"><?= e(t($u['role'])) ?></span>
          <span class="badge badge-success"><?= e(t($u['status'])) ?></span>
          <span class="text-soft" style="font-size:13px"><?= icon('calendar', 12) ?> <?= date('d.m.Y', strtotime($u['created_at'])) ?> dan beri</span>
        </div>
        <div class="profile-quickstats">
          <div><strong><?= $total_tests ?></strong> <span class="text-soft">test</span></div>
          <div><strong><?= round($avg_score,1) ?>%</strong> <span class="text-soft">o'rtacha</span></div>
          <div><strong><?= e($u['referral_code']) ?></strong> <span class="text-soft">referal</span></div>
        </div>
      </div>
    </div>
  </div>

  <div class="grid-2">
    <!-- Profil ma'lumotlari -->
    <div class="card">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:10px">
        <?= icon('edit', 20) ?> <?= t('personal_info') ?>
      </h3>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="profile">

        <!-- Avatar uploader -->
        <div class="avatar-uploader-section">
          <div class="avatar-display" id="avatarDisplay">
            <?php if (!empty($u['avatar'])): ?>
              <img src="<?= e($u['avatar']) ?>" alt="" id="avatarImg">
            <?php else: ?>
              <div class="avatar-letter-big" id="avatarLetter"><?= mb_substr($u['first_name'],0,1) ?></div>
            <?php endif; ?>
            <div class="avatar-overlay">
              <?= icon('upload', 24) ?>
              <span><?= lang()==='uz_cyrillic' ? "Расм танлаш" : "Rasm tanlash" ?></span>
            </div>
            <input type="file" name="avatar" accept="image/*" id="avatarInput" hidden>
          </div>
          <div class="form-help text-center mt-1"><?= lang()==='uz_cyrillic' ? "JPG/PNG/WEBP, макс 5MB" : "JPG/PNG/WEBP, max 5MB" ?></div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label"><?= t('first_name') ?></label>
            <input type="text" name="first_name" class="form-control" required value="<?= e($u['first_name']) ?>" maxlength="50">
          </div>
          <div class="form-group">
            <label class="form-label"><?= t('last_name') ?></label>
            <input type="text" name="last_name" class="form-control" required value="<?= e($u['last_name']) ?>" maxlength="50">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label"><?= t('email') ?></label>
          <div class="input-group">
            <span class="input-icon"><?= icon('mail', 16) ?></span>
            <input type="email" name="email" class="form-control" value="<?= e($u['email']) ?>" maxlength="100">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label"><?= t('phone') ?></label>
          <div class="input-group">
            <span class="input-icon"><?= icon('phone', 16) ?></span>
            <input type="tel" name="phone" class="form-control" value="<?= e($u['phone']) ?>" maxlength="20">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label"><?= icon('globe', 14) ?> <?= t('language_setting') ?></label>
          <select name="language" class="form-control">
            <option value="uz_latin"    <?= $u['language']==='uz_latin'?'selected':'' ?>>O'zbek (Lotin)</option>
            <option value="uz_cyrillic" <?= $u['language']==='uz_cyrillic'?'selected':'' ?>>Ўзбек (Кирилл)</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">
          <?= icon('check', 16) ?> <?= t('save') ?>
        </button>
      </form>
    </div>

    <div>
      <!-- Parol -->
      <div class="card mb-3">
        <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:10px">
          <?= icon('lock', 20) ?> <?= t('change_password') ?>
        </h3>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="password">
          <div class="form-group">
            <label class="form-label"><?= t('old_password') ?></label>
            <div class="input-group">
              <span class="input-icon"><?= icon('lock', 16) ?></span>
              <input type="password" name="old_password" class="form-control" required id="opwd">
              <button type="button" class="input-action" data-toggle-password="opwd"><?= icon('eye', 16) ?></button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label"><?= t('new_password') ?></label>
            <div class="input-group">
              <span class="input-icon"><?= icon('lock', 16) ?></span>
              <input type="password" name="new_password" class="form-control" required minlength="8" id="npwd" data-strength="1">
              <button type="button" class="input-action" data-toggle-password="npwd"><?= icon('eye', 16) ?></button>
            </div>
            <?= Security::password_strength_meter() ?>
          </div>
          <div class="form-group">
            <label class="form-label"><?= t('password2') ?></label>
            <div class="input-group">
              <span class="input-icon"><?= icon('lock', 16) ?></span>
              <input type="password" name="new_password2" class="form-control" required minlength="8" id="npwd2">
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-block">
            <?= icon('check', 16) ?> <?= t('save') ?>
          </button>
        </form>
      </div>

      <!-- Akkaunt info -->
      <div class="card">
        <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:10px">
          <?= icon('shield', 20) ?> <?= t('account') ?>
        </h3>
        <div class="profile-info-list">
          <div class="profile-info-row">
            <span class="text-soft">ID</span>
            <strong>#<?= $u['id'] ?></strong>
          </div>
          <div class="profile-info-row">
            <span class="text-soft"><?= t('role') ?></span>
            <span class="badge badge-info"><?= e($u['role']) ?></span>
          </div>
          <div class="profile-info-row">
            <span class="text-soft">Telegram</span>
            <?php if ($u['telegram_id']): ?>
              <span class="badge badge-success"><?= icon('check', 12) ?> Bog'langan</span>
            <?php else: ?>
              <a href="<?= e(setting('telegram_url','#')) ?>" target="_blank" class="badge badge-warning"><?= icon('telegram', 12) ?> Bog'lash</a>
            <?php endif; ?>
          </div>
          <div class="profile-info-row">
            <span class="text-soft"><?= t('referral_code') ?></span>
            <code style="background:var(--primary-light);color:var(--primary-dark);padding:4px 10px;border-radius:6px;font-weight:700"><?= e($u['referral_code']) ?></code>
          </div>
          <div class="profile-info-row">
            <span class="text-soft"><?= t('registered_at') ?></span>
            <strong><?= date('d.m.Y H:i', strtotime($u['created_at'])) ?></strong>
          </div>
          <?php if ($u['last_login']): ?>
          <div class="profile-info-row">
            <span class="text-soft">So'nggi kirish</span>
            <strong><?= date('d.m.Y H:i', strtotime($u['last_login'])) ?></strong>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>
</div>

<style>
/* Profile banner */
.profile-banner{background:linear-gradient(135deg,var(--primary),var(--secondary));
  border-radius:var(--r-2xl);padding:32px;color:#fff;position:relative;overflow:hidden;
  box-shadow:0 16px 40px rgba(59,130,246,.25)}
.profile-banner::before{content:'';position:absolute;top:-30%;right:-10%;width:300px;height:300px;
  background:radial-gradient(circle,rgba(255,255,255,.15),transparent 70%);border-radius:50%}
.profile-banner-inner{display:flex;align-items:center;gap:24px;position:relative;z-index:1;flex-wrap:wrap}
.profile-avatar-wrap{flex-shrink:0;width:120px;height:120px;border-radius:50%;border:5px solid rgba(255,255,255,.3);overflow:hidden;background:rgba(255,255,255,.1)}
.profile-avatar-img{width:100%;height:100%;object-fit:cover}
.profile-avatar-letter{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:48px;font-weight:900}
.profile-meta{flex:1;min-width:200px}
.profile-meta h2{font-size:28px;font-weight:800;color:#fff;margin-bottom:8px}
.profile-tags{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;align-items:center;color:rgba(255,255,255,.85)}
.profile-tags .badge{background:rgba(255,255,255,.2);color:#fff;backdrop-filter:blur(10px)}
.profile-quickstats{display:flex;gap:24px;flex-wrap:wrap}
.profile-quickstats > div{font-size:14px}
.profile-quickstats strong{font-size:20px;display:block;color:#fff}
.profile-quickstats .text-soft{color:rgba(255,255,255,.75);font-size:12px}

/* Avatar uploader */
.avatar-uploader-section{margin-bottom:24px;display:flex;flex-direction:column;align-items:center}
.avatar-display{position:relative;width:140px;height:140px;border-radius:50%;cursor:pointer;
  overflow:hidden;border:3px solid var(--border);transition:all .3s var(--ease-soft)}
.avatar-display:hover{border-color:var(--primary);transform:scale(1.03)}
.avatar-display img{width:100%;height:100%;object-fit:cover}
.avatar-letter-big{width:100%;height:100%;display:flex;align-items:center;justify-content:center;
  font-size:60px;font-weight:900;background:var(--primary-light);color:var(--primary)}
.avatar-overlay{position:absolute;inset:0;background:rgba(15,23,42,.6);color:#fff;
  display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;
  opacity:0;transition:opacity .25s;font-size:12px;font-weight:600}
.avatar-display:hover .avatar-overlay{opacity:1}

/* Profile info list */
.profile-info-list{display:flex;flex-direction:column;gap:0}
.profile-info-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border);font-size:14px}
.profile-info-row:last-child{border-bottom:none}
.profile-info-row .text-soft{font-size:13px}

@media(max-width:520px){
  .profile-banner{padding:24px 20px}
  .profile-banner-inner{flex-direction:column;text-align:center;gap:16px}
  .profile-avatar-wrap{width:96px;height:96px}
  .profile-avatar-letter{font-size:36px}
  .profile-meta h2{font-size:22px}
  .profile-quickstats{justify-content:center}
}
</style>

<script>
// Avatar live preview
(function(){
  const display = document.getElementById('avatarDisplay');
  const input = document.getElementById('avatarInput');
  if (!display || !input) return;
  display.addEventListener('click', () => input.click());
  input.addEventListener('change', () => {
    if (input.files && input.files[0]) {
      const r = new FileReader();
      r.onload = e => {
        const img = display.querySelector('img');
        if (img) {
          img.src = e.target.result;
        } else {
          // Letter ni rasm bilan almashtiramiz
          const letter = display.querySelector('.avatar-letter-big');
          if (letter) {
            const newImg = document.createElement('img');
            newImg.src = e.target.result;
            newImg.alt = '';
            letter.replaceWith(newImg);
          }
        }
      };
      r.readAsDataURL(input.files[0]);
    }
  });
})();

// Password match
(function(){
  const p1 = document.getElementById('npwd');
  const p2 = document.getElementById('npwd2');
  if (!p1 || !p2) return;
  function check(){
    p2.style.borderColor = (p2.value && p1.value !== p2.value) ? 'var(--danger)' : '';
  }
  p1.addEventListener('input', check);
  p2.addEventListener('input', check);
})();
</script>
</body></html>
