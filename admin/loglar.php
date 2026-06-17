<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// Eksport
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=logs_'.date('Ymd_His').'.csv');
    $f = fopen('php://output', 'w');
    fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM
    fputcsv($f, ['ID','User ID','User','Action','Description','IP','User Agent','Level','Date']);

    $rows = db()->fetchAll("SELECT l.*, u.first_name, u.last_name FROM logs l
                            LEFT JOIN users u ON l.user_id = u.id
                            ORDER BY l.created_at DESC LIMIT 5000");
    foreach ($rows as $r) {
        fputcsv($f, [
            $r['id'], $r['user_id'], ($r['first_name'] ?? '').' '.($r['last_name'] ?? ''),
            $r['action'], $r['description'], $r['ip_address'],
            $r['user_agent'], $r['level'], $r['created_at'],
        ]);
    }
    fclose($f); exit;
}

// Tozalash
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_old' && csrf_check()) {
    $days = max(7, (int)($_POST['days'] ?? 30));
    db()->execute("DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)", [$days]);
    $count = db()->fetch("SELECT ROW_COUNT() c");
    $msg = ($count['c'] ?? 0) . " ta log o'chirildi";
}

// Filter
$action_f = $_GET['action'] ?? '';
$level_f  = $_GET['level'] ?? '';
$user_f   = trim($_GET['user'] ?? '');
$from     = $_GET['from'] ?? '';
$to       = $_GET['to'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 50;
$offset   = ($page-1) * $perPage;

$where = "1=1"; $params = [];
if ($action_f) { $where .= " AND l.action = ?"; $params[] = $action_f; }
if ($level_f)  { $where .= " AND l.level = ?"; $params[] = $level_f; }
if ($from)     { $where .= " AND DATE(l.created_at) >= ?"; $params[] = $from; }
if ($to)       { $where .= " AND DATE(l.created_at) <= ?"; $params[] = $to; }
if ($user_f)   {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params = array_merge($params, ["%$user_f%","%$user_f%","%$user_f%"]);
}

$total = (int)(db()->fetch(
    "SELECT COUNT(*) c FROM logs l LEFT JOIN users u ON l.user_id=u.id WHERE $where",
    $params)['c'] ?? 0);

$logs = db()->fetchAll(
    "SELECT l.*, u.first_name, u.last_name FROM logs l
     LEFT JOIN users u ON l.user_id = u.id
     WHERE $where ORDER BY l.created_at DESC LIMIT $perPage OFFSET $offset",
    $params);

$totalPages = max(1, (int)ceil($total / $perPage));

// Aksiyalar ro'yxati (filter uchun)
$actions = db()->fetchAll("SELECT action, COUNT(*) c FROM logs GROUP BY action ORDER BY c DESC LIMIT 30");

render_head(t('logs'));
?>
<div class="layout">
<?php render_sidebar('admin','logs'); ?>
<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title"><?= icon('logs', 28) ?> <?= t('logs') ?></div>
      <div class="page-subtitle">Jami: <?= $total ?> ta yozuv</div>
    </div>
    <div class="flex gap-2">
      <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn btn-light"><?= icon('download', 14) ?> CSV</a>
      <button class="btn btn-danger" data-modal-open="clearModal"><?= icon('trash', 14) ?> Tozalash</button>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <!-- Filter -->
  <form method="get" class="card mb-3" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <div class="form-group" style="margin-bottom:0;min-width:160px">
      <label class="form-label">Action</label>
      <select name="action" class="form-control">
        <option value="">— —</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?= e($a['action']) ?>" <?= $action_f===$a['action']?'selected':'' ?>>
            <?= e($a['action']) ?> (<?= $a['c'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin-bottom:0;min-width:120px">
      <label class="form-label">Level</label>
      <select name="level" class="form-control">
        <option value="">— —</option>
        <option value="info"     <?= $level_f==='info'?'selected':'' ?>>Info</option>
        <option value="warning"  <?= $level_f==='warning'?'selected':'' ?>>Warning</option>
        <option value="error"    <?= $level_f==='error'?'selected':'' ?>>Error</option>
        <option value="critical" <?= $level_f==='critical'?'selected':'' ?>>Critical</option>
      </select>
    </div>
    <div class="form-group" style="margin-bottom:0">
      <label class="form-label"><?= t('from') ?></label>
      <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    </div>
    <div class="form-group" style="margin-bottom:0">
      <label class="form-label"><?= t('to') ?></label>
      <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    </div>
    <div class="form-group flex-1" style="margin-bottom:0;min-width:160px">
      <label class="form-label">User</label>
      <input type="text" name="user" class="form-control" value="<?= e($user_f) ?>" placeholder="<?= t('search') ?>">
    </div>
    <button class="btn btn-primary"><?= icon('filter', 14) ?> <?= t('filter') ?></button>
    <a href="?" class="btn btn-ghost"><?= icon('x', 14) ?></a>
  </form>

  <!-- List -->
  <div class="card" style="padding:0">
    <div class="table-wrap table-flat">
      <table>
        <thead><tr><th>ID</th><th><?= lang()==='uz_cyrillic' ? "Фойдаланувчи" : "Foydalanuvchi" ?></th><th>Action</th><th>Tavsif</th><th>IP</th><th>Level</th><th>Sana</th></tr></thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr><td colspan="7" class="text-center" style="padding:40px;color:var(--text-soft)"><?= t('no_data') ?></td></tr>
          <?php else: foreach ($logs as $l): ?>
          <tr>
            <td>#<?= $l['id'] ?></td>
            <td>
              <?php if ($l['user_id']): ?>
                <strong><?= e($l['first_name'] ?? '—') ?></strong>
                <div class="text-mute" style="font-size:11px">ID: <?= $l['user_id'] ?></div>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td><code style="background:var(--bg-mute);padding:3px 8px;border-radius:4px;font-size:12px"><?= e($l['action']) ?></code></td>
            <td style="max-width:300px"><?= e(mb_substr($l['description'] ?? '', 0, 100)) ?></td>
            <td style="font-family:monospace;font-size:12px"><?= e($l['ip_address']) ?></td>
            <td>
              <?php $cls = ['info'=>'info','warning'=>'warning','error'=>'danger','critical'=>'danger'][$l['level']] ?? 'mute'; ?>
              <span class="badge badge-<?= $cls ?>"><?= e($l['level']) ?></span>
            </td>
            <td style="font-size:12px;white-space:nowrap"><?= date('d.m.Y H:i:s', strtotime($l['created_at'])) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>"><?= icon('chevron-left',16) ?></a><?php endif; ?>
    <?php
    $start = max(1, $page - 3);
    $end = min($totalPages, $page + 3);
    for ($i = $start; $i <= $end; $i++):
      if ($i == $page) echo '<span class="active">'.$i.'</span>';
      else echo '<a href="?'.http_build_query(array_merge($_GET,['page'=>$i])).'">'.$i.'</a>';
    endfor; ?>
    <?php if ($page < $totalPages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>"><?= icon('chevron-right',16) ?></a><?php endif; ?>
  </div>
  <?php endif; ?>
</main>
</div>

<!-- Clear modal -->
<div id="clearModal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">Eski loglarni tozalash</h3><button class="modal-close" data-modal-close>&times;</button></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="clear_old">
      <div class="modal-body">
        <div class="alert alert-warning"><?= icon('flame',18) ?> Bu amal qaytarilmaydi!</div>
        <div class="form-group">
          <label class="form-label">Necha kundan eski loglar o'chirilsin?</label>
          <select name="days" class="form-control">
            <option value="7">7 kundan eski</option>
            <option value="30" selected>30 kundan eski</option>
            <option value="90">90 kundan eski</option>
            <option value="365">1 yildan eski</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-modal-close><?= t('cancel') ?></button>
        <button class="btn btn-danger" type="submit">O'chirish</button>
      </div>
    </form>
  </div>
</div>
</body></html>
