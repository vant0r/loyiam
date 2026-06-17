<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'set_status' && $id) {
        $status = in_array($_POST['status'], ['pending','approved','rejected','refunded']) ? $_POST['status'] : 'pending';
        db()->execute("UPDATE payments SET status=? WHERE id=?", [$status, $id]);

        // Agar approved bo'lsa, foydalanuvchi tarifi yangilanadi
        if ($status === 'approved') {
            $p = db()->fetch("SELECT * FROM payments WHERE id=?", [$id]);
            if ($p && $p['tariff_id']) {
                $tariff = db()->fetch("SELECT * FROM tariffs WHERE id=?", [$p['tariff_id']]);
                $expires = date('Y-m-d H:i:s', strtotime("+{$tariff['duration_days']} days"));
                db()->execute("UPDATE users SET tariff_id=?, tariff_expires_at=? WHERE id=?",
                    [$p['tariff_id'], $expires, $p['user_id']]);
            }
        }
        $msg = lang()==='uz_cyrillic' ? 'Янгиланди' : 'Yangilandi';
    }
}

$status_f = $_GET['status'] ?? '';
$method_f = $_GET['method'] ?? '';
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

$where = "WHERE 1=1"; $params = [];
if ($status_f) { $where .= " AND p.status = ?"; $params[] = $status_f; }
if ($method_f) { $where .= " AND p.method = ?"; $params[] = $method_f; }
if ($from) { $where .= " AND DATE(p.created_at) >= ?"; $params[] = $from; }
if ($to)   { $where .= " AND DATE(p.created_at) <= ?"; $params[] = $to; }

