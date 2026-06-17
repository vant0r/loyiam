<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';
$msg = '';

// Action lar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        db()->execute("DELETE FROM questions WHERE id=?", [$id]);
        $msg = lang()==='uz_cyrillic' ? 'Ўчирилди' : 'O\'chirildi';
    }
    if ($action === 'add') {
        $ok = db()->execute("INSERT INTO questions (ticket_id, question_latin, question_cyrillic, category, difficulty)
                             VALUES (?,?,?,?,?)",
            [(int)$_POST['ticket_id'] ?: null, trim($_POST['question_latin']), trim($_POST['question_cyrillic']),
             trim($_POST['category']) ?: null, $_POST['difficulty'] ?? 'medium']);
        if ($ok) {
            $qid = db()->lastInsertId();
            for ($i = 1; $i <= 4; $i++) {
                $a_lat = trim($_POST["answer_lat_$i"] ?? '');
                $a_cyr = trim($_POST["answer_cyr_$i"] ?? '');
                if (!$a_lat) continue;
                $is_correct = ((int)($_POST['correct'] ?? 1) === $i) ? 1 : 0;
                db()->execute("INSERT INTO answers (question_id, answer_latin, answer_cyrillic, is_correct, sort_order)
                               VALUES (?,?,?,?,?)", [$qid, $a_lat, $a_cyr ?: $a_lat, $is_correct, $i]);
            }
            $msg = lang()==='uz_cyrillic' ? 'Қўшилди' : 'Qo\'shildi';
        }
    }
}

// CSV import (oddiy, bir nechta savol)
if (!empty($_FILES['csv']['tmp_name']) && ($_POST['action'] ?? '') === 'csv_import') {
    $h = fopen($_FILES['csv']['tmp_name'], 'r');
    if ($h) {
        $count = 0;
        while ($row = fgetcsv($h)) {
            // CSV format: ticket_id, question_lat, question_cyr, ans1, ans2, ans3, ans4, correct(1-4)
            if (count($row) < 8) continue;
            db()->execute("INSERT INTO questions (ticket_id, question_latin, question_cyrillic) VALUES (?,?,?)",
                [(int)$row[0] ?: null, $row[1], $row[2] ?: $row[1]]);
            $qid = db()->lastInsertId();
            for ($i=0; $i<4; $i++) {
                db()->execute("INSERT INTO answers (question_id, answer_latin, answer_cyrillic, is_correct, sort_order)
                               VALUES (?,?,?,?,?)",
                    [$qid, $row[3+$i], $row[3+$i], ((int)$row[7] === $i+1)?1:0, $i+1]);
            }
            $count++;
        }
        fclose($h);
        $msg = "CSV: $count savol qo'shildi";
    }
}

$search = trim($_GET['q'] ?? '');
$ticket_f = (int)($_GET['ticket'] ?? 0);

$where = "WHERE 1=1"; $params = [];
if ($search) { $where .= " AND (question_latin LIKE ? OR question_cyrillic LIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; }
if ($ticket_f) { $where .= " AND ticket_id = ?"; $params[] = $ticket_f; }

$questions = db()->fetchAll("SELECT q.*, t.title_$lang_field tname FROM questions q LEFT JOIN tickets t ON q.ticket_id=t.id $where ORDER BY q.id DESC LIMIT 100", $params);
$tickets = db()->fetchAll("SELECT * FROM tickets ORDER BY ticket_number");
foreach ($questions as &$q) {
    $q['answers'] = db()->fetchAll("SELECT * FROM answers WHERE question_id=? ORDER BY sort_order", [$q['id']]);
}
unset($q);

render_head(t('questions'));
?>
<div class="layout">
<?php render_sidebar('admin','questions'); ?>
<main class="main">
  <div class="page-header">
    <div class="page-title">❓ <?= t('questions') ?></div>
    <div class="flex gap-2">
      <button class="btn btn-light" onclick="document.getElementById('csvModal').style.display='flex'">📥 CSV Import</button>
      <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">+ <?= t('add') ?></button>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <form method="get" class="card mb-3" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
    <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0">
      <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="<?= t('search') ?>...">
    </div>
    <div class="form-group" style="margin-bottom:0;min-width:180px">
      <select name="ticket" class="form-control">
        <option value="">— <?= lang()==='uz_cyrillic' ? 'Барча билетлар' : 'Barcha biletlar' ?> —</option>
        <?php foreach ($tickets as $t): ?>
          <option value="<?= $t['id'] ?>" <?= $ticket_f==$t['id']?'selected':'' ?>><?= e($t['title_'.$lang_field]) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary"><?= t('search') ?></button>
  </form>

  <div class="card" style="padding:0">
    <div class="table-wrap" style="border:none;box-shadow:none">
      <table>
        <thead><tr><th>#</th><th><?= lang()==='uz_cyrillic' ? 'Савол' : 'Savol' ?></th><th><?= lang()==='uz_cyrillic' ? 'Тўғри жавоб' : 'To\'g\'ri javob' ?></th><th><?= lang()==='uz_cyrillic' ? 'Билет' : 'Bilet' ?></th><th><?= t('actions') ?></th></tr></thead>
        <tbody>
          <?php foreach ($questions as $q):
            $correct = '';
            foreach ($q['answers'] as $a) if ($a['is_correct']) { $correct = $a['answer_'.$lang_field]; break; }
          ?>
          <tr>
            <td>#<?= $q['id'] ?></td>
            <td style="max-width:400px"><?= e(mb_substr($q['question_'.$lang_field], 0, 100)) ?><?= mb_strlen($q['question_'.$lang_field])>100?'...':'' ?></td>
            <td><span class="badge badge-success">✓ <?= e(mb_substr($correct, 0, 50)) ?></span></td>
            <td><?= e($q['tname'] ?? '—') ?></td>
            <td>
              <form method="post" style="display:inline" onsubmit="return confirm('Ochirilsinmi?')">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $q['id'] ?>">
                <button class="btn btn-light btn-sm" style="color:var(--danger)">🗑</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($questions)): ?>
            <tr><td colspan="5" class="text-center" style="padding:40px;color:var(--text-soft)"><?= lang()==='uz_cyrillic' ? 'Саволлар топилмади' : 'Savollar topilmadi' ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>

