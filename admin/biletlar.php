<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        db()->execute("DELETE FROM tickets WHERE id=?", [$id]);
        $msg = lang()==='uz_cyrillic' ? 'Ўчирилди' : 'O\'chirildi';
    }
    if ($action === 'add') {
        $num = (int)$_POST['ticket_number'];
        db()->execute("INSERT INTO tickets (title_latin, title_cyrillic, ticket_number, questions_count, time_minutes)
                       VALUES (?,?,?,?,?)",
            [trim($_POST['title_latin']), trim($_POST['title_cyrillic']) ?: trim($_POST['title_latin']), $num,
             (int)$_POST['questions_count'], (int)$_POST['time_minutes']]);
        $msg = lang()==='uz_cyrillic' ? 'Қўшилди' : 'Qo\'shildi';
    }
    if ($action === 'edit' && $id) {
        db()->execute("UPDATE tickets SET title_latin=?, title_cyrillic=?, ticket_number=?, questions_count=?, time_minutes=?, status=? WHERE id=?",
            [trim($_POST['title_latin']), trim($_POST['title_cyrillic']),
             (int)$_POST['ticket_number'], (int)$_POST['questions_count'], (int)$_POST['time_minutes'],
             $_POST['status'] ?? 'active', $id]);
        $msg = 'Yangilandi';
    }
    if ($action === 'auto_generate') {
        $start = (int)($_POST['start_num'] ?? 1);
        $count = max(1, min(50, (int)($_POST['count'] ?? 10)));
        for ($i = 0; $i < $count; $i++) {
            $n = $start + $i;
            $exists = db()->fetch("SELECT id FROM tickets WHERE ticket_number=?", [$n]);
            if (!$exists) {
                db()->execute("INSERT INTO tickets (title_latin, title_cyrillic, ticket_number, questions_count, time_minutes)
                               VALUES (?,?,?,?,?)", ["Bilet $n", "Билет $n", $n, 20, 25]);
            }
        }
        $msg = "$count ta bilet generatsiya qilindi";
    }
}

$tickets = db()->fetchAll("SELECT t.*, (SELECT COUNT(*) FROM questions WHERE ticket_id=t.id) qc FROM tickets t ORDER BY t.ticket_number");

render_head(t('tickets'));
?>
<div class="layout">
<?php render_sidebar('admin','tickets'); ?>
<main class="main">
  <div class="page-header">
    <div class="page-title">🎫 <?= t('tickets') ?></div>
    <div class="flex gap-2">
      <button class="btn btn-light" onclick="document.getElementById('autoModal').style.display='flex'">⚡ Auto</button>
      <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">+ <?= t('add') ?></button>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <div class="card" style="padding:0">
    <div class="table-wrap" style="border:none;box-shadow:none">
      <table>
        <thead><tr><th>#</th><th><?= lang()==='uz_cyrillic' ? 'Номи' : 'Nomi' ?></th><th><?= lang()==='uz_cyrillic' ? 'Саволлар сони' : 'Savollar soni' ?></th><th><?= lang()==='uz_cyrillic' ? 'Вақт (мин)' : 'Vaqt (min)' ?></th><th><?= t('status') ?></th><th><?= t('actions') ?></th></tr></thead>
        <tbody>
          <?php foreach ($tickets as $t): ?>
          <tr>
            <td>#<?= $t['ticket_number'] ?></td>
            <td><strong><?= e($t['title_'.$lang_field]) ?></strong></td>
            <td><?= $t['qc'] ?> / <?= $t['questions_count'] ?></td>
            <td><?= $t['time_minutes'] ?></td>
            <td><span class="badge badge-<?= $t['status']==='active'?'success':'mute' ?>"><?= e($t['status']) ?></span></td>
            <td>
              <div class="flex" style="gap:4px">
                <button class="btn btn-light btn-sm" onclick='editTicket(<?= json_encode($t) ?>)'>✎</button>
                <form method="post" style="display:inline" onsubmit="return confirm('Ochirilsinmi?')">
                  <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                  <button class="btn btn-light btn-sm" style="color:var(--danger)">🗑</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($tickets)): ?>
            <tr><td colspan="6" class="text-center" style="padding:40px;color:var(--text-soft)"><?= lang()==='uz_cyrillic' ? 'Билетлар йўқ' : 'Biletlar yo\'q' ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>

