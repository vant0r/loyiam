<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$msg = '';

// Action lar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        db()->execute("DELETE FROM users WHERE id=? AND role!='admin'", [$id]);
        $msg = lang()==='uz_cyrillic' ? 'Фойдаланувчи ўчирилди' : 'Foydalanuvchi o\'chirildi';
    }
    if ($action === 'block' && $id) {
        db()->execute("UPDATE users SET status='blocked' WHERE id=?", [$id]);
        $msg = 'Bloklandi';
    }
    if ($action === 'unblock' && $id) {
        db()->execute("UPDATE users SET status='active' WHERE id=?", [$id]);
        $msg = 'Faollashtirildi';
    }
    if ($action === 'add') {
        $first = trim($_POST['first_name']);
        $last  = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $role  = in_array($_POST['role'] ?? '', ['user','admin','developer']) ? $_POST['role'] : 'user';
        $pass  = $_POST['password'] ?? 'changeme';
        if ($first && $last && ($email || $phone)) {
            db()->execute("INSERT INTO users (first_name,last_name,email,phone,password,role,status,referral_code)
              VALUES (?,?,?,?,?,?, 'active', ?)",
              [$first, $last, $email ?: null, $phone ?: null, password_hash($pass, PASSWORD_DEFAULT), $role,
               strtoupper(substr(md5(uniqid('',true)),0,8))]);
            $msg = lang()==='uz_cyrillic' ? 'Фойдаланувчи қўшилди' : 'Foydalanuvchi qo\'shildi';
        }
    }
    if ($action === 'edit' && $id) {
        db()->execute("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=? WHERE id=?",
            [trim($_POST['first_name']), trim($_POST['last_name']),
             trim($_POST['email']) ?: null, trim($_POST['phone']) ?: null,
             $_POST['role'] ?? 'user', $id]);
        $msg = lang()==='uz_cyrillic' ? 'Янгиланди' : 'Yangilandi';
    }
}

$search = trim($_GET['q'] ?? '');
$role_f = $_GET['role'] ?? '';

$where = "WHERE 1=1"; $params = [];
if ($search) {
    $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
}
if ($role_f) { $where .= " AND role = ?"; $params[] = $role_f; }

$users = db()->fetchAll("SELECT * FROM users $where ORDER BY created_at DESC LIMIT 100", $params);

render_head(t('users'));
?>
<div class="layout">
<?php render_sidebar('admin','users'); ?>
<main class="main">
  <div class="page-header">
    <div class="page-title">👥 <?= t('users') ?></div>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">+ <?= t('add') ?></button>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <!-- Filter -->
  <form method="get" class="card mb-3" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
    <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0">
      <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="<?= t('search') ?>...">
    </div>
    <div class="form-group" style="margin-bottom:0;min-width:140px">
      <select name="role" class="form-control">
        <option value="">— Rol —</option>
        <option value="user"      <?= $role_f==='user'?'selected':'' ?>>User</option>
        <option value="admin"     <?= $role_f==='admin'?'selected':'' ?>>Admin</option>
        <option value="developer" <?= $role_f==='developer'?'selected':'' ?>>Developer</option>
      </select>
    </div>
    <button class="btn btn-primary"><?= t('search') ?></button>
  </form>

  <!-- List -->
  <div class="card" style="padding:0">
    <div class="table-wrap" style="border:none;box-shadow:none">
      <table>
        <thead><tr><th>#</th><th><?= t('name') ?></th><th><?= t('email') ?></th><th><?= t('phone') ?></th><th><?= t('role') ?></th><th><?= t('status') ?></th><th><?= t('date') ?></th><th><?= t('actions') ?></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>#<?= $u['id'] ?></td>
            <td>
              <div class="flex items-center" style="gap:10px">
                <div class="review-avatar" style="width:34px;height:34px;font-size:13px"><?= mb_substr($u['first_name'],0,1) ?></div>
                <div><strong><?= e($u['first_name'].' '.$u['last_name']) ?></strong></div>
              </div>
            </td>
            <td><?= e($u['email']) ?></td>
            <td><?= e($u['phone']) ?></td>
            <td><span class="badge badge-info"><?= e($u['role']) ?></span></td>
            <td>
              <?php
                $cls = $u['status']==='active'?'success':($u['status']==='blocked'?'danger':'warning');
              ?>
              <span class="badge badge-<?= $cls ?>"><?= e($u['status']) ?></span>
            </td>
            <td><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
            <td>
              <div class="flex" style="gap:4px">
                <button class="btn btn-light btn-sm" onclick='editUser(<?= json_encode($u) ?>)'>✎</button>
                <?php if ($u['status']==='active'): ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('Blokirovka qilasizmi?')">
                    <input type="hidden" name="action" value="block"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <button class="btn btn-light btn-sm" style="color:var(--warning)">🚫</button>
                  </form>
                <?php else: ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="unblock"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <button class="btn btn-light btn-sm" style="color:var(--success)">✓</button>
                  </form>
                <?php endif; ?>
                <?php if ($u['role'] !== 'admin'): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('O\'chirilsinmi?')">
                  <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button class="btn btn-light btn-sm" style="color:var(--danger)">🗑</button>
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

