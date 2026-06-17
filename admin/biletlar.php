<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';
$msg = '';
$default_qcount = (int)setting('default_questions_per_ticket', 20);
$default_image  = setting('default_ticket_image', '/assets/images/default-ticket.svg');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        db()->execute("DELETE FROM tickets WHERE id=?", [$id]);
        $msg = t('deleted_success');
    }

    if ($action === 'add' || ($action === 'edit' && $id)) {
        $title_lat = Security::clean($_POST['title_latin'] ?? '', 150);
        $title_cyr = Security::clean($_POST['title_cyrillic'] ?? '', 150);
        if (!$title_cyr && $title_lat) $title_cyr = uz_latin_to_cyrillic($title_lat);

        $num    = (int)$_POST['ticket_number'];
        $qcount = max(1, (int)($_POST['questions_count'] ?? $default_qcount));
        $tmin   = max(1, (int)($_POST['time_minutes'] ?? 25));
        $status = in_array($_POST['status'] ?? 'active', ['active','inactive']) ? $_POST['status'] : 'active';

        // Rasm yuklash
        $image = $_POST['old_image'] ?? null;
        if (!empty($_FILES['image']['name'])) {
            $up = Security::upload_image($_FILES['image'], 'ticket');
            if ($up['ok']) $image = $up['url'];
        }

        if ($action === 'add') {
            db()->execute(
                "INSERT INTO tickets (title_latin, title_cyrillic, ticket_number,
                 questions_count, time_minutes, image, status)
                 VALUES (?,?,?,?,?,?,?)",
                [$title_lat, $title_cyr, $num, $qcount, $tmin, $image, $status]);
            $msg = t('saved_success');
        } else {
            db()->execute(
                "UPDATE tickets SET title_latin=?, title_cyrillic=?, ticket_number=?,
                 questions_count=?, time_minutes=?, image=?, status=? WHERE id=?",
                [$title_lat, $title_cyr, $num, $qcount, $tmin, $image, $status, $id]);
            $msg = t('updated_success');
        }
    }

    if ($action === 'auto_generate') {
        $start = (int)($_POST['start_num'] ?? 1);
        $count = max(1, min(50, (int)($_POST['count'] ?? 10)));
        for ($i = 0; $i < $count; $i++) {
            $n = $start + $i;
            $exists = db()->fetch("SELECT id FROM tickets WHERE ticket_number=?", [$n]);
            if (!$exists) {
                db()->execute(
                    "INSERT INTO tickets (title_latin, title_cyrillic, ticket_number,
                     questions_count, time_minutes) VALUES (?,?,?,?,?)",
                    ["Bilet $n", "Билет $n", $n, $default_qcount, 25]);
            }
        }
        $msg = "$count ta bilet generatsiya qilindi";
    }
}

