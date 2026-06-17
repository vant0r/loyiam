<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$review = null;

if ($isEdit) {
    $review = db()->fetch("SELECT * FROM reviews WHERE id = ?", [$id]);
    if (!$review) { flash('err', 'Topilmadi'); header('Location: /admin/sharhlar.php'); exit; }
}

$msg = flash('msg');
$err = flash('err');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = $isEdit ? "/admin/sharhlar-form.php?id=$id" : "/admin/sharhlar-form.php";
    if (!csrf_check()) { flash('err', 'CSRF xatosi'); header("Location: $back"); exit; }

    try {
        $name = Security::clean($_POST['name'] ?? '', 100);
        $text = Security::clean($_POST['text'] ?? '', 1000);
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $status = in_array($_POST['status'] ?? '', ['pending','approved','rejected']) ? $_POST['status'] : 'pending';

        if (!$name) throw new Exception('Ism kerak');
        if (!$text || mb_strlen($text) < 5) throw new Exception('Sharh matni kerak (5+ belgi)');

        if ($isEdit) {
            db()->execute("UPDATE reviews SET name=?, text=?, rating=?, status=? WHERE id=?",
                [$name, $text, $rating, $status, $id]);
            flash('msg', t('updated_success'));
        } else {
            db()->execute("INSERT INTO reviews (name, text, rating, status) VALUES (?,?,?,?)",
                [$name, $text, $rating, $status]);
            flash('msg', t('saved_success'));
        }
        header("Location: /admin/sharhlar.php"); exit;
    } catch (Throwable $e) {
        flash('err', 'Xatolik: ' . $e->getMessage());
        header("Location: $back"); exit;
    }
}

render_head($isEdit ? t('edit') : t('add'));
?>
<div class="layout">
<?php render_sidebar('admin','reviews'); ?>
<main class="main">
  <div class="page-header">
    <div>
      <a href="/admin/sharhlar.php" class="text-soft" style="font-size:13px;display:inline-flex;align-items:center;gap:4px;text-decoration:none">
        <?= icon('arrow-left', 14) ?> <?= t('reviews') ?>
      </a>
      <div class="page-title mt-1"><?= icon('star', 28) ?> <?= $isEdit ? t('edit') : t('add') ?> <?= t('reviews') ?></div>
    </div>
  </div>

  <?php if ($err): ?><div class="alert alert-danger"><?= icon('x-circle',18) ?> <?= e($err) ?></div><?php endif; ?>

  <form method="post" action="" style="max-width:600px">
    <?= csrf_field() ?>

    <div class="card">
      <div class="form-group">
        <label class="form-label"><?= t('name') ?> <span style="color:var(--danger)">*</span></label>
        <input type="text" name="name" class="form-control" required maxlength="100"
               value="<?= e($_POST['name'] ?? $review['name'] ?? '') ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Baho</label>
          <select name="rating" class="form-control">
            <?php for ($i=5;$i>=1;$i--): ?>
              <option value="<?= $i ?>" <?= ($review['rating']??5)==$i?'selected':'' ?>><?= str_repeat('★',$i) ?> (<?= $i ?>)</option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="approved" <?= ($review['status']??'approved')==='approved'?'selected':'' ?>>✓ Tasdiqlangan</option>
            <option value="pending"  <?= ($review['status']??'')==='pending'?'selected':'' ?>>⏳ Kutilmoqda</option>
            <option value="rejected" <?= ($review['status']??'')==='rejected'?'selected':'' ?>>✗ Rad etilgan</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Sharh matni <span style="color:var(--danger)">*</span></label>
        <textarea name="text" class="form-control" required maxlength="1000" rows="5" minlength="5"><?= e($_POST['text'] ?? $review['text'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="form-actions">
      <a href="/admin/sharhlar.php" class="btn btn-light"><?= t('cancel') ?></a>
      <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> <?= t('save') ?></button>
    </div>
  </form>
</main>
</div>

<style>
.form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:24px;padding:18px 0}
@media(max-width:640px){.form-actions{flex-direction:column-reverse}.form-actions .btn{width:100%}}
</style>
</body></html>
