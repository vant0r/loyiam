<?php
require_once __DIR__ . '/../includes/auth.php';
require_developer();

$msg = '';

// Cache tozalash
if (($_POST['action'] ?? '') === 'clear_cache') {
    if (!csrf_check()) {
        $msg = t('csrf_invalid');
    } else {
        // Statik cache fayllarni o'chirish (agar mavjud bo'lsa)
        $cleared = 0;
        $cacheDir = BASE_PATH . '/cache';
        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir . '/data/*.cache') as $f) { @unlink($f); $cleared++; }
            foreach (glob($cacheDir . '/ratelimit/*.json') as $f) { @unlink($f); $cleared++; }
        }
        flush_settings_cache();
        audit('cache_cleared', "Cleared $cleared files");
        $msg = "Cache tozalandi ($cleared fayl)";
    }
}

// Loglarni eksport
if (($_GET['export'] ?? '') === 'logs') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=logs_'.date('Ymd_His').'.csv');
    $f = fopen('php://output', 'w');
    fputcsv($f, ['ID','User ID','Action','Description','IP','Level','Date']);
    foreach (db()->fetchAll("SELECT * FROM logs ORDER BY created_at DESC LIMIT 1000") as $l) {
        fputcsv($f, [$l['id'], $l['user_id'], $l['action'], $l['description'], $l['ip_address'], $l['level'], $l['created_at']]);
    }
    fclose($f); exit;
}

// Tizim ma'lumotlari
$pdo_ok = db()->pdo !== null;
$tables = $pdo_ok ? db()->fetchAll("SHOW TABLES") : [];
$tableNames = [];
foreach ($tables as $r) { $vals = array_values($r); $tableNames[] = $vals[0]; }

$logs = db()->fetchAll("SELECT l.*, u.first_name, u.last_name FROM logs l LEFT JOIN users u ON l.user_id=u.id ORDER BY l.created_at DESC LIMIT 50");

$dbsize = 0;
if ($pdo_ok) {
    $r = db()->fetch("SELECT SUM(data_length + index_length) s FROM information_schema.tables WHERE table_schema = ?", [DB_NAME]);
    $dbsize = (int)($r['s'] ?? 0);
}

// API endpoints (dokumentatsiya)
$endpoints = [
    ['POST',  '/login.php',          'Foydalanuvchi kirishi',     ['login','password']],
    ['POST',  '/register.php',       'Ro\'yxatdan o\'tish',       ['first_name','last_name','phone','password','password2','agree']],
    ['GET',   '/logout.php',         'Tizimdan chiqish',          []],
    ['POST',  '/aloqa.php',          'Aloqa formasi',             ['name','email','phone','message']],
    ['POST',  '/user/profil.php',    'Profil yangilash',          ['action=profile|password','first_name','phone','...']],
    ['POST',  '/user/tariflar.php',  'To\'lov yuborish',          ['action=pay','tariff_id','method']],
    ['POST',  '/admin/users.php',    'Foydalanuvchi CRUD',        ['action=add|edit|delete|block|unblock']],
    ['POST',  '/admin/savollar.php', 'Savol qo\'shish',           ['action=add','ticket_id','question_*','correct']],
    ['POST',  '/admin/sozlamalar.php','Sozlamalar saqlash',       ['action=save_group|logo|banner']],
];