<!-- Add modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:300;align-items:center;justify-content:center;padding:20px">
  <div class="card" style="max-width:520px;width:100%">
    <div class="flex justify-between items-center mb-3">
      <h3 style="font-size:18px;font-weight:700"><?= t('add') ?></h3>
      <button onclick="document.getElementById('addModal').style.display='none'" style="font-size:24px">×</button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="add">
      <div class="grid-2" style="gap:14px">
        <div class="form-group"><label class="form-label"><?= t('first_name') ?></label><input type="text" name="first_name" class="form-control" required></div>
        <div class="form-group"><label class="form-label"><?= t('last_name') ?></label><input type="text" name="last_name" class="form-control" required></div>
      </div>
      <div class="form-group"><label class="form-label"><?= t('email') ?></label><input type="email" name="email" class="form-control"></div>
      <div class="form-group"><label class="form-label"><?= t('phone') ?></label><input type="tel" name="phone" class="form-control"></div>
      <div class="form-group"><label class="form-label"><?= t('password') ?></label><input type="text" name="password" class="form-control" value="changeme"></div>
      <div class="form-group">
        <label class="form-label"><?= t('role') ?></label>
        <select name="role" class="form-control">
          <option value="user">User</option>
          <option value="admin">Admin</option>
          <option value="developer">Developer</option>
        </select>
      </div>
      <button class="btn btn-primary btn-block"><?= t('save') ?></button>
    </form>
  </div>
</div>

<!-- Edit modal -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:300;align-items:center;justify-content:center;padding:20px">
  <div class="card" style="max-width:520px;width:100%">
    <div class="flex justify-between items-center mb-3">
      <h3 style="font-size:18px;font-weight:700"><?= t('edit') ?></h3>
      <button onclick="document.getElementById('editModal').style.display='none'" style="font-size:24px">×</button>
    </div>
    <form method="post" id="editForm">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="e_id">
      <div class="grid-2" style="gap:14px">
        <div class="form-group"><label class="form-label"><?= t('first_name') ?></label><input type="text" name="first_name" class="form-control" id="e_first" required></div>
        <div class="form-group"><label class="form-label"><?= t('last_name') ?></label><input type="text" name="last_name" class="form-control" id="e_last" required></div>
      </div>
      <div class="form-group"><label class="form-label"><?= t('email') ?></label><input type="email" name="email" class="form-control" id="e_email"></div>
      <div class="form-group"><label class="form-label"><?= t('phone') ?></label><input type="tel" name="phone" class="form-control" id="e_phone"></div>
      <div class="form-group">
        <label class="form-label"><?= t('role') ?></label>
        <select name="role" class="form-control" id="e_role">
          <option value="user">User</option>
          <option value="admin">Admin</option>
          <option value="developer">Developer</option>
        </select>
      </div>
      <button class="btn btn-primary btn-block"><?= t('save') ?></button>
    </form>
  </div>
</div>

<script>
function editUser(u){
  document.getElementById('e_id').value = u.id;
  document.getElementById('e_first').value = u.first_name;
  document.getElementById('e_last').value = u.last_name;
  document.getElementById('e_email').value = u.email || '';
  document.getElementById('e_phone').value = u.phone || '';
  document.getElementById('e_role').value = u.role;
  document.getElementById('editModal').style.display = 'flex';
}
[document.getElementById('addModal'), document.getElementById('editModal')].forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; });
});
</script>
</body></html>
