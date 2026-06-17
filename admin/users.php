<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$msg = ''; $err = '';

// Action lar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = t('csrf_invalid');
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int)($_POST['id'] ?? 0);

        if ($action === 'delete' && $id) {
            db()->execute("DELETE FROM users WHERE id=? AND role!='admin'", [$id]);
            audit('user_deleted', "User #$id", 'warning');
            $msg = t('deleted_success');
        }
        if ($action === 'block' && $id) {
            db()->execute("UPDATE users SET status='blocked' WHERE id=?", [$id]);
            audit('user_blocked', "User #$id", 'warning');
            $msg = lang()==='uz_cyrillic' ? 'Блокланди' : 'Bloklandi';
        }
        if ($action === 'unblock' && $id) {
            db()->execute("UPDATE users SET status='active' WHERE id=?", [$id]);
            audit('user_unblocked', "User #$id");
            $msg = lang()==='uz_cyrillic' ? 'Фаоллаштирилди' : 'Faollashtirildi';
        }
        if ($action === 'add') {
            $first = Security::clean($_POST['first_name'] ?? '', 50);
            $last  = Security::clean($_POST['last_name'] ?? '', 50);
            $email = Security::clean($_POST['email'] ?? '', 100);
            $phone = Security::clean($_POST['phone'] ?? '', 20);
            $role  = in_array($_POST['role'] ?? '', ['user','admin','developer']) ? $_POST['role'] : 'user';
            $pass  = $_POST['password'] ?? 'changeme';

            if (!$first || !$last || (!$email && !$phone)) {
                $err = t('fill_required');
            } elseif ($email && !Security::valid_email($email)) {
                $err = t('invalid_email');
            } elseif (strlen($pass) < 6) {
                $err = t('password_min');
            } else {
                $exists = db()->fetch("SELECT id FROM users WHERE email = ? OR phone = ?", [$email ?: null, $phone ?: null]);
                if ($exists) {
                    $err = t('email_exists');
                } else {
                    db()->execute(
                        "INSERT INTO users (first_name,last_name,email,phone,password,role,status,referral_code)
                         VALUES (?,?,?,?,?,?, 'active', ?)",
                        [$first, $last, $email ?: null, $phone ?: null,
                         password_hash($pass, PASSWORD_DEFAULT), $role,
                         strtoupper(substr(bin2hex(random_bytes(6)),0,8))]);
                    audit('user_added', "Email: $email, role: $role");
                    $msg = lang()==='uz_cyrillic' ? 'Фойдаланувчи қўшилди' : 'Foydalanuvchi qo\'shildi';
                }
            }
        }
        if ($action === 'edit' && $id) {
            $first = Security::clean($_POST['first_name'] ?? '', 50);
            $last  = Security::clean($_POST['last_name'] ?? '', 50);
            $email = Security::clean($_POST['email'] ?? '', 100);
            $phone = Security::clean($_POST['phone'] ?? '', 20);
            $role  = in_array($_POST['role'] ?? '', ['user','admin','developer']) ? $_POST['role'] : 'user';

            if (!$first || !$last) {
                $err = t('fill_required');
            } else {
                db()->execute(
                    "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=? WHERE id=?",
                    [$first, $last, $email ?: null, $phone ?: null, $role, $id]);
                audit('user_updated', "User #$id");
                $msg = t('updated_success');
            }
        }
    }
}

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
<?php render_sidebar('admin','users'); ?>
<main class="main">
  <div class="page-header">
    <div class="page-title"><?= icon('users', 28) ?> <?= t('users') ?></div>
    <button class="btn btn-primary" onclick='openUserModal({})'>
      <?= icon('plus', 16) ?> <?= t('add') ?>
    </button>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= icon('check-circle', 18) ?> <?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= icon('x-circle', 18) ?> <?= e($err) ?></div><?php endif; ?>

  <!-- Filter -->
  <form method="get" class="card mb-3" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
    <div class="form-group flex-1" style="min-width:200px;margin-bottom:0">
      <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="<?= t('search') ?>...">
    </div>
    <div class="form-group" style="margin-bottom:0;min-width:140px">
      <select name="role" class="form-control">
        <option value="">— <?= t('role') ?> —</option>
        <option value="user"      <?= $role_f==='user'?'selected':'' ?>>User</option>
        <option value="admin"     <?= $role_f==='admin'?'selected':'' ?>>Admin</option>
        <option value="developer" <?= $role_f==='developer'?'selected':'' ?>>Developer</option>
      </select>
    </div>
    <button class="btn btn-primary"><?= icon('search', 14) ?> <?= t('search') ?></button>
    <?php if ($search || $role_f): ?>
      <a href="?" class="btn btn-ghost"><?= icon('x', 14) ?></a>
    <?php endif; ?>
  </form>

  <!-- Users list -->
  <div class="card" style="padding:0">
    <div class="table-wrap table-flat table-responsive">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th><?= t('name') ?></th>
            <th><?= t('email') ?></th>
            <th><?= t('phone') ?></th>
            <th><?= t('role') ?></th>
            <th><?= t('status') ?></th>
            <th><?= t('date') ?></th>
            <th><?= t('actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>#<?= $u['id'] ?></td>
            <td>
              <div class="flex items-center" style="gap:10px">
                <?php if (!empty($u['avatar'])): ?>
                  <img src="<?= e($u['avatar']) ?>" style="width:34px;height:34px;border-radius:50%;object-fit:cover">
                <?php else: ?>
                  <div class="review-avatar" style="width:34px;height:34px;font-size:13px"><?= mb_substr($u['first_name'],0,1) ?></div>
                <?php endif; ?>
                <div><strong><?= e($u['first_name'].' '.$u['last_name']) ?></strong></div>
              </div>
            </td>
            <td><?= e($u['email']) ?></td>
            <td><?= e($u['phone']) ?></td>
            <td><span class="badge badge-info"><?= e($u['role']) ?></span></td>
            <td>
              <?php $cls = $u['status']==='active'?'success':($u['status']==='blocked'?'danger':'warning'); ?>
              <span class="badge badge-<?= $cls ?>"><?= e($u['status']) ?></span>
            </td>
            <td style="white-space:nowrap"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
            <td>
              <div class="flex" style="gap:4px;flex-wrap:nowrap">
                <button class="btn btn-light btn-sm" title="Tahrirlash"
                        onclick='openUserModal(<?= json_encode($u, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                  <?= icon('edit', 12) ?>
                </button>

                <?php if ($u['status']==='active' && $u['role'] !== 'admin'): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Bloklaymi?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="block">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button class="btn btn-light btn-sm" style="color:var(--warning-dark)" title="Bloklash">
                    <?= icon('lock', 12) ?>
                  </button>
                </form>
                <?php elseif ($u['status']==='blocked'): ?>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="unblock">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button class="btn btn-light btn-sm" style="color:var(--success-dark)" title="Faollashtirish">
                    <?= icon('unlock', 12) ?>
                  </button>
                </form>
                <?php endif; ?>

                <?php if ($u['role'] !== 'admin'): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button class="btn btn-light btn-sm" style="color:var(--danger)" title="O'chirish">
                    <?= icon('trash', 12) ?>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($users)): ?>
            <tr><td colspan="8" class="text-center" style="padding:40px;color:var(--text-soft)"><?= lang()==='uz_cyrillic' ? 'Топилмади' : 'Topilmadi' ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>

<!-- User modal -->
<div id="userModal" class="modal-backdrop">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3 class="modal-title" id="userModalTitle"><?= t('add') ?></h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="post">
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
            <div class="form-help">Faqat yangi user uchun</div>
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
        <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
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
</body></html>
