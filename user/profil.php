<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$u = current_user();
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Profilni yangilash
    if ($action === 'profile') {
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name']  ?? '');
        $email = trim($_POST['email']      ?? '');
        $phone = trim($_POST['phone']      ?? '');
        $lang  = $_POST['language']        ?? 'uz_latin';

        // Avatar
        $avatar = $u['avatar'];
        if (!empty($_FILES['avatar']['name'])) {
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $name = 'avatar_' . $u['id'] . '_' . time() . '.' . $ext;
                if (@move_uploaded_file($_FILES['avatar']['tmp_name'], UPLOAD_PATH . '/' . $name)) {
                    $avatar = UPLOAD_URL . '/' . $name;
                }
            }
        }

        $ok = db()->execute(
            "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, language=?, avatar=? WHERE id=?",
            [$first, $last, $email ?: null, $phone ?: null, $lang, $avatar, $u['id']]
        );
        if ($ok) {
            $_SESSION['lang'] = $lang;
            $msg = lang()==='uz_cyrillic' ? 'Маълумотлар сақланди' : 'Ma\'lumotlar saqlandi';
            $u = db()->fetch("SELECT * FROM users WHERE id=?", [$u['id']]);
        } else {
            $err = 'Xatolik';
        }
    }

    // Parolni o'zgartirish
    if ($action === 'password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $new2= $_POST['new_password2'] ?? '';

        if (!password_verify($old, $u['password'])) {
            $err = lang()==='uz_cyrillic' ? 'Эски парол нотўғри' : 'Eski parol noto\'g\'ri';
        } elseif (strlen($new) < 6) {
            $err = lang()==='uz_cyrillic' ? "Янги парол камида 6 белги" : "Yangi parol kamida 6 belgi";
        } elseif ($new !== $new2) {
            $err = lang()==='uz_cyrillic' ? 'Парол мос келмади' : 'Parol mos kelmadi';
        } else {
            db()->execute("UPDATE users SET password=? WHERE id=?", [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            $msg = lang()==='uz_cyrillic' ? 'Парол ўзгартирилди' : 'Parol o\'zgartirildi';
        }
    }
}

render_head(t('profile'));
?>
<div class="layout">
<?php render_sidebar('user', 'profile'); ?>
<main class="main">
  <div class="page-header">
    <div class="page-title">👤 <?= t('profile') ?></div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

  <div class="grid-2">
    <!-- Profil -->
    <div class="card">
      <h3 style="font-size:18px;font-weight:700;margin-bottom:18px"><?= lang()==='uz_cyrillic' ? 'Шахсий маълумотлар' : 'Shaxsiy ma\'lumotlar' ?></h3>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="profile">

        <!-- Avatar -->
        <div class="text-center mb-3">
          <?php if (!empty($u['avatar'])): ?>
            <img src="<?= e($u['avatar']) ?>" style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:4px solid var(--primary-light)">
          <?php else: ?>
            <div style="width:120px;height:120px;border-radius:50%;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:48px;font-weight:800;margin:0 auto"><?= mb_substr($u['first_name'],0,1) ?></div>
          <?php endif; ?>
          <div class="form-group" style="margin-top:14px">
            <input type="file" name="avatar" class="form-control" accept="image/*">
          </div>
        </div>

        <div class="grid-2" style="gap:14px">
          <div class="form-group">
            <label class="form-label"><?= t('first_name') ?></label>
            <input type="text" name="first_name" class="form-control" required value="<?= e($u['first_name']) ?>">
          </div>
          <div class="form-group">
            <label class="form-label"><?= t('last_name') ?></label>
            <input type="text" name="last_name" class="form-control" required value="<?= e($u['last_name']) ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label"><?= t('email') ?></label>
          <input type="email" name="email" class="form-control" value="<?= e($u['email']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label"><?= t('phone') ?></label>
          <input type="tel" name="phone" class="form-control" value="<?= e($u['phone']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label"><?= lang()==='uz_cyrillic' ? 'Тил' : 'Til' ?></label>
          <select name="language" class="form-control">
            <option value="uz_latin"    <?= $u['language']==='uz_latin'?'selected':'' ?>>O'zbek (Lotin)</option>
            <option value="uz_cyrillic" <?= $u['language']==='uz_cyrillic'?'selected':'' ?>>Ўзбек (Кирилл)</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><?= t('save') ?></button>
      </form>
    </div>

    <!-- Parol -->
    <div>
      <div class="card mb-3">
        <h3 style="font-size:18px;font-weight:700;margin-bottom:18px"><?= lang()==='uz_cyrillic' ? 'Паролни ўзгартириш' : 'Parolni o\'zgartirish' ?></h3>
        <form method="post">
          <input type="hidden" name="action" value="password">
          <div class="form-group">
            <label class="form-label"><?= lang()==='uz_cyrillic' ? 'Эски парол' : 'Eski parol' ?></label>
            <input type="password" name="old_password" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label"><?= lang()==='uz_cyrillic' ? 'Янги парол' : 'Yangi parol' ?></label>
            <input type="password" name="new_password" class="form-control" required minlength="6">
          </div>
          <div class="form-group">
            <label class="form-label"><?= lang()==='uz_cyrillic' ? 'Янги паролни тасдиқланг' : 'Yangi parolni tasdiqlang' ?></label>
            <input type="password" name="new_password2" class="form-control" required minlength="6">
          </div>
          <button type="submit" class="btn btn-primary btn-block"><?= lang()==='uz_cyrillic' ? 'Паролни ўзгартириш' : 'Parolni o\'zgartirish' ?></button>
        </form>
      </div>

      <!-- Akkaunt info -->
      <div class="card">
        <h3 style="font-size:18px;font-weight:700;margin-bottom:18px"><?= lang()==='uz_cyrillic' ? 'Аккаунт' : 'Akkaunt' ?></h3>
        <div class="flex justify-between mb-1" style="padding:8px 0;border-bottom:1px solid var(--border)">
          <span style="color:var(--text-soft)">ID</span><strong>#<?= $u['id'] ?></strong>
        </div>
        <div class="flex justify-between mb-1" style="padding:8px 0;border-bottom:1px solid var(--border)">
          <span style="color:var(--text-soft)"><?= t('role') ?></span><span class="badge badge-info"><?= e($u['role']) ?></span>
        </div>
        <div class="flex justify-between mb-1" style="padding:8px 0;border-bottom:1px solid var(--border)">
          <span style="color:var(--text-soft)"><?= lang()==='uz_cyrillic' ? 'Реферал код' : 'Referral kod' ?></span><strong><?= e($u['referral_code']) ?></strong>
        </div>
        <div class="flex justify-between" style="padding:8px 0">
          <span style="color:var(--text-soft)"><?= lang()==='uz_cyrillic' ? 'Рўйхатдан ўтган' : 'Ro\'yxatdan o\'tgan' ?></span><strong><?= date('d.m.Y', strtotime($u['created_at'])) ?></strong>
        </div>
      </div>
    </div>
  </div>
</main>
</div>
</body></html>