$payments = db()->fetchAll(
    "SELECT p.*, u.first_name, u.last_name, u.phone, t.name_$lang_field tname
     FROM payments p
     LEFT JOIN users u ON p.user_id = u.id
     LEFT JOIN tariffs t ON p.tariff_id = t.id
     $where ORDER BY p.created_at DESC LIMIT 200", $params);

$sum_approved = (float)(db()->fetch("SELECT COALESCE(SUM(amount),0) c FROM payments WHERE status='approved'")['c'] ?? 0);
$sum_pending  = (float)(db()->fetch("SELECT COALESCE(SUM(amount),0) c FROM payments WHERE status='pending'")['c'] ?? 0);
$count_pending= (int)(db()->fetch("SELECT COUNT(*) c FROM payments WHERE status='pending'")['c'] ?? 0);

render_head(t('payments'));
?>
<div class="layout">
<?php render_sidebar('admin','payments'); ?>
<main class="main">
  <div class="page-header">
    <div class="page-title">💳 <?= t('payments') ?></div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <div class="grid-3 mb-3">
    <div class="stat-card"><div class="icon" style="background:#D1FAE5;color:#065F46">✓</div><div class="value" style="font-size:22px"><?= money($sum_approved) ?></div><div class="label"><?= lang()==='uz_cyrillic' ? 'Тасдиқланган' : 'Tasdiqlangan' ?> (<?= t('soum') ?>)</div></div>
    <div class="stat-card"><div class="icon" style="background:#FEF3C7;color:#92400E">⏳</div><div class="value" style="font-size:22px"><?= money($sum_pending) ?></div><div class="label"><?= lang()==='uz_cyrillic' ? 'Кутилаётган' : 'Kutilayotgan' ?></div></div>
    <div class="stat-card"><div class="icon" style="background:#DBEAFE;color:#1E40AF">📊</div><div class="value"><?= $count_pending ?></div><div class="label"><?= lang()==='uz_cyrillic' ? 'Сони' : 'Soni' ?></div></div>
  </div>

  <!-- Filter -->
  <form method="get" class="card mb-3" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <div class="form-group" style="margin-bottom:0">
      <label class="form-label"><?= lang()==='uz_cyrillic' ? 'Дан' : 'Dan' ?></label>
      <input type="date" name="from" value="<?= e($from) ?>" class="form-control">
    </div>
    <div class="form-group" style="margin-bottom:0">
      <label class="form-label"><?= lang()==='uz_cyrillic' ? 'Гача' : 'Gacha' ?></label>
      <input type="date" name="to" value="<?= e($to) ?>" class="form-control">
    </div>
    <div class="form-group" style="margin-bottom:0;min-width:140px">
      <label class="form-label"><?= t('status') ?></label>
      <select name="status" class="form-control">
        <option value="">— —</option>
        <option value="pending"  <?= $status_f==='pending'?'selected':'' ?>>Pending</option>
        <option value="approved" <?= $status_f==='approved'?'selected':'' ?>>Approved</option>
        <option value="rejected" <?= $status_f==='rejected'?'selected':'' ?>>Rejected</option>
        <option value="refunded" <?= $status_f==='refunded'?'selected':'' ?>>Refunded</option>
      </select>
    </div>
    <div class="form-group" style="margin-bottom:0;min-width:140px">
      <label class="form-label"><?= lang()==='uz_cyrillic' ? 'Усул' : 'Usul' ?></label>
      <select name="method" class="form-control">
        <option value="">— —</option>
        <option value="click"    <?= $method_f==='click'?'selected':'' ?>>Click</option>
        <option value="payme"    <?= $method_f==='payme'?'selected':'' ?>>Payme</option>
        <option value="manual"   <?= $method_f==='manual'?'selected':'' ?>>Manual</option>
        <option value="telegram" <?= $method_f==='telegram'?'selected':'' ?>>Telegram</option>
      </select>
    </div>
    <button class="btn btn-primary"><?= lang()==='uz_cyrillic' ? 'Филтрлаш' : 'Filtrlash' ?></button>
  </form>

  <!-- List -->
  <div class="card" style="padding:0">
    <div class="table-wrap" style="border:none;box-shadow:none">
      <table>
        <thead><tr><th>ID</th><th><?= lang()==='uz_cyrillic' ? 'Фойдаланувчи' : 'Foydalanuvchi' ?></th><th><?= t('tariffs') ?></th><th><?= t('amount') ?></th><th><?= lang()==='uz_cyrillic' ? 'Усул' : 'Usul' ?></th><th><?= t('status') ?></th><th>Чек</th><th><?= t('date') ?></th><th><?= t('actions') ?></th></tr></thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
          <tr>
            <td>#<?= $p['id'] ?></td>
            <td>
              <div><strong><?= e($p['first_name'].' '.$p['last_name']) ?></strong></div>
              <div style="font-size:12px;color:var(--text-mute)"><?= e($p['phone']) ?></div>
            </td>
            <td><?= e($p['tname'] ?? '—') ?></td>
            <td><strong><?= money($p['amount']) ?></strong></td>
            <td><?= e(strtoupper($p['method'])) ?></td>
            <td>
              <?php $cls = ['pending'=>'warning','approved'=>'success','rejected'=>'danger','refunded'=>'mute'][$p['status']] ?? 'mute'; ?>
              <span class="badge badge-<?= $cls ?>"><?= e($p['status']) ?></span>
            </td>
            <td><?php if ($p['screenshot']): ?><a href="<?= e($p['screenshot']) ?>" target="_blank">📷</a><?php else: ?>—<?php endif; ?></td>
            <td><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></td>
            <td>
              <form method="post" style="display:flex;gap:4px">
                <input type="hidden" name="action" value="set_status">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <select name="status" class="form-control" style="padding:6px 8px;font-size:12px">
                  <option value="pending"  <?= $p['status']==='pending'?'selected':'' ?>>Pending</option>
                  <option value="approved" <?= $p['status']==='approved'?'selected':'' ?>>Approved</option>
                  <option value="rejected" <?= $p['status']==='rejected'?'selected':'' ?>>Rejected</option>
                  <option value="refunded" <?= $p['status']==='refunded'?'selected':'' ?>>Refunded</option>
                </select>
                <button class="btn btn-primary btn-sm">✓</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($payments)): ?>
            <tr><td colspan="9" class="text-center" style="padding:40px;color:var(--text-soft)"><?= lang()==='uz_cyrillic' ? 'Тўловлар йўқ' : 'To\'lovlar yo\'q' ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>
</body></html>