$tickets = db()->fetchAll(
    "SELECT t.*, (SELECT COUNT(*) FROM questions WHERE ticket_id=t.id) qc
     FROM tickets t ORDER BY t.ticket_number");

render_head(t('tickets'));
?>
<div class="layout">
<?php render_sidebar('admin','tickets'); ?>
<main class="main">
  <div class="page-header">
    <div class="page-title"><?= icon('ticket', 28) ?> <?= t('tickets') ?></div>
    <div class="flex gap-2">
      <button class="btn btn-light" data-modal-open="autoModal"><?= icon('zap', 16) ?> Auto</button>
      <button class="btn btn-primary" onclick='openTicketModal({})'><?= icon('plus', 16) ?> <?= t('add') ?></button>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= icon('check-circle', 18) ?> <?= e($msg) ?></div><?php endif; ?>

  <!-- Statistika -->
  <div class="grid-3 mb-3">
    <div class="stat-card">
      <div class="stat-icon"><?= icon('ticket', 22) ?></div>
      <div class="value"><?= count($tickets) ?></div>
      <div class="label">Jami biletlar</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon success"><?= icon('check-circle', 22) ?></div>
      <div class="value"><?= count(array_filter($tickets, fn($t) => $t['status'] === 'active')) ?></div>
      <div class="label">Faol biletlar</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon warning"><?= icon('help', 22) ?></div>
      <div class="value"><?= array_sum(array_column($tickets, 'qc')) ?></div>
      <div class="label">Jami savollar</div>
    </div>
  </div>

  <!-- Biletlar grid -->
  <?php if (empty($tickets)): ?>
    <div class="card empty-state">
      <?= icon('ticket', 64) ?>
      <h3 class="mt-2"><?= lang()==='uz_cyrillic' ? "Билетлар йўқ" : "Biletlar yo'q" ?></h3>
      <p><?= lang()==='uz_cyrillic' ? "Биринчи билетни қўшинг" : "Birinchi biletni qo'shing" ?></p>
    </div>
  <?php else: ?>
  <div class="ticket-grid stagger">
    <?php foreach ($tickets as $tk):
      $img = $tk['image'] ?: $default_image;
      $progress = $tk['questions_count'] > 0 ? min(100, round($tk['qc'] / $tk['questions_count'] * 100)) : 0;
    ?>
    <div class="ticket-card <?= $tk['status']==='inactive'?'is-inactive':'' ?>">
      <div class="ticket-cover">
        <img src="<?= e($img) ?>" alt="" loading="lazy">
        <div class="ticket-num">#<?= $tk['ticket_number'] ?></div>
        <span class="badge badge-<?= $tk['status']==='active'?'success':'mute' ?>" style="position:absolute;top:12px;right:12px">
          <?= $tk['status']==='active' ? '✓ '.t('active') : t('inactive') ?>
        </span>
      </div>
      <div class="ticket-body">
        <h4><?= e($tk['title_'.$lang_field]) ?></h4>
        <div class="ticket-meta">
          <span><?= icon('help', 14) ?> <?= $tk['qc'] ?>/<?= $tk['questions_count'] ?></span>
          <span><?= icon('clock', 14) ?> <?= $tk['time_minutes'] ?> <?= t('minutes') ?></span>
        </div>
        <div class="progress mt-2"><div class="progress-bar" style="width:<?= $progress ?>%"></div></div>
        <div class="text-mute" style="font-size:11px;margin-top:4px"><?= $progress ?>% to'lgan</div>
        <div class="flex gap-1 mt-2 flex-wrap">
          <a href="/admin/savollar.php?ticket=<?= $tk['id'] ?>" class="btn btn-light btn-sm">
            <?= icon('document', 12) ?> Savollar
          </a>
          <button class="btn btn-light btn-sm" onclick='openTicketModal(<?= json_encode($tk, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
            <?= icon('edit', 12) ?>
          </button>
          <form method="post" style="display:inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $tk['id'] ?>">
            <button class="btn btn-light btn-sm" style="color:var(--danger)"><?= icon('trash', 12) ?></button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
</div>

<style>
.ticket-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px}
.ticket-card{background:#fff;border:1px solid var(--border);border-radius:var(--r-xl);
  overflow:hidden;transition:all .35s var(--ease-out);position:relative}
.ticket-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-lg);border-color:var(--primary-200)}
.ticket-card.is-inactive{opacity:.6}
.ticket-cover{aspect-ratio:16/9;position:relative;overflow:hidden;background:var(--bg-soft)}
.ticket-cover img{width:100%;height:100%;object-fit:cover;transition:transform .6s var(--ease-out)}
.ticket-card:hover .ticket-cover img{transform:scale(1.06)}
.ticket-num{position:absolute;left:14px;bottom:14px;padding:6px 14px;background:rgba(255,255,255,.95);
  backdrop-filter:blur(8px);border-radius:var(--r-md);font-weight:800;color:var(--primary);
  font-size:18px;box-shadow:var(--shadow-sm)}
.ticket-body{padding:18px}
.ticket-body h4{font-size:16px;font-weight:700;margin-bottom:10px}
.ticket-meta{display:flex;gap:14px;color:var(--text-soft);font-size:13px;flex-wrap:wrap}
.ticket-meta span{display:inline-flex;align-items:center;gap:5px}
@media(max-width:480px){
  .ticket-grid{grid-template-columns:1fr}
  .ticket-card{animation:none}
}
</style>

