<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'approve' && $id) {
        db()->execute("UPDATE reviews SET status='approved' WHERE id=?", [$id]);
        $msg = lang()==='uz_cyrillic' ? 'Тасдиқланди' : 'Tasdiqlandi';
    }
    if ($action === 'reject' && $id) {
        db()->execute("UPDATE reviews SET status='rejected' WHERE id=?", [$id]);
        $msg = lang()==='uz_cyrillic' ? 'Рад этилди' : 'Rad etildi';
    }
    if ($action === 'delete' && $id) {
        db()->execute("DELETE FROM reviews WHERE id=?", [$id]);
        $msg = t('deleted_success');
    }
    if ($action === 'add') {
        $name   = Security::clean($_POST['name'] ?? '', 100);
        $text   = Security::clean($_POST['text'] ?? '', 1000);
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        if ($name && $text) {
            db()->execute(
                "INSERT INTO reviews (name, text, rating, status) VALUES (?,?,?,'approved')",
                [$name, $text, $rating]);
            $msg = t('saved_success');
        }
    }
}

$status_f = $_GET['status'] ?? '';
$where = "1=1"; $params = [];
if ($status_f) { $where .= " AND status = ?"; $params[] = $status_f; }

$reviews = db()->fetchAll("SELECT * FROM reviews WHERE $where ORDER BY created_at DESC LIMIT 100", $params);
$pending_count = (int)(db()->fetch("SELECT COUNT(*) c FROM reviews WHERE status='pending'")['c'] ?? 0);
$approved_count = (int)(db()->fetch("SELECT COUNT(*) c FROM reviews WHERE status='approved'")['c'] ?? 0);

render_head(t('reviews'));
?>
<div class="layout">
<?php render_sidebar('admin','reviews'); ?>
<main class="main">
  <div class="page-header">
    <div class="page-title"><?= icon('star', 28) ?> <?= t('reviews') ?></div>
    <button class="btn btn-primary" data-modal-open="addModal"><?= icon('plus', 16) ?> <?= t('add') ?></button>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <!-- Stat -->
  <div class="grid-3 mb-3">
    <div class="stat-card">
      <div class="stat-icon"><?= icon('star', 22) ?></div>
      <div class="value"><?= $approved_count + $pending_count ?></div>
      <div class="label">Jami</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon success"><?= icon('check-circle', 22) ?></div>
      <div class="value"><?= $approved_count ?></div>
      <div class="label"><?= t('approved') ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon warning"><?= icon('clock', 22) ?></div>
      <div class="value"><?= $pending_count ?></div>
      <div class="label"><?= t('pending') ?></div>
    </div>
  </div>

  <!-- Tabs filter -->
  <div class="tabs mb-3">
    <a href="?" class="<?= !$status_f?'active':'' ?>"><?= t('all') ?></a>
    <a href="?status=pending" class="<?= $status_f==='pending'?'active':'' ?>"><?= t('pending') ?> <?= $pending_count > 0 ? '('.$pending_count.')' : '' ?></a>
    <a href="?status=approved" class="<?= $status_f==='approved'?'active':'' ?>"><?= t('approved') ?></a>
    <a href="?status=rejected" class="<?= $status_f==='rejected'?'active':'' ?>"><?= t('rejected') ?></a>
  </div>

  <!-- Ro'yxat -->
  <?php if (empty($reviews)): ?>
    <div class="card empty-state">
      <?= icon('star', 64) ?>
      <h3 class="mt-2"><?= lang()==='uz_cyrillic' ? "Шарҳлар йўқ" : "Sharhlar yo'q" ?></h3>
    </div>
  <?php else: ?>
  <div class="grid-2 stagger">
    <?php foreach ($reviews as $r): ?>
    <div class="card" style="position:relative">
      <div class="flex justify-between items-start mb-2">
        <div class="flex items-center gap-3">
          <div class="review-avatar"><?= mb_substr($r['name'],0,1) ?></div>
          <div>
            <strong><?= e($r['name']) ?></strong>
            <div class="text-mute" style="font-size:12px"><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></div>
          </div>
        </div>
        <?php
          $cls = ['approved'=>'success','pending'=>'warning','rejected'=>'danger'][$r['status']] ?? 'mute';
        ?>
        <span class="badge badge-<?= $cls ?>"><?= e(t($r['status'])) ?></span>
      </div>
      <div class="review-stars mb-2" style="color:#FBBF24">
        <?php for ($i=0;$i<(int)$r['rating'];$i++) echo icon('star',14); ?>
      </div>
      <p style="font-size:14px;line-height:1.6;margin-bottom:14px"><?= nl2br(e($r['text'])) ?></p>
      <div class="flex gap-2 flex-wrap">
        <?php if ($r['status'] !== 'approved'): ?>
        <form method="post" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="approve">
          <input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-success btn-sm"><?= icon('check', 12) ?> <?= lang()==='uz_cyrillic' ? "Тасдиқлаш" : "Tasdiqlash" ?></button>
        </form>
        <?php endif; ?>
        <?php if ($r['status'] !== 'rejected'): ?>
        <form method="post" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reject">
          <input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-light btn-sm" style="color:var(--warning-dark)"><?= icon('x', 12) ?> <?= lang()==='uz_cyrillic' ? "Рад этиш" : "Rad etish" ?></button>
        </form>
        <?php endif; ?>
        <form method="post" style="display:inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-light btn-sm" style="color:var(--danger)"><?= icon('trash', 12) ?></button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
</div>

<!-- Add modal -->
<div id="addModal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><?= t('add') ?></h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label"><?= t('name') ?></label>
          <input type="text" name="name" class="form-control" required maxlength="100">
        </div>
        <div class="form-group">
          <label class="form-label"><?= lang()==='uz_cyrillic' ? "Баҳо" : "Baho" ?> (1-5)</label>
          <select name="rating" class="form-control">
            <?php for ($i=5;$i>=1;$i--): ?>
              <option value="<?= $i ?>" <?= $i==5?'selected':'' ?>><?= str_repeat('★',$i) ?> (<?= $i ?>)</option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label"><?= lang()==='uz_cyrillic' ? "Шарҳ матни" : "Sharh matni" ?></label>
          <textarea name="text" class="form-control" required maxlength="1000" rows="4"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-modal-close><?= t('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
      </div>
    </form>
  </div>
</div>
</body></html>
