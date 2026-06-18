<?php
require_once __DIR__ . '/../includes/bootstrap.php';
auth_class();
require_admin();

// PRG flash messages
$msg = flash('msg');
$err = flash('err');

// =====================================
// POST handler — har doim redirect bilan
// =====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redir = $_SERVER['REQUEST_URI'];

    if (!csrf_check()) {
        flash('err', 'Xavfsizlik tokeni notog\'ri. Sahifani yangilang.');
        header("Location: $redir"); exit;
    }

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    try {
        if ($action === 'delete' && $id) {
            db()->execute("DELETE FROM users WHERE id=? AND role!='admin'", [$id]);
            audit('user_deleted', "User #$id", 'warning');
            flash('msg', t('deleted_success'));
        }
        elseif ($action === 'block' && $id) {
            db()->execute("UPDATE users SET status='blocked' WHERE id=?", [$id]);
            audit('user_blocked', "User #$id", 'warning');
            flash('msg', lang()==='uz_cyrillic' ? 'Блокланди' : 'Bloklandi');
        }
        elseif ($action === 'unblock' && $id) {
            db()->execute("UPDATE users SET status='active' WHERE id=?", [$id]);
            audit('user_unblocked', "User #$id");
            flash('msg', lang()==='uz_cyrillic' ? 'Фаоллаштирилди' : 'Faollashtirildi');
        }
        elseif ($action === 'add') {
            $first = Security::clean($_POST['first_name'] ?? '', 50);
            $last  = Security::clean($_POST['last_name'] ?? '', 50);
            $email = Security::clean($_POST['email'] ?? '', 100);
            $phone = Security::clean($_POST['phone'] ?? '', 20);
            $role  = in_array($_POST['role'] ?? '', ['user','admin','developer']) ? $_POST['role'] : 'user';
            $pass  = $_POST['password'] ?? 'changeme';

            if (!$first || !$last) throw new Exception(t('fill_required') . ': ism + familiya');
            if (!$email && !$phone) throw new Exception('Email yoki telefon kiriting');
            if ($email && !Security::valid_email($email)) throw new Exception(t('invalid_email'));
            if (strlen($pass) < 6) throw new Exception(t('password_min'));

            $exists = db()->fetch("SELECT id FROM users WHERE email = ? OR phone = ?", [$email ?: '__', $phone ?: '__']);
            if ($exists) throw new Exception(t('email_exists'));

            $ok = db()->execute(
                "INSERT INTO users (first_name,last_name,email,phone,password,role,status,referral_code)
                 VALUES (?,?,?,?,?,?, 'active', ?)",
                [$first, $last, $email ?: null, $phone ?: null,
                 password_hash($pass, PASSWORD_DEFAULT), $role,
                 strtoupper(substr(bin2hex(random_bytes(6)),0,8))]);

            if (!$ok) throw new Exception('DB xatosi');
            audit('user_added', "Email: $email, role: $role");
            flash('msg', lang()==='uz_cyrillic' ? 'Фойдаланувчи қўшилди' : 'Foydalanuvchi qo\'shildi');
        }
        elseif ($action === 'edit' && $id) {
            $first = Security::clean($_POST['first_name'] ?? '', 50);
            $last  = Security::clean($_POST['last_name'] ?? '', 50);
            $email = Security::clean($_POST['email'] ?? '', 100);
            $phone = Security::clean($_POST['phone'] ?? '', 20);
            $role  = in_array($_POST['role'] ?? '', ['user','admin','developer']) ? $_POST['role'] : 'user';

            if (!$first || !$last) throw new Exception(t('fill_required'));

            db()->execute(
                "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=? WHERE id=?",
                [$first, $last, $email ?: null, $phone ?: null, $role, $id]);
            audit('user_updated', "User #$id");
            flash('msg', t('updated_success'));
        }
        else {
            throw new Exception('Notog\'ri amal: '.$action);
        }
    } catch (Throwable $e) {
        flash('err', 'Xatolik: ' . $e->getMessage());
    }

    header("Location: $redir"); exit;
}

// =====================================
// Display
// =====================================
$search = trim($_GET['q'] ?? '');
$role_f = $_GET['role'] ?? '';

$where = "1=1"; $params = [];
if ($search) {
    $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
}
if ($role_f) { $where .= " AND role = ?"; $params[] = $role_f; }

$users = db()->fetchAll("SELECT * FROM users WHERE $where ORDER BY created_at DESC LIMIT 100", $params);