render_head('Developer Panel');
?>
<div class="layout">
<?php render_sidebar('developer','dashboard'); ?>
<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">🖥️ Developer Panel</div>
      <div style="color:var(--text-soft);font-size:14px"><?= e(setting('site_name')) ?> — <?= date('d.m.Y H:i') ?></div>
    </div>
    <div class="flex gap-2">
      <span class="badge badge-<?= $pdo_ok?'success':'danger' ?>"><?= $pdo_ok?'DB Online':'DB Offline' ?></span>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <!-- Tizim holati -->
  <div class="grid-4 mb-3">
    <div class="stat-card"><div class="icon">🐘</div><div class="value" style="font-size:18px">PHP <?= phpversion() ?></div><div class="label">PHP Version</div></div>
    <div class="stat-card"><div class="icon" style="background:#FEF3C7;color:#92400E">🗄️</div><div class="value" style="font-size:18px"><?= count($tableNames) ?></div><div class="label">Jadvallar</div></div>
    <div class="stat-card"><div class="icon" style="background:#FCE7F3;color:#9F1239">💾</div><div class="value" style="font-size:18px"><?= round($dbsize/1024,1) ?> KB</div><div class="label">DB hajmi</div></div>
    <div class="stat-card"><div class="icon" style="background:#D1FAE5;color:#065F46">⚡</div><div class="value" style="font-size:18px"><?= round(memory_get_usage()/1024/1024,2) ?> MB</div><div class="label">Memory</div></div>
  </div>

  <!-- Server info -->
  <div class="card mb-3" id="server">
    <h3 style="font-size:18px;font-weight:700;margin-bottom:14px">🖥️ Server ma'lumotlari</h3>
    <div class="grid-2">
      <div class="table-wrap" style="border:none;box-shadow:none">
        <table>
          <tbody>
            <tr><td style="color:var(--text-soft)">OS</td><td><strong><?= e(php_uname('s')).' '.e(php_uname('r')) ?></strong></td></tr>
            <tr><td style="color:var(--text-soft)">Server software</td><td><strong><?= e($_SERVER['SERVER_SOFTWARE'] ?? '—') ?></strong></td></tr>
            <tr><td style="color:var(--text-soft)">PHP SAPI</td><td><strong><?= php_sapi_name() ?></strong></td></tr>
            <tr><td style="color:var(--text-soft)">Memory limit</td><td><strong><?= ini_get('memory_limit') ?></strong></td></tr>
            <tr><td style="color:var(--text-soft)">Max upload</td><td><strong><?= ini_get('upload_max_filesize') ?></strong></td></tr>
          </tbody>
        </table>
      </div>
      <div class="table-wrap" style="border:none;box-shadow:none">
        <table>
          <tbody>
            <tr><td style="color:var(--text-soft)">Timezone</td><td><strong><?= date_default_timezone_get() ?></strong></td></tr>
            <tr><td style="color:var(--text-soft)">DB Host</td><td><strong><?= DB_HOST ?></strong></td></tr>
            <tr><td style="color:var(--text-soft)">DB Name</td><td><strong><?= DB_NAME ?></strong></td></tr>
            <tr><td style="color:var(--text-soft)">DB Charset</td><td><strong><?= DB_CHARSET ?></strong></td></tr>
            <tr><td style="color:var(--text-soft)">Site URL</td><td><strong><?= e(SITE_URL) ?></strong></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- DB Tables -->
  <div class="card mb-3" id="db">
    <div class="flex justify-between items-center mb-3">
      <h3 style="font-size:18px;font-weight:700">🗄️ Ma'lumotlar bazasi</h3>
      <span class="badge badge-info"><?= count($tableNames) ?> jadval</span>
    </div>
    <div class="grid-3">
      <?php foreach ($tableNames as $tbl):
        $cnt = (int)(db()->fetch("SELECT COUNT(*) c FROM `$tbl`")['c'] ?? 0);
      ?>
        <div class="card" style="padding:14px;background:var(--bg-soft)">
          <div style="font-weight:700;color:var(--primary)"><?= e($tbl) ?></div>
          <div style="color:var(--text-soft);font-size:13px"><?= $cnt ?> ta yozuv</div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Migratsiyalar -->
  <div class="card mb-3" id="mig">
    <h3 style="font-size:18px;font-weight:700;margin-bottom:14px">🔁 Migratsiyalar</h3>
    <div class="alert alert-info" style="font-size:13px">
      Joriy schema: <code>sql/database.sql</code><br>
      Faollashtirish uchun: <code>mysql -u root vatanparvar_yaypan &lt; sql/database.sql</code>
    </div>
    <div class="table-wrap" style="border:none;box-shadow:none">
      <table>
        <thead><tr><th>#</th><th>Migration</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <tr><td>1</td><td>create_initial_schema</td><td><span class="badge badge-success">Applied</span></td><td><span class="badge badge-mute">—</span></td></tr>
          <tr><td>2</td><td>seed_initial_data</td><td><span class="badge badge-success">Applied</span></td><td><span class="badge badge-mute">—</span></td></tr>
        </tbody>
      </table>
    </div>
    <div class="flex gap-2 mt-3">
      <button class="btn btn-light btn-sm" disabled>+ Yaratish</button>
      <button class="btn btn-primary btn-sm" disabled>Run pending</button>
      <button class="btn btn-danger btn-sm" disabled>Rollback</button>
    </div>
  </div>

  <!-- Cache -->
  <div class="card mb-3" id="cache">
    <h3 style="font-size:18px;font-weight:700;margin-bottom:14px">⚡ Cache</h3>
    <div class="grid-3 mb-3">
      <div class="card" style="background:var(--bg-soft)"><div style="color:var(--text-soft);font-size:13px">Driver</div><strong>file</strong></div>
      <div class="card" style="background:var(--bg-soft)"><div style="color:var(--text-soft);font-size:13px">Items</div><strong><?= count(glob(BASE_PATH.'/cache/*') ?: []) ?></strong></div>
      <div class="card" style="background:var(--bg-soft)"><div style="color:var(--text-soft);font-size:13px">Size</div><strong>0 KB</strong></div>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="clear_cache">
      <button class="btn btn-danger btn-sm">🗑️ Cache tozalash</button>
    </form>
  </div>

  <!-- Loglar -->
  <div class="card mb-3" id="logs">
    <div class="flex justify-between items-center mb-3">
      <h3 style="font-size:18px;font-weight:700">📋 Loglar (<?= count($logs) ?>)</h3>
      <a href="?export=logs" class="btn btn-light btn-sm">📥 Export CSV</a>
    </div>
    <div class="table-wrap" style="border:none;box-shadow:none;max-height:420px;overflow-y:auto">
      <table>
        <thead><tr><th>#</th><th>Foydalanuvchi</th><th>Action</th><th>Level</th><th>IP</th><th>Vaqt</th></tr></thead>
        <tbody>
          <?php foreach ($logs as $l): ?>
          <tr>
            <td><?= $l['id'] ?></td>
            <td><?= e(($l['first_name'] ?? '—').' '.($l['last_name'] ?? '')) ?></td>
            <td><strong><?= e($l['action']) ?></strong> <span style="color:var(--text-mute);font-size:12px"><?= e(mb_substr($l['description'] ?? '', 0, 60)) ?></span></td>
            <td>
              <?php $cls = ['info'=>'info','warning'=>'warning','error'=>'danger','critical'=>'danger'][$l['level']] ?? 'mute'; ?>
              <span class="badge badge-<?= $cls ?>"><?= e($l['level']) ?></span>
            </td>
            <td style="font-family:monospace;font-size:12px"><?= e($l['ip_address']) ?></td>
            <td style="font-size:12px"><?= date('d.m.Y H:i:s', strtotime($l['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($logs)): ?>
            <tr><td colspan="6" class="text-center" style="padding:30px;color:var(--text-soft)">Loglar yo'q</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- API endpoints -->
  <div class="card mb-3" id="api">
    <h3 style="font-size:18px;font-weight:700;margin-bottom:14px">🔌 API Endpointlar</h3>
    <div class="table-wrap" style="border:none;box-shadow:none">
      <table>
        <thead><tr><th>Method</th><th>Endpoint</th><th>Tavsif</th><th>Parametrlar</th></tr></thead>
        <tbody>
          <?php foreach ($endpoints as $ep): ?>
          <tr>
            <td><span class="badge badge-<?= $ep[0]==='POST'?'warning':'info' ?>"><?= $ep[0] ?></span></td>
            <td style="font-family:monospace"><?= e($ep[1]) ?></td>
            <td><?= e($ep[2]) ?></td>
            <td style="font-family:monospace;font-size:12px;color:var(--text-soft)"><?= e(implode(', ', array_filter($ep[3], 'is_string'))) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Sozlamalar -->
  <div class="card" id="settings">
    <h3 style="font-size:18px;font-weight:700;margin-bottom:14px">⚙️ Environment</h3>
    <div class="grid-2">
      <div class="card" style="background:var(--bg-soft)">
        <h4 style="font-weight:700;margin-bottom:10px">Constants</h4>
        <div style="font-family:monospace;font-size:12px;line-height:1.8">
          BASE_PATH: <code><?= e(BASE_PATH) ?></code><br>
          UPLOAD_PATH: <code><?= e(UPLOAD_PATH) ?></code><br>
          PRIMARY_COLOR: <code><?= PRIMARY_COLOR ?></code><br>
          DB_NAME: <code><?= DB_NAME ?></code>
        </div>
      </div>
      <div class="card" style="background:var(--bg-soft)">
        <h4 style="font-weight:700;margin-bottom:10px">Sessions</h4>
        <div style="font-family:monospace;font-size:12px;line-height:1.8">
          Session ID: <code><?= e(substr(session_id(), 0, 8) . '...' . substr(session_id(), -4)) ?></code><br>
          User ID: <code><?= $_SESSION['user_id'] ?? '—' ?></code><br>
          Role: <code><?= $_SESSION['user_role'] ?? '—' ?></code><br>
          Lang: <code><?= e(lang()) ?></code>
        </div>
      </div>
    </div>
  </div>
</main>
</div>
</body></html>