<!-- Add modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:300;align-items:center;justify-content:center;padding:20px">
  <div class="card" style="max-width:680px;width:100%;max-height:90vh;overflow-y:auto">
    <div class="flex justify-between items-center mb-3"><h3 style="font-size:18px;font-weight:700"><?= t('add') ?></h3>
      <button onclick="document.getElementById('addModal').style.display='none'" style="font-size:24px">×</button></div>
    <form method="post">
      <input type="hidden" name="action" value="add">
      <div class="grid-2" style="gap:14px">
        <div class="form-group">
          <label class="form-label"><?= lang()==='uz_cyrillic' ? 'Билет' : 'Bilet' ?></label>
          <select name="ticket_id" class="form-control"><option value="">— —</option>
            <?php foreach ($tickets as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['title_'.$lang_field]) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label"><?= lang()==='uz_cyrillic' ? 'Қийинлик' : 'Qiyinlik' ?></label>
          <select name="difficulty" class="form-control">
            <option value="easy">Easy</option><option value="medium" selected>Medium</option><option value="hard">Hard</option>
          </select>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Savol (Lotin) *</label><textarea name="question_latin" class="form-control" required rows="2"></textarea></div>
      <div class="form-group"><label class="form-label">Савол (Кирилл)</label><textarea name="question_cyrillic" class="form-control" rows="2"></textarea></div>
      <div class="form-group"><label class="form-label"><?= lang()==='uz_cyrillic' ? 'Категория' : 'Kategoriya' ?></label><input type="text" name="category" class="form-control"></div>

      <h4 style="font-weight:700;margin:14px 0"><?= lang()==='uz_cyrillic' ? 'Жавоб вариантлари' : 'Javob variantlari' ?></h4>
      <?php for ($i=1; $i<=4; $i++): ?>
        <div class="card mb-2" style="background:var(--bg-soft);padding:14px">
          <div class="flex items-center mb-1" style="gap:10px">
            <input type="radio" name="correct" value="<?= $i ?>" <?= $i==1?'checked':'' ?>>
            <strong><?= lang()==='uz_cyrillic' ? 'Жавоб' : 'Javob' ?> <?= $i ?></strong>
          </div>
          <div class="grid-2" style="gap:10px">
            <input type="text" name="answer_lat_<?= $i ?>" class="form-control" placeholder="Latin" <?= $i<=2?'required':'' ?>>
            <input type="text" name="answer_cyr_<?= $i ?>" class="form-control" placeholder="Кирилл">
          </div>
        </div>
      <?php endfor; ?>
      <button class="btn btn-primary btn-block"><?= t('save') ?></button>
    </form>
  </div>
</div>

<!-- CSV Modal -->
<div id="csvModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:300;align-items:center;justify-content:center;padding:20px">
  <div class="card" style="max-width:480px;width:100%">
    <div class="flex justify-between items-center mb-3"><h3 style="font-size:18px;font-weight:700">CSV Import</h3>
      <button onclick="document.getElementById('csvModal').style.display='none'" style="font-size:24px">×</button></div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="csv_import">
      <div class="alert alert-info" style="font-size:12px">
        Format: <code>ticket_id, question_lat, question_cyr, ans1, ans2, ans3, ans4, correct(1-4)</code>
      </div>
      <div class="form-group"><input type="file" name="csv" accept=".csv" class="form-control" required></div>
      <button class="btn btn-primary btn-block">Import</button>
    </form>
  </div>
</div>

<script>
['addModal','csvModal'].forEach(id => {
  const m = document.getElementById(id);
  m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; });
});
</script>
</body></html>
