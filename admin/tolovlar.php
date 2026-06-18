<?php
require_once __DIR__ . '/../includes/bootstrap.php';
auth_class();
require_admin();

$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = t('csrf_invalid');
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'set_status' && $id) {
            $status = in_array($_POST['status'] ?? '', ['pending','approved','rejected','refunded']) ? $_POST['status'] : 'pending';
            $note   = Security::clean($_POST['note'] ?? '', 500);
            $p_old  = db()->fetch("SELECT * FROM payments WHERE id=?", [$id]);

            db()->execute("UPDATE payments SET status=?, note=? WHERE id=?", [$status, $note ?: null, $id]);

            if (!$p_old) { /* skip */ }
            elseif ($status === 'approved' && $p_old['tariff_id']) {
                $tariff = db()->fetch("SELECT * FROM tariffs WHERE id=?", [$p_old['tariff_id']]);
                if ($tariff) {
                    $expires = date('Y-m-d H:i:s', strtotime("+{$tariff['duration_days']} days"));
                    db()->execute("UPDATE users SET tariff_id=?, tariff_expires_at=? WHERE id=?",
                        [$p_old['tariff_id'], $expires, $p_old['user_id']]);

                    // Notification — foydalanuvchiga
                    Notify::send((int)$p_old['user_id'], 'payment_approved',
                        lang()==='uz_cyrillic' ? "Тўлов тасдиқланди! 🎉" : "To'lov tasdiqlandi! 🎉",
                        ($tariff['name_latin']) . " tarifingiz faollashtirildi (" . $tariff['duration_days'] . " kun)",
                        ['link' => '/user/tariflar.php', 'icon' => 'check-circle', 'telegram' => true]);
                }
            }
            elseif ($status === 'rejected') {
                Notify::send((int)$p_old['user_id'], 'payment_rejected',
                    lang()==='uz_cyrillic' ? "Тўлов рад этилди" : "To'lov rad etildi",
                    $note ?: (lang()==='uz_cyrillic' ? "Илтимос, қайта уриниб кўринг" : "Iltimos, qayta urinib ko'ring"),
                    ['link' => '/user/tariflar.php', 'icon' => 'x-circle', 'telegram' => true]);
            }

            audit('payment_status_changed', "Payment #$id → $status");
            $msg = t('updated_success');
        }
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
<?= panel_sidebar('admin', 'payments') ?>
<main class="main">

  <div class="page-header-modern">
    <div>
      <div class="page-eyebrow"><?= icon('card', 12) ?> <?= lang()==='uz_cyrillic' ? "Тўлов тарихи" : "To'lov tarixi" ?></div>
      <h1><?= t('payments') ?></h1>
      <div class="page-subtitle"><?= lang()==='uz_cyrillic' ? "Барча тўловларни кузатинг ва тасдиқланг" : "Barcha to'lovlarni kuzating va tasdiqlang" ?></div>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= icon('check-circle', 18) ?> <?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= icon('x-circle', 18) ?> <?= e($err) ?></div><?php endif; ?>

  <!-- Metric cards -->
  <div class="metric-grid mb-3">
    <div class="metric-card is-success">
      <div class="metric-head">
        <div class="metric-icon"><?= icon('check-circle', 18) ?></div>
      </div>
      <div class="metric-value"><?= money($sum_approved) ?></div>
      <div class="metric-label"><?= lang()==='uz_cyrillic' ? "Тасдиқланган" : "Tasdiqlangan" ?> · <?= t('soum') ?></div>
    </div>
    <div class="metric-card is-warning">
      <div class="metric-head">
        <div class="metric-icon"><?= icon('clock', 18) ?></div>
        <?php if ($count_pending > 0): ?><span class="metric-trend" style="background:var(--warning-light);color:var(--warning-dark)"><?= $count_pending ?> <?= lang()==='uz_cyrillic' ? "та" : "ta" ?></span><?php endif; ?>
      </div>
      <div class="metric-value"><?= money($sum_pending) ?></div>
      <div class="metric-label"><?= lang()==='uz_cyrillic' ? "Кутилаётган" : "Kutilayotgan" ?> · <?= t('soum') ?></div>
    </div>
    <div class="metric-card is-primary">
      <div class="metric-head">
        <div class="metric-icon"><?= icon('chart', 18) ?></div>
      </div>
      <div class="metric-value"><?= count($payments) ?></div>
      <div class="metric-label"><?= lang()==='uz_cyrillic' ? "Кўрсатилган" : "Ko'rsatilgan" ?></div>
    </div>
  </div>

  <!-- Filter -->
  <div class="section-card mb-3">
    <div class="section-card-head">
      <div class="section-card-title"><?= icon('filter', 16) ?> <?= lang()==='uz_cyrillic' ? "Филтр" : "Filtr" ?></div>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <a href="?" class="chip <?= !$status_f && !$method_f && !$from && !$to?'is-active':'' ?>"><?= lang()==='uz_cyrillic' ? "Барчаси" : "Barchasi" ?></a>
        <a href="?status=pending" class="chip <?= $status_f==='pending'?'is-active':'' ?>" style="<?= $status_f==='pending'?'background:#FEF3C7;color:#92400E':'' ?>"><?= icon('clock',12) ?> <?= lang()==='uz_cyrillic' ? "Кутилаётган" : "Kutilayotgan" ?></a>
        <a href="?status=approved" class="chip <?= $status_f==='approved'?'is-active':'' ?>" style="<?= $status_f==='approved'?'background:#D1FAE5;color:#065F46':'' ?>"><?= icon('check-circle',12) ?> <?= lang()==='uz_cyrillic' ? "Тасдиқланган" : "Tasdiqlangan" ?></a>
        <a href="?status=rejected" class="chip <?= $status_f==='rejected'?'is-active':'' ?>" style="<?= $status_f==='rejected'?'background:#FEE2E2;color:#991B1B':'' ?>"><?= icon('x-circle',12) ?> <?= lang()==='uz_cyrillic' ? "Рад этилган" : "Rad etilgan" ?></a>
      </div>
    </div>
    <div class="section-card-body">
      <form method="get" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:12px;align-items:end">
        <div class="form-group" style="margin:0">
          <label class="form-label"><?= lang()==='uz_cyrillic' ? "Дан" : "Dan" ?></label>
          <input type="date" name="from" value="<?= e($from) ?>" class="form-control">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label"><?= lang()==='uz_cyrillic' ? "Гача" : "Gacha" ?></label>
          <input type="date" name="to" value="<?= e($to) ?>" class="form-control">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label"><?= t('status') ?></label>
          <select name="status" class="form-control">
            <option value="">— —</option>
            <option value="pending"  <?= $status_f==='pending'?'selected':'' ?>>Pending</option>
            <option value="approved" <?= $status_f==='approved'?'selected':'' ?>>Approved</option>
            <option value="rejected" <?= $status_f==='rejected'?'selected':'' ?>>Rejected</option>
            <option value="refunded" <?= $status_f==='refunded'?'selected':'' ?>>Refunded</option>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label"><?= lang()==='uz_cyrillic' ? "Усул" : "Usul" ?></label>
          <select name="method" class="form-control">
            <option value="">— —</option>
            <option value="click"    <?= $method_f==='click'?'selected':'' ?>>Click</option>
            <option value="payme"    <?= $method_f==='payme'?'selected':'' ?>>Payme</option>
            <option value="manual"   <?= $method_f==='manual'?'selected':'' ?>>Manual</option>
            <option value="telegram" <?= $method_f==='telegram'?'selected':'' ?>>Telegram</option>
          </select>
        </div>
        <button class="btn btn-primary"><?= icon('filter', 14) ?> <?= lang()==='uz_cyrillic' ? "Филтрлаш" : "Filtrlash" ?></button>
      </form>
    </div>
  </div>

  <!-- Payment list -->
  <div class="section-card">
    <div class="section-card-head">
      <div class="section-card-title"><?= icon('card', 16) ?> <?= lang()==='uz_cyrillic' ? "Тўловлар" : "To'lovlar" ?> <span class="count-pill"><?= count($payments) ?></span></div>
    </div>
    <div class="section-card-body flush">
      <?php if (empty($payments)): ?>
        <div class="empty-state-v2">
          <div class="empty-state-v2-icon"><?= icon('card', 32) ?></div>
          <h3><?= lang()==='uz_cyrillic' ? "Тўловлар йўқ" : "To'lovlar yo'q" ?></h3>
          <p><?= lang()==='uz_cyrillic' ? "Танланган филтр бўйича тўловлар топилмади" : "Tanlangan filtr bo'yicha to'lovlar topilmadi" ?></p>
        </div>
      <?php else:
        $st_map = ['pending'=>['warning','clock'],'approved'=>['success','check-circle'],'rejected'=>['danger','x-circle'],'refunded'=>['mute','refresh']];
        foreach ($payments as $p):
          [$st_cls, $st_icon] = $st_map[$p['status']] ?? ['mute','help'];
      ?>
        <div class="payment-card-row" id="p<?= $p['id'] ?>">
          <div class="pc-icon <?= $st_cls ?>"><?= icon($st_icon, 18) ?></div>
          <div class="pc-info">
            <div class="pc-title">
              <?= e(($p['first_name'] ?? '—').' '.($p['last_name'] ?? '')) ?>
              <span class="badge-soft <?= $st_cls ?>" style="margin-left:6px"><?= e($p['status']) ?></span>
            </div>
            <div class="pc-meta">
              <span><?= e($p['tname'] ?? '—') ?></span>
              <span class="activity-meta-dot"></span>
              <span class="data-cell-mono"><?= strtoupper(e($p['method'])) ?></span>
              <?php if ($p['phone']): ?>
                <span class="activity-meta-dot"></span>
                <span class="data-cell-mono"><?= e($p['phone']) ?></span>
              <?php endif; ?>
              <span class="activity-meta-dot"></span>
              <span><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></span>
              <?php if ($p['screenshot']): ?>
                <span class="activity-meta-dot"></span>
                <a href="<?= e($p['screenshot']) ?>" target="_blank" class="text-primary" style="display:inline-flex;align-items:center;gap:4px"><?= icon('image', 12) ?> <?= lang()==='uz_cyrillic' ? "Чек" : "Chek" ?></a>
              <?php endif; ?>
            </div>
          </div>
          <div class="pc-amount"><?= money($p['amount']) ?> <small><?= t('soum') ?></small></div>
          <form method="post" style="display:flex;gap:6px;align-items:center" data-no-loading>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="set_status">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <select name="status" class="form-control" style="padding:6px 10px;font-size:12px;min-height:34px;border-radius:8px;width:auto">
              <option value="pending"  <?= $p['status']==='pending'?'selected':'' ?>>Pending</option>
              <option value="approved" <?= $p['status']==='approved'?'selected':'' ?>>Approved</option>
              <option value="rejected" <?= $p['status']==='rejected'?'selected':'' ?>>Rejected</option>
              <option value="refunded" <?= $p['status']==='refunded'?'selected':'' ?>>Refunded</option>
            </select>
            <button class="btn btn-primary btn-icon btn-sm" title="<?= lang()==='uz_cyrillic' ? "Сақлаш" : "Saqlash" ?>"><?= icon('check', 14) ?></button>
          </form>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

</main>
</div>
<script><?= panel_js() ?></script>
</body></html>
