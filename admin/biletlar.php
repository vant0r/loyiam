<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';
$default_qcount = (int)setting('default_questions_per_ticket', 20);
$default_image  = setting('default_ticket_image', '/assets/images/default-ticket.svg');

$msg = flash('msg');
$err = flash('err');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redir = $_SERVER['REQUEST_URI'];
    if (!csrf_check()) { flash('err', 'CSRF xatosi. Sahifani yangilang.'); header("Location: $redir"); exit; }

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    try {
        if ($action === 'delete' && $id) {
            db()->execute("DELETE FROM tickets WHERE id=?", [$id]);
            flash('msg', t('deleted_success'));
        }
        elseif ($action === 'add' || ($action === 'edit' && $id)) {
            $title_lat = Security::clean($_POST['title_latin'] ?? '', 150);
            $title_cyr = Security::clean($_POST['title_cyrillic'] ?? '', 150);
            if (!$title_cyr && $title_lat) $title_cyr = uz_latin_to_cyrillic($title_lat);
            if (!$title_lat) throw new Exception('Bilet nomini kiriting');

            $num    = (int)$_POST['ticket_number'];
            $qcount = max(1, (int)($_POST['questions_count'] ?? $default_qcount));
            $tmin   = max(1, (int)($_POST['time_minutes'] ?? 25));
            $status = in_array($_POST['status'] ?? 'active', ['active','inactive']) ? $_POST['status'] : 'active';

            if ($num < 1) throw new Exception('Bilet raqami noto\'g\'ri');

            $image = $_POST['old_image'] ?? null;
            if (!empty($_FILES['image']['name'])) {
                $up = Security::upload_image($_FILES['image'], 'ticket');
                if ($up['ok']) { $image = $up['url']; @chmod(BASE_PATH . $up['url'], 0644); }
                else throw new Exception('Rasm: ' . $up['error']);
            }

            if ($action === 'add') {
                // Bilet raqami unique tekshirish
                $exists = db()->fetch("SELECT id FROM tickets WHERE ticket_number=?", [$num]);
                if ($exists) throw new Exception("Bilet #$num allaqachon mavjud");
                db()->execute(
                    "INSERT INTO tickets (title_latin, title_cyrillic, ticket_number, questions_count, time_minutes, image, status)
                     VALUES (?,?,?,?,?,?,?)",
                    [$title_lat, $title_cyr, $num, $qcount, $tmin, $image, $status]);
                flash('msg', t('saved_success'));
            } else {
                db()->execute(
                    "UPDATE tickets SET title_latin=?, title_cyrillic=?, ticket_number=?, questions_count=?, time_minutes=?, image=?, status=? WHERE id=?",
                    [$title_lat, $title_cyr, $num, $qcount, $tmin, $image, $status, $id]);
                flash('msg', t('updated_success'));
            }
        }
        elseif ($action === 'auto_generate') {
            $start = (int)($_POST['start_num'] ?? 1);
            $count = max(1, min(50, (int)($_POST['count'] ?? 10)));
            $created = 0;
            for ($i = 0; $i < $count; $i++) {
                $n = $start + $i;
                $exists = db()->fetch("SELECT id FROM tickets WHERE ticket_number=?", [$n]);
                if (!$exists) {
                    db()->execute(
                        "INSERT INTO tickets (title_latin, title_cyrillic, ticket_number, questions_count, time_minutes)
                         VALUES (?,?,?,?,?)", ["Bilet $n", "Билет $n", $n, $default_qcount, 25]);
                    $created++;
                }
            }
            flash('msg', "$created ta yangi bilet yaratildi");
        }
        else { throw new Exception('Notog\'ri amal'); }
    } catch (Throwable $e) {
        flash('err', 'Xatolik: ' . $e->getMessage());
    }
    header("Location: $redir"); exit;
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
      <button type="button" class="btn btn-light" data-modal-open="autoModal"><?= icon('zap', 16) ?> Auto</button>
      <button type="button" class="btn btn-primary" onclick='openTicketModal({})'><?= icon('plus', 16) ?> <?= t('add') ?></button>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= icon('check-circle', 18) ?> <?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= icon('x-circle', 18) ?> <?= e($err) ?></div><?php endif; ?>

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

  <?php if (empty($tickets)): ?>
    <div class="card empty-state"><?= icon('ticket', 64) ?><h3 class="mt-2">Biletlar yo'q</h3></div>
  <?php else: ?>
  <div class="ticket-grid">
    <?php foreach ($tickets as $tk):
      $img = $tk['image'] ?: $default_image;
      $progress = $tk['questions_count'] > 0 ? min(100, round($tk['qc'] / $tk['questions_count'] * 100)) : 0;
    ?>
    <div class="ticket-card">
      <div class="ticket-cover">
        <img src="<?= e($img) ?>" alt="" loading="lazy">
        <div class="ticket-num">#<?= $tk['ticket_number'] ?></div>
      </div>
      <div class="ticket-body">
        <h4><?= e($tk['title_'.$lang_field]) ?></h4>
        <div class="ticket-meta">
          <span><?= icon('help', 14) ?> <?= $tk['qc'] ?>/<?= $tk['questions_count'] ?></span>
          <span><?= icon('clock', 14) ?> <?= $tk['time_minutes'] ?> min</span>
        </div>
        <div class="progress mt-2"><div class="progress-bar" style="width:<?= $progress ?>%"></div></div>
        <div class="flex gap-1 mt-2 flex-wrap">
          <a href="/admin/savollar.php?ticket=<?= $tk['id'] ?>" class="btn btn-light btn-sm"><?= icon('document', 12) ?> Savollar</a>
          <button type="button" class="btn btn-light btn-sm" onclick='openTicketModal(<?= json_encode($tk, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'>
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
.ticket-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px}
.ticket-card{background:#fff;border:1px solid var(--border);border-radius:var(--r-xl);overflow:hidden;transition:box-shadow .25s}
.ticket-card:hover{box-shadow:var(--shadow-md)}
.ticket-cover{aspect-ratio:16/9;position:relative;overflow:hidden;background:var(--bg-soft)}
.ticket-cover img{width:100%;height:100%;object-fit:cover}
.ticket-num{position:absolute;left:14px;bottom:14px;padding:6px 14px;background:rgba(255,255,255,.95);border-radius:var(--r-md);font-weight:800;color:var(--primary);font-size:18px}
.ticket-body{padding:18px}
.ticket-body h4{font-size:16px;font-weight:700;margin-bottom:10px}
.ticket-meta{display:flex;gap:14px;color:var(--text-soft);font-size:13px}
.ticket-meta span{display:inline-flex;align-items:center;gap:5px}
@media(max-width:480px){.ticket-grid{grid-template-columns:1fr}}
</style>

<div id="ticketModal" class="modal-backdrop">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3 class="modal-title" id="ticketModalTitle"><?= t('add') ?></h3>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="tk_action" value="add">
      <input type="hidden" name="id" id="tk_id">
      <input type="hidden" name="old_image" id="tk_old_image">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Bilet rasmi (ixtiyoriy)</label>
          <input type="file" name="image" accept="image/*" class="form-control" id="tk_image">
          <div class="form-help">Bo'sh qoldirilsa standart rasm ishlatiladi</div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Title (Lotin) *</label>
            <input type="text" name="title_latin" id="tk_lat" class="form-control" required maxlength="150">
          </div>
          <div class="form-group">
            <label class="form-label">Title (Кирилл)</label>
            <input type="text" name="title_cyrillic" id="tk_cyr" class="form-control" maxlength="150">
          </div>
        </div>
        <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Bilet № *</label>
            <input type="number" name="ticket_number" id="tk_num" class="form-control" required min="1">
          </div>
          <div class="form-group">
            <label class="form-label">Savollar</label>
            <input type="number" name="questions_count" id="tk_qc" class="form-control" min="1" max="100" value="<?= $default_qcount ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Vaqt (daq)</label>
            <input type="number" name="time_minutes" id="tk_tm" class="form-control" min="1" max="180" value="25">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" id="tk_st" class="form-control">
            <option value="active">✓ Faol</option>
            <option value="inactive">⏸ Nofaol</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-modal-close><?= t('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> <?= t('save') ?></button>
      </div>
    </form>
  </div>
</div>

<div id="autoModal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">⚡ Auto Generate</h3>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="post" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="auto_generate">
      <div class="modal-body">
        <div class="alert alert-info"><?= icon('zap', 18) ?> <span>Standart 20 savol bilan biletlar yaratiladi</span></div>
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
  const isEdit = !!t.id;
  document.getElementById('tk_action').value = isEdit ? 'edit' : 'add';
  document.getElementById('tk_id').value = t.id || '';
  document.getElementById('tk_lat').value = t.title_latin || '';
  document.getElementById('tk_cyr').value = t.title_cyrillic || '';
  document.getElementById('tk_num').value = t.ticket_number || '';
  document.getElementById('tk_qc').value = t.questions_count || <?= $default_qcount ?>;
  document.getElementById('tk_tm').value = t.time_minutes || 25;
  document.getElementById('tk_st').value = t.status || 'active';
  document.getElementById('tk_old_image').value = t.image || '';
  document.getElementById('ticketModalTitle').textContent = isEdit ? '<?= t('edit') ?>' : '<?= t('add') ?>';
  openModal('ticketModal');
}
</script>
</body></html>
