<?php
require_once __DIR__ . '/../includes/bootstrap.php';
auth_class();
require_admin();

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$ticket = null;
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';
$default_qcount = (int)setting('default_questions_per_ticket', 20);
$default_image  = setting('default_ticket_image', '/assets/images/default-ticket.svg');

if ($isEdit) {
    $ticket = db()->fetch("SELECT * FROM tickets WHERE id = ?", [$id]);
    if (!$ticket) { flash('err', 'Topilmadi'); header('Location: /admin/biletlar.php'); exit; }
}

$msg = flash('msg');
$err = flash('err');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = $isEdit ? "/admin/biletlar-form.php?id=$id" : "/admin/biletlar-form.php";
    if (!csrf_check()) { flash('err', 'CSRF xatosi'); header("Location: $back"); exit; }

    try {
        $title_lat = Security::clean($_POST['title_latin'] ?? '', 150);
        $title_cyr = Security::clean($_POST['title_cyrillic'] ?? '', 150);
        if (!$title_cyr && $title_lat) $title_cyr = uz_latin_to_cyrillic($title_lat);
        if (!$title_lat) throw new Exception('Bilet nomini kiriting');

        $num    = (int)$_POST['ticket_number'];
        $qcount = max(1, (int)($_POST['questions_count'] ?? $default_qcount));
        $tmin   = max(1, (int)($_POST['time_minutes'] ?? 25));
        $status = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

        if ($num < 1) throw new Exception('Bilet raqami noto\'g\'ri');

        $image = $ticket['image'] ?? null;
        if (!empty($_FILES['image']['name'])) {
            $up = Security::upload_image($_FILES['image'], 'ticket');
            if ($up['ok']) { $image = $up['url']; @chmod(BASE_PATH . $up['url'], 0644); }
            else throw new Exception('Rasm: ' . $up['error']);
        }

        if ($isEdit) {
            db()->execute(
                "UPDATE tickets SET title_latin=?, title_cyrillic=?, ticket_number=?, questions_count=?, time_minutes=?, image=?, status=? WHERE id=?",
                [$title_lat, $title_cyr, $num, $qcount, $tmin, $image, $status, $id]);
            flash('msg', t('updated_success'));
        } else {
            $exists = db()->fetch("SELECT id FROM tickets WHERE ticket_number=?", [$num]);
            if ($exists) throw new Exception("Bilet #$num allaqachon mavjud");
            db()->execute(
                "INSERT INTO tickets (title_latin, title_cyrillic, ticket_number, questions_count, time_minutes, image, status)
                 VALUES (?,?,?,?,?,?,?)",
                [$title_lat, $title_cyr, $num, $qcount, $tmin, $image, $status]);
            flash('msg', t('saved_success'));
        }
        header("Location: /admin/biletlar.php"); exit;
    } catch (Throwable $e) {
        flash('err', 'Xatolik: ' . $e->getMessage());
        header("Location: $back"); exit;
    }
}

render_head($isEdit ? t('edit') : t('add'));
?>
<div class="layout">
<?= panel_sidebar('admin', 'tickets') ?>
<main class="main">
  <div class="page-header">
    <div>
      <a href="/admin/biletlar.php" class="text-soft" style="font-size:13px;display:inline-flex;align-items:center;gap:4px;text-decoration:none">
        <?= icon('arrow-left', 14) ?> <?= t('tickets') ?>
      </a>
      <div class="page-title mt-1"><?= icon('ticket', 28) ?> <?= $isEdit ? t('edit') : t('add') ?> <?= t('tickets') ?></div>
    </div>
  </div>

  <?php if ($err): ?><div class="alert alert-danger"><?= icon('x-circle',18) ?> <?= e($err) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" action="" style="max-width:760px">
    <?= csrf_field() ?>

    <div class="card">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">🖼️ Bilet rasmi (ixtiyoriy)</h3>
      <div class="image-up" id="imageUp">
        <img id="imagePrev" src="<?= e($ticket['image'] ?? $default_image) ?>">
        <div class="image-overlay"><?= icon('upload', 32) ?> <strong>Rasm tanlash</strong> <small>JPG, PNG, max 5MB</small></div>
        <input type="file" name="image" accept="image/*" id="imageInput" hidden>
      </div>
      <div class="form-help mt-1">Bo'sh qoldirilsa standart rasm ishlatiladi</div>
    </div>

    <div class="card mt-3">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">📝 Asosiy ma'lumotlar</h3>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Title (Lotin) <span style="color:var(--danger)">*</span></label>
          <input type="text" name="title_latin" class="form-control" required maxlength="150"
                 value="<?= e($_POST['title_latin'] ?? $ticket['title_latin'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Title (Кирилл)</label>
          <input type="text" name="title_cyrillic" class="form-control" maxlength="150"
                 value="<?= e($_POST['title_cyrillic'] ?? $ticket['title_cyrillic'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Bilet № <span style="color:var(--danger)">*</span></label>
          <input type="number" name="ticket_number" class="form-control" required min="1"
                 value="<?= e($_POST['ticket_number'] ?? $ticket['ticket_number'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Savollar</label>
          <input type="number" name="questions_count" class="form-control" min="1" max="100"
                 value="<?= e($_POST['questions_count'] ?? $ticket['questions_count'] ?? $default_qcount) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Vaqt (daq)</label>
          <input type="number" name="time_minutes" class="form-control" min="1" max="180"
                 value="<?= e($_POST['time_minutes'] ?? $ticket['time_minutes'] ?? 25) ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status" class="form-control">
          <option value="active"   <?= ($ticket['status']??'active')==='active'?'selected':'' ?>>✓ Faol</option>
          <option value="inactive" <?= ($ticket['status']??'')==='inactive'?'selected':'' ?>>⏸ Nofaol</option>
        </select>
      </div>
    </div>

    <div class="form-actions">
      <a href="/admin/biletlar.php" class="btn btn-light"><?= t('cancel') ?></a>
      <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> <?= t('save') ?></button>
    </div>
  </form>
</main>
</div>

<style>
.form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:24px;padding:18px 0}
@media(max-width:640px){.form-actions{flex-direction:column-reverse}.form-actions .btn{width:100%}}

.image-up{position:relative;aspect-ratio:16/9;border:2px dashed var(--border);border-radius:var(--r-lg);
  overflow:hidden;cursor:pointer;background:var(--bg-soft);transition:all .25s}
.image-up:hover{border-color:var(--primary)}
.image-up img{width:100%;height:100%;object-fit:cover;display:block}
.image-overlay{position:absolute;inset:0;background:rgba(15,23,42,.5);color:#fff;display:flex;flex-direction:column;
  align-items:center;justify-content:center;gap:6px;opacity:0;transition:opacity .25s}
.image-up:hover .image-overlay{opacity:1}
</style>
<script>
(function(){
  const up = document.getElementById('imageUp');
  const inp = document.getElementById('imageInput');
  const prev = document.getElementById('imagePrev');
  if (!up) return;
  up.addEventListener('click', () => inp.click());
  inp.addEventListener('change', () => {
    if (inp.files[0]) {
      const r = new FileReader();
      r.onload = e => { prev.src = e.target.result; };
      r.readAsDataURL(inp.files[0]);
    }
  });
})();
</script>
<script><?= panel_js() ?></script>
</body></html>
