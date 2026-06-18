<?php
/**
 * Foydalanuvchi qo'shish/tahrirlash formasi
 *
 * URL:
 *   /admin/users-form.php          → yangi qo'shish
 *   /admin/users-form.php?id=X     → tahrirlash
 */
require_once __DIR__ . '/../includes/bootstrap.php';
auth_class();
require_admin();

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$user = null;

if ($isEdit) {
    $user = db()->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$user) {
        flash('err', 'Foydalanuvchi topilmadi');
        header('Location: /admin/users.php'); exit;
    }
}

$msg = flash('msg');
$err = flash('err');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = $isEdit ? "/admin/users-form.php?id=$id" : "/admin/users-form.php";

    if (!csrf_check()) {
        flash('err', 'CSRF xatosi');
        header("Location: $back"); exit;
    }

    try {
        $first = Security::clean($_POST['first_name'] ?? '', 50);
        $last  = Security::clean($_POST['last_name'] ?? '', 50);
        $email = Security::clean($_POST['email'] ?? '', 100);
        $phone = Security::clean($_POST['phone'] ?? '', 20);
        $role  = in_array($_POST['role'] ?? '', ['user','admin','developer']) ? $_POST['role'] : 'user';
        $status = in_array($_POST['status'] ?? '', ['active','blocked','pending']) ? $_POST['status'] : 'active';
        $pass  = $_POST['password'] ?? '';

        if (!$first || !$last) throw new Exception('Ism va familiya majburiy');
        if (!$email && !$phone) throw new Exception('Email yoki telefon kiriting');
        if ($email && !Security::valid_email($email)) throw new Exception(t('invalid_email'));

        // Avatar upload
        $avatar = $user['avatar'] ?? null;
        if (!empty($_FILES['avatar']['name'])) {
            $up = Security::upload_image($_FILES['avatar'], 'avatar_'.($id ?: 'new'));
            if ($up['ok']) { $avatar = $up['url']; @chmod(BASE_PATH . $up['url'], 0644); }
            else throw new Exception('Avatar: '.$up['error']);
        }

        if ($isEdit) {
            // Edit
            $sql = "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=?, status=?, avatar=?";
            $params = [$first, $last, $email ?: null, $phone ?: null, $role, $status, $avatar];

            if ($pass) {
                if (strlen($pass) < 6) throw new Exception('Parol kamida 6 belgi');
                $sql .= ", password=?";
                $params[] = password_hash($pass, PASSWORD_DEFAULT);
            }

            $sql .= " WHERE id=?";
            $params[] = $id;

            db()->execute($sql, $params);
            audit('user_updated', "User #$id");
            flash('msg', t('updated_success'));
        } else {
            // Add
            if (!$pass || strlen($pass) < 6) throw new Exception('Parol kamida 6 belgi');

            $exists = db()->fetch("SELECT id FROM users WHERE email = ? OR phone = ?", [$email ?: '__', $phone ?: '__']);
            if ($exists) throw new Exception(t('email_exists'));

            db()->execute(
                "INSERT INTO users (first_name,last_name,email,phone,password,role,status,referral_code,avatar)
                 VALUES (?,?,?,?,?,?,?,?,?)",
                [$first, $last, $email ?: null, $phone ?: null,
                 password_hash($pass, PASSWORD_DEFAULT), $role, $status,
                 strtoupper(substr(bin2hex(random_bytes(6)),0,8)), $avatar]);
            audit('user_added', "Email: $email");
            flash('msg', t('saved_success'));
        }

        header("Location: /admin/users.php"); exit;
    } catch (Throwable $e) {
        flash('err', 'Xatolik: ' . $e->getMessage());
        header("Location: $back"); exit;
    }
}