<!-- Add modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:300;align-items:center;justify-content:center;padding:20px">
  <div class="card" style="max-width:520px;width:100%">
    <div class="flex justify-between items-center mb-3"><h3 style="font-size:18px;font-weight:700"><?= t('add') ?></h3>
      <button onclick="document.getElementById('addModal').style.display='none'" style="font-size:24px">×</button></div>
    <form method="post">
      <input type="hidden" name="action" value="add">
      <div class="form-group"><label class="form-label">Title (Lotin) *</label><input type="text" name="title_latin" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Title (Кирилл)</label><input type="text" name="title_cyrillic" class="form-control"></div>
      <div class="grid-3" style="gap:10px">
        <div class="form-group"><label class="form-label">№</label><input type="number" name="ticket_number" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Q count</label><input type="number" name="questions_count" class="form-control" value="20"></div>
        <div class="form-group"><label class="form-label">Min</label><input type="number" name="time_minutes" class="form-control" value="25"></div>
      </div>
      <button class="btn btn-primary btn-block"><?= t('save') ?></button>
    </form>
  </div>
</div>

<!-- Edit modal -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:300;align-items:center;justify-content:center;padding:20px">
  <div class="card" style="max-width:520px;width:100%">
    <div class="flex justify-between items-center mb-3"><h3 style="font-size:18px;font-weight:700"><?= t('edit') ?></h3>
      <button onclick="document.getElementById('editModal').style.display='none'" style="font-size:24px">×</button></div>
    <form method="post">
      <input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="te_id">
      <div class="form-group"><label class="form-label">Title (Lotin)</label><input type="text" name="title_latin" id="te_lat" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Title (Кирилл)</label><input type="text" name="title_cyrillic" id="te_cyr" class="form-control"></div>
      <div class="grid-3" style="gap:10px">
        <div class="form-group"><label class="form-label">№</label><input type="number" name="ticket_number" id="te_num" class="form-control"></div>
        <div class="form-group"><label class="form-label">Q count</label><input type="number" name="questions_count" id="te_qc" class="form-control"></div>
        <div class="form-group"><label class="form-label">Min</label><input type="number" name="time_minutes" id="te_tm" class="form-control"></div>
      </div>
      <div class="form-group"><label class="form-label">Status</label>
        <select name="status" id="te_st" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select>
      </div>
      <button class="btn btn-primary btn-block"><?= t('save') ?></button>
    </form>
  </div>
</div>

<!-- Auto generate -->
<div id="autoModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:300;align-items:center;justify-content:center;padding:20px">
  <div class="card" style="max-width:480px;width:100%">
    <div class="flex justify-between items-center mb-3"><h3 style="font-size:18px;font-weight:700">⚡ Auto Generate</h3>
      <button onclick="document.getElementById('autoModal').style.display='none'" style="font-size:24px">×</button></div>
    <form method="post">
      <input type="hidden" name="action" value="auto_generate">
      <div class="grid-2" style="gap:14px">
        <div class="form-group"><label class="form-label">Start №</label><input type="number" name="start_num" class="form-control" value="1" required></div>
        <div class="form-group"><label class="form-label">Count</label><input type="number" name="count" class="form-control" value="10" required></div>
      </div>
      <button class="btn btn-primary btn-block">Generate</button>
    </form>
  </div>
</div>

<script>
function editTicket(t){
  ['e_id','lat','cyr','num','qc','tm','st'].forEach((k,i)=>{});
  document.getElementById('te_id').value = t.id;
  document.getElementById('te_lat').value = t.title_latin;
  document.getElementById('te_cyr').value = t.title_cyrillic || '';
  document.getElementById('te_num').value = t.ticket_number;
  document.getElementById('te_qc').value = t.questions_count;
  document.getElementById('te_tm').value = t.time_minutes;
  document.getElementById('te_st').value = t.status;
  document.getElementById('editModal').style.display = 'flex';
}
['addModal','editModal','autoModal'].forEach(id => {
  const m = document.getElementById(id);
  m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; });
});
</script>
</body></html>