render_head(t('users'));
?>
<div class="layout">
<?= panel_sidebar('admin', 'users') ?>
<main class="main">

  <div class="page-header-modern">
    <div>
      <div class="page-eyebrow"><?= icon('users', 12) ?> <?= lang()==='uz_cyrillic' ? "Бошқарув" : "Boshqaruv" ?></div>
      <h1><?= t('users') ?></h1>
      <div class="page-subtitle">
        <?= lang()==='uz_cyrillic' ? "Барча фойдаланувчиларни бошқаринг" : "Barcha foydalanuvchilarni boshqaring" ?>
      </div>
    </div>
    <div class="page-toolbar">
      <button type="button" class="btn btn-light btn-sm" onclick='openUserModal({})'><?= icon('user-plus', 14) ?> <?= lang()==='uz_cyrillic' ? "Тез қўшиш" : "Tez qo'shish" ?></button>
      <a href="/admin/users-form.php" class="btn btn-primary btn-sm"><?= icon('plus', 14) ?> <?= t('add') ?></a>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= icon('check-circle', 18) ?> <?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= icon('x-circle', 18) ?> <?= e($err) ?></div><?php endif; ?>

  <div class="data-table">
    <div class="data-table-head">
      <div class="data-table-title">
        <?= icon('users', 16) ?>
        <?= lang()==='uz_cyrillic' ? "Барча фойдаланувчилар" : "Barcha foydalanuvchilar" ?>
        <span class="count-pill"><?= count($users) ?></span>
      </div>
      <form method="get" class="data-table-toolbar" data-no-loading>
        <div class="input-group">
          <span class="input-icon"><?= icon('search', 14) ?></span>
          <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="<?= lang()==='uz_cyrillic' ? "Қидирув..." : "Qidiruv..." ?>" style="padding-left:36px;min-width:200px">
        </div>
        <select name="role" class="form-control">
          <option value="">— <?= t('role') ?> —</option>
          <option value="user"      <?= $role_f==='user'?'selected':'' ?>>User</option>
          <option value="admin"     <?= $role_f==='admin'?'selected':'' ?>>Admin</option>
          <option value="developer" <?= $role_f==='developer'?'selected':'' ?>>Developer</option>
        </select>
        <button class="btn btn-primary btn-sm"><?= icon('filter', 14) ?></button>
        <?php if ($search || $role_f): ?>
          <a href="?" class="btn btn-light btn-sm"><?= icon('x', 14) ?></a>
        <?php endif; ?>
      </form>
    </div>

    <?php if (empty($users)): ?>
      <div class="empty-state-v2">
        <div class="empty-state-v2-icon"><?= icon('users', 32) ?></div>
        <h3><?= lang()==='uz_cyrillic' ? "Фойдаланувчилар топилмади" : "Foydalanuvchilar topilmadi" ?></h3>
        <p><?= lang()==='uz_cyrillic' ? "Қидирув шартларини ўзгартириб қайта уриниб кўринг" : "Qidiruv shartlarini o'zgartirib qayta urinib ko'ring" ?></p>
        <button type="button" class="btn btn-primary" onclick='openUserModal({})'><?= icon('plus', 14) ?> <?= t('add') ?></button>
      </div>
    <?php else: ?>
    <div class="data-table-body" style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th><?= t('name') ?></th>
            <th><?= lang()==='uz_cyrillic' ? "Алоқа" : "Aloqa" ?></th>
            <th><?= t('role') ?></th>
            <th><?= t('status') ?></th>
            <th><?= t('date') ?></th>
            <th style="text-align:right"><?= t('actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $row):
            $cls = $row['status']==='active'?'success':($row['status']==='blocked'?'danger':'warning');
            $role_cls = $row['role']==='admin'?'violet':($row['role']==='developer'?'cyan':'info');
          ?>
          <tr>
            <td>
              <div class="data-cell-user">
                <div class="data-cell-user-avatar">
                  <?php if (!empty($row['avatar'])): ?>
                    <img src="<?= e($row['avatar']) ?>" alt="">
                  <?php else: ?>
                    <?= mb_strtoupper(mb_substr($row['first_name'],0,1)) ?>
                  <?php endif; ?>
                </div>
                <div class="data-cell-user-info">
                  <div class="data-cell-user-name"><?= e($row['first_name'].' '.$row['last_name']) ?></div>
                  <div class="data-cell-user-meta">#<?= $row['id'] ?> · <?= e($row['referral_code'] ?? '') ?></div>
                </div>
              </div>
            </td>
            <td>
              <?php if ($row['email']): ?><div class="data-cell-mono"><?= e($row['email']) ?></div><?php endif; ?>
              <?php if ($row['phone']): ?><div class="data-cell-mono" style="font-size:12px;color:var(--text-mute)"><?= e($row['phone']) ?></div><?php endif; ?>
            </td>
            <td><span class="badge-soft <?= $role_cls ?>"><?= e($row['role']) ?></span></td>
            <td><span class="badge-soft <?= $cls ?>"><?= e($row['status']) ?></span></td>
            <td><span class="data-cell-mono"><?= date('d.m.Y', strtotime($row['created_at'])) ?></span></td>
            <td>
              <div class="data-cell-actions" style="justify-content:flex-end">
                <button type="button" class="btn btn-light btn-icon btn-sm"
                        onclick='openUserModal(<?= json_encode($row, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'
                        title="<?= lang()==='uz_cyrillic' ? "Таҳрирлаш" : "Tahrirlash" ?>"><?= icon('edit', 14) ?></button>
                <?php if ($row['status']==='active' && $row['role'] !== 'admin'): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('<?= lang()==='uz_cyrillic' ? "Блоклайсизми?" : "Bloklaysizmi?" ?>')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="block">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <button class="btn btn-light btn-icon btn-sm" style="color:var(--warning-dark)" title="<?= lang()==='uz_cyrillic' ? "Блоклаш" : "Bloklash" ?>"><?= icon('ban', 14) ?></button>
                </form>
                <?php elseif ($row['status']==='blocked'): ?>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="unblock">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <button class="btn btn-light btn-icon btn-sm" style="color:var(--success-dark)" title="<?= lang()==='uz_cyrillic' ? "Фаоллаштириш" : "Faollashtirish" ?>"><?= icon('check-circle', 14) ?></button>
                </form>
                <?php endif; ?>
                <?php if ($row['role'] !== 'admin'): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <button class="btn btn-light btn-icon btn-sm" style="color:var(--danger)" title="<?= lang()==='uz_cyrillic' ? "Ўчириш" : "O'chirish" ?>"><?= icon('trash', 14) ?></button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</main>
</div>

<div id="userModal" class="modal-backdrop">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3 class="modal-title" id="userModalTitle"><?= t('add') ?></h3>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="post" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="u_action" value="add">
      <input type="hidden" name="id" id="u_id">

      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label"><?= t('first_name') ?> <span style="color:var(--danger)">*</span></label>
            <input type="text" name="first_name" id="u_first" class="form-control" required maxlength="50">
          </div>
          <div class="form-group">
            <label class="form-label"><?= t('last_name') ?> <span style="color:var(--danger)">*</span></label>
            <input type="text" name="last_name" id="u_last" class="form-control" required maxlength="50">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label"><?= t('email') ?></label>
            <input type="email" name="email" id="u_email" class="form-control" maxlength="100">
          </div>
          <div class="form-group">
            <label class="form-label"><?= t('phone') ?></label>
            <input type="tel" name="phone" id="u_phone" class="form-control" maxlength="20">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group" id="u_pass_block">
            <label class="form-label"><?= t('password') ?></label>
            <input type="text" name="password" id="u_pass" class="form-control" value="changeme">
          </div>
          <div class="form-group">
            <label class="form-label"><?= t('role') ?></label>
            <select name="role" id="u_role" class="form-control">
              <option value="user">User</option>
              <option value="admin">Admin</option>
              <option value="developer">Developer</option>
            </select>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-modal-close><?= t('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> <?= t('save') ?></button>
      </div>
    </form>
  </div>
</div>

<script>
function openUserModal(u){
  const isEdit = !!u.id;
  document.getElementById('u_action').value = isEdit ? 'edit' : 'add';
  document.getElementById('u_id').value = u.id || '';
  document.getElementById('u_first').value = u.first_name || '';
  document.getElementById('u_last').value = u.last_name || '';
  document.getElementById('u_email').value = u.email || '';
  document.getElementById('u_phone').value = u.phone || '';
  document.getElementById('u_role').value = u.role || 'user';
  document.getElementById('u_pass_block').style.display = isEdit ? 'none' : '';
  document.getElementById('userModalTitle').textContent = isEdit ? '<?= t('edit') ?>' : '<?= t('add') ?>';
  openModal('userModal');
}
</script>
<script><?= panel_js() ?></script>
</body></html>