render_head($isEdit ? t('edit') : t('add'));
?>
<div class="layout">
<?= panel_sidebar('admin', 'users') ?>
<main class="main">
  <div class="page-header">
    <div>
      <a href="/admin/users.php" class="text-soft" style="font-size:13px;display:inline-flex;align-items:center;gap:4px;text-decoration:none">
        <?= icon('arrow-left', 14) ?> <?= t('users') ?>
      </a>
      <div class="page-title mt-1"><?= icon('user', 28) ?> <?= $isEdit ? t('edit') : t('add') ?> <?= t('user') ?></div>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= icon('check-circle',18) ?> <?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= icon('x-circle',18) ?> <?= e($err) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" action="">
    <?= csrf_field() ?>

    <div class="card">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:18px"><?= icon('user', 18) ?> Asosiy ma'lumotlar</h3>

      <!-- Avatar -->
      <div class="form-group text-center mb-3">
        <label class="form-label">Avatar</label>
        <div class="avatar-up" id="avatarUp">
          <?php if (!empty($user['avatar'])): ?>
            <img src="<?= e($user['avatar']) ?>" id="avatarPrev">
          <?php else: ?>
            <div id="avatarPrev" class="av-letter"><?= e(mb_substr($user['first_name'] ?? 'U', 0, 1)) ?></div>
          <?php endif; ?>
          <div class="av-overlay"><?= icon('upload', 24) ?> <span>Tanlash</span></div>
          <input type="file" name="avatar" accept="image/*" id="avatarInput" hidden>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label"><?= t('first_name') ?> <span style="color:var(--danger)">*</span></label>
          <input type="text" name="first_name" class="form-control" required maxlength="50"
                 value="<?= e($_POST['first_name'] ?? $user['first_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label"><?= t('last_name') ?> <span style="color:var(--danger)">*</span></label>
          <input type="text" name="last_name" class="form-control" required maxlength="50"
                 value="<?= e($_POST['last_name'] ?? $user['last_name'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label"><?= t('email') ?></label>
          <div class="input-group">
            <span class="input-icon"><?= icon('mail', 16) ?></span>
            <input type="email" name="email" class="form-control" maxlength="100"
                   value="<?= e($_POST['email'] ?? $user['email'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label"><?= t('phone') ?></label>
          <div class="input-group">
            <span class="input-icon"><?= icon('phone', 16) ?></span>
            <input type="tel" name="phone" class="form-control" maxlength="20"
                   value="<?= e($_POST['phone'] ?? $user['phone'] ?? '') ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:18px"><?= icon('shield', 18) ?> Xavfsizlik va rol</h3>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">
            <?= t('password') ?>
            <?php if ($isEdit): ?><small class="text-mute">— bo'sh = o'zgartirilmaydi</small><?php endif; ?>
          </label>
          <div class="input-group">
            <span class="input-icon"><?= icon('lock', 16) ?></span>
            <input type="text" name="password" class="form-control" minlength="6"
                   <?= $isEdit ? '' : 'required value="changeme"' ?>>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label"><?= t('role') ?></label>
          <select name="role" class="form-control">
            <option value="user"      <?= ($user['role']??'')==='user'?'selected':'' ?>>User</option>
            <option value="admin"     <?= ($user['role']??'')==='admin'?'selected':'' ?>>Admin</option>
            <option value="developer" <?= ($user['role']??'')==='developer'?'selected':'' ?>>Developer</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label"><?= t('status') ?></label>
        <select name="status" class="form-control">
          <option value="active"  <?= ($user['status']??'active')==='active'?'selected':'' ?>>✓ Faol</option>
          <option value="blocked" <?= ($user['status']??'')==='blocked'?'selected':'' ?>>🚫 Bloklangan</option>
          <option value="pending" <?= ($user['status']??'')==='pending'?'selected':'' ?>>⏳ Kutilmoqda</option>
        </select>
      </div>
    </div>

    <div class="form-actions">
      <a href="/admin/users.php" class="btn btn-light"><?= t('cancel') ?></a>
      <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> <?= t('save') ?></button>
    </div>
  </form>
</main>
</div>

<style>
.form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:24px;padding:18px 0}
@media(max-width:640px){.form-actions{flex-direction:column-reverse}.form-actions .btn{width:100%}}

.avatar-up{position:relative;width:120px;height:120px;border-radius:50%;cursor:pointer;
  overflow:hidden;border:3px solid var(--border);margin:0 auto;transition:border-color .2s}
.avatar-up:hover{border-color:var(--primary)}
.avatar-up img,.avatar-up .av-letter{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:48px;font-weight:900;background:var(--primary-light);color:var(--primary);object-fit:cover}
.av-overlay{position:absolute;inset:0;background:rgba(15,23,42,.6);color:#fff;display:flex;flex-direction:column;
  align-items:center;justify-content:center;opacity:0;transition:opacity .2s;font-size:11px;font-weight:600;gap:4px}
.avatar-up:hover .av-overlay{opacity:1}
</style>

<script>
(function(){
  const up = document.getElementById('avatarUp');
  const inp = document.getElementById('avatarInput');
  const prev = document.getElementById('avatarPrev');
  if (!up) return;
  up.addEventListener('click', () => inp.click());
  inp.addEventListener('change', () => {
    if (inp.files[0]) {
      const r = new FileReader();
      r.onload = e => {
        if (prev.tagName === 'IMG') prev.src = e.target.result;
        else {
          const img = document.createElement('img');
          img.id = 'avatarPrev';
          img.src = e.target.result;
          prev.replaceWith(img);
        }
      };
      r.readAsDataURL(inp.files[0]);
    }
  });
})();
</script>
<script><?= panel_js() ?></script>
</body></html>
