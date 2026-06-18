<?php
require_once __DIR__ . '/../includes/bootstrap.php';
auth_class();
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
<?= panel_sidebar('admin', 'logs') ?>
<main class="main">

  <div class="page-header-modern">
    <div>
      <div class="page-eyebrow"><?= icon('logs', 12) ?> <?= lang()==='uz_cyrillic' ? "Тизим тарихи" : "Tizim tarixi" ?></div>
      <h1><?= t('logs') ?></h1>
      <div class="page-subtitle"><?= lang()==='uz_cyrillic' ? "Жами" : "Jami" ?> <strong><?= number_format($total) ?></strong> <?= lang()==='uz_cyrillic' ? "ёзув" : "yozuv" ?></div>
    </div>
    <div class="page-toolbar">
      <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn btn-light btn-sm"><?= icon('download', 14) ?> CSV</a>
      <button class="btn btn-danger btn-sm" data-modal-open="clearModal"><?= icon('trash', 14) ?> <?= lang()==='uz_cyrillic' ? "Тозалаш" : "Tozalash" ?></button>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= icon('check-circle', 18) ?> <?= e($msg) ?></div><?php endif; ?>

  <!-- Filter -->
  <div class="section-card mb-3">
    <div class="section-card-head">
      <div class="section-card-title"><?= icon('filter', 16) ?> <?= lang()==='uz_cyrillic' ? "Филтр" : "Filtr" ?></div>
      <?php if ($action_f || $level_f || $from || $to || $user_f): ?>
        <a href="?" class="chip"><?= icon('x', 12) ?> <?= lang()==='uz_cyrillic' ? "Тозалаш" : "Tozalash" ?></a>
      <?php endif; ?>
    </div>
    <div class="section-card-body">
      <form method="get" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:12px;align-items:end">
        <div class="form-group" style="margin:0">
          <label class="form-label">Action</label>
          <select name="action" class="form-control">
            <option value="">— —</option>
            <?php foreach ($actions as $a): ?>
              <option value="<?= e($a['action']) ?>" <?= $action_f===$a['action']?'selected':'' ?>><?= e($a['action']) ?> (<?= $a['c'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">Level</label>
          <select name="level" class="form-control">
            <option value="">— —</option>
            <option value="info"     <?= $level_f==='info'?'selected':'' ?>>Info</option>
            <option value="warning"  <?= $level_f==='warning'?'selected':'' ?>>Warning</option>
            <option value="error"    <?= $level_f==='error'?'selected':'' ?>>Error</option>
            <option value="critical" <?= $level_f==='critical'?'selected':'' ?>>Critical</option>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label"><?= t('from') ?></label>
          <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label"><?= t('to') ?></label>
          <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">User</label>
          <input type="text" name="user" class="form-control" value="<?= e($user_f) ?>" placeholder="<?= t('search') ?>">
        </div>
        <button class="btn btn-primary"><?= icon('filter', 14) ?> <?= t('filter') ?></button>
      </form>
    </div>
  </div>

  <!-- Log feed -->
  <div class="section-card">
    <div class="section-card-head">
      <div class="section-card-title"><?= icon('logs', 16) ?> <?= lang()==='uz_cyrillic' ? "Ёзувлар" : "Yozuvlar" ?> <span class="count-pill"><?= count($logs) ?></span></div>
      <span class="text-mute" style="font-size:12px"><?= lang()==='uz_cyrillic' ? "Саҳифа" : "Sahifa" ?> <?= $page ?> / <?= $totalPages ?></span>
    </div>
    <div class="section-card-body flush">
      <?php if (empty($logs)): ?>
        <div class="empty-state-v2">
          <div class="empty-state-v2-icon"><?= icon('logs', 32) ?></div>
          <h3><?= t('no_data') ?></h3>
        </div>
      <?php else:
        $level_icons = [
          'info'=>['info','primary-dark'],
          'warning'=>['flame','warning-dark'],
          'error'=>['x-circle','danger-dark'],
          'critical'=>['flame','danger-dark'],
        ];
        $level_classes = ['info'=>'info','warning'=>'warning','error'=>'danger','critical'=>'danger'];
        foreach ($logs as $l):
          $lvl = $l['level'] ?? 'info';
          $lvl_cls = $level_classes[$lvl] ?? 'mute';
      ?>
        <div class="log-row">
          <div class="log-id">#<?= $l['id'] ?></div>
          <div>
            <?php if ($l['user_id']): ?>
              <div style="font-weight:600;font-size:13px"><?= e($l['first_name'] ?? '—') ?></div>
              <div class="text-mute" style="font-size:11px">ID: <?= $l['user_id'] ?></div>
            <?php else: ?>
              <span class="text-mute">—</span>
            <?php endif; ?>
          </div>
          <div>
            <span class="log-action"><code><?= e($l['action']) ?></code></span>
            <?php if (!empty($l['description'])): ?>
              <div class="log-desc"><?= e(mb_substr($l['description'] ?? '', 0, 140)) ?></div>
            <?php endif; ?>
          </div>
          <div class="log-ip"><?= e($l['ip_address'] ?? '—') ?></div>
          <div><span class="badge-soft <?= $lvl_cls ?>"><?= e($lvl) ?></span></div>
          <div class="log-time"><?= date('d.m.Y H:i:s', strtotime($l['created_at'])) ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

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

<div id="clearModal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title"><?= lang()==='uz_cyrillic' ? "Эски логларни тозалаш" : "Eski loglarni tozalash" ?></h3><button class="modal-close" data-modal-close>&times;</button></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="clear_old">
      <div class="modal-body">
        <div class="alert alert-warning"><?= icon('flame',18) ?> <?= lang()==='uz_cyrillic' ? "Бу амал қайтарилмайди!" : "Bu amal qaytarilmaydi!" ?></div>
        <div class="form-group">
          <label class="form-label"><?= lang()==='uz_cyrillic' ? "Неча кундан эски логлар ўчирилсин?" : "Necha kundan eski loglar o'chirilsin?" ?></label>
          <select name="days" class="form-control">
            <option value="7">7 <?= lang()==='uz_cyrillic' ? "кундан эски" : "kundan eski" ?></option>
            <option value="30" selected>30 <?= lang()==='uz_cyrillic' ? "кундан эски" : "kundan eski" ?></option>
            <option value="90">90 <?= lang()==='uz_cyrillic' ? "кундан эски" : "kundan eski" ?></option>
            <option value="365">1 <?= lang()==='uz_cyrillic' ? "йилдан эски" : "yildan eski" ?></option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-modal-close><?= t('cancel') ?></button>
        <button class="btn btn-danger" type="submit"><?= icon('trash',14) ?> <?= lang()==='uz_cyrillic' ? "Ўчириш" : "O'chirish" ?></button>
      </div>
    </form>
  </div>
</div>
<script><?= panel_js() ?></script>
</body></html>