<!-- Bilet modal -->
<div id="ticketModal" class="modal-backdrop">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3 class="modal-title" id="ticketModalTitle"><?= t('add') ?></h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" id="ticketForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="t_action" value="add">
      <input type="hidden" name="id" id="t_id">
      <input type="hidden" name="old_image" id="t_old_image">

      <div class="modal-body">
        <!-- Image upload -->
        <div class="form-group">
          <label class="form-label">Bilet rasmi (ixtiyoriy)</label>
          <div class="image-uploader" id="ticketImageDrop">
            <input type="file" name="image" accept="image/*" id="t_image" hidden>
            <img id="t_preview" src="<?= e($default_image) ?>" alt="">
            <div class="image-uploader-overlay">
              <?= icon('upload', 32) ?>
              <strong>Rasm tanlash</strong>
              <small>JPG, PNG, WEBP, SVG (max 5MB)</small>
            </div>
          </div>
          <div class="form-help">Bo'sh qoldirilsa standart rasm ishlatiladi</div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Title (Lotin) <span style="color:var(--danger)">*</span></label>
            <input type="text" name="title_latin" id="t_lat" class="form-control" required maxlength="150">
          </div>
          <div class="form-group">
            <label class="form-label">Title (Кирилл) <small class="text-mute">— bo'sh = avto</small></label>
            <input type="text" name="title_cyrillic" id="t_cyr" class="form-control" maxlength="150">
          </div>
        </div>

        <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Bilet № <span style="color:var(--danger)">*</span></label>
            <input type="number" name="ticket_number" id="t_num" class="form-control" required min="1">
          </div>
          <div class="form-group">
            <label class="form-label">Savollar</label>
            <input type="number" name="questions_count" id="t_qc" class="form-control" min="1" max="100" value="<?= $default_qcount ?>">
            <div class="form-help">Standart: <?= $default_qcount ?></div>
          </div>
          <div class="form-group">
            <label class="form-label">Vaqt (daq)</label>
            <input type="number" name="time_minutes" id="t_tm" class="form-control" min="1" max="180" value="25">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" id="t_st" class="form-control">
            <option value="active">✓ Faol</option>
            <option value="inactive">⏸ Nofaol</option>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-modal-close><?= t('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
      </div>
    </form>
  </div>
</div>

<style>
.image-uploader{position:relative;border:2px dashed var(--border);border-radius:var(--r-lg);
  overflow:hidden;cursor:pointer;background:var(--bg-soft);transition:all .25s;aspect-ratio:16/9}
.image-uploader:hover{border-color:var(--primary);background:var(--primary-50)}
.image-uploader.is-dragover{border-color:var(--primary);background:var(--primary-100)}
.image-uploader img{width:100%;height:100%;object-fit:cover;display:block}
.image-uploader-overlay{position:absolute;inset:0;background:rgba(15,23,42,.4);color:#fff;
  display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;
  opacity:0;transition:opacity .25s}
.image-uploader:hover .image-uploader-overlay{opacity:1}
.image-uploader-overlay strong{font-size:14px}
.image-uploader-overlay small{font-size:11px;opacity:.85}
</style>

<!-- Auto generate modal -->
<div id="autoModal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">⚡ Auto Generate</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="auto_generate">
      <div class="modal-body">
        <div class="alert alert-info">
          <?= icon('zap', 18) ?>
          <span>Standart 20 savol va standart rasm bilan biletlar yaratiladi</span>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Boshlash №</label>
            <input type="number" name="start_num" class="form-control" value="1" required min="1">
          </div>
          <div class="form-group">
            <label class="form-label">Soni</label>
            <input type="number" name="count" class="form-control" value="10" required min="1" max="50">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-modal-close><?= t('cancel') ?></button>
        <button type="submit" class="btn btn-primary">Generate</button>
      </div>
    </form>
  </div>
</div>

<script>
function openTicketModal(t){
  document.getElementById('t_action').value = t.id ? 'edit' : 'add';
  document.getElementById('t_id').value = t.id || '';
  document.getElementById('t_lat').value = t.title_latin || '';
  document.getElementById('t_cyr').value = t.title_cyrillic || '';
  document.getElementById('t_num').value = t.ticket_number || '';
  document.getElementById('t_qc').value = t.questions_count || <?= $default_qcount ?>;
  document.getElementById('t_tm').value = t.time_minutes || 25;
  document.getElementById('t_st').value = t.status || 'active';
  document.getElementById('t_old_image').value = t.image || '';
  document.getElementById('t_preview').src = t.image || '<?= e($default_image) ?>';
  document.getElementById('ticketModalTitle').textContent = t.id ? '<?= t('edit') ?>' : '<?= t('add') ?>';
  openModal('ticketModal');
}

// Image upload preview + drag&drop
(function(){
  const drop = document.getElementById('ticketImageDrop');
  const input = document.getElementById('t_image');
  const preview = document.getElementById('t_preview');
  if (!drop) return;
  drop.addEventListener('click', () => input.click());
  drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('is-dragover'); });
  drop.addEventListener('dragleave', () => drop.classList.remove('is-dragover'));
  drop.addEventListener('drop', e => {
    e.preventDefault(); drop.classList.remove('is-dragover');
    if (e.dataTransfer.files[0]) { input.files = e.dataTransfer.files; previewFile(); }
  });
  input.addEventListener('change', previewFile);
  function previewFile(){
    if (input.files && input.files[0]) {
      const r = new FileReader();
      r.onload = e => { preview.src = e.target.result; };
      r.readAsDataURL(input.files[0]);
    }
  }
})();
</script>
</body></html>
