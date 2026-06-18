<?php
require_once __DIR__ . '/../includes/bootstrap.php';
auth_class();
require_admin();

$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';
$default_image = setting('default_question_image', '/assets/images/default-question.svg');

$msg = flash('msg');
$err = flash('err');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redir = $_SERVER['REQUEST_URI'];
    if (!csrf_check()) { flash('err', 'CSRF xatosi'); header("Location: $redir"); exit; }

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    try {
        if ($action === 'delete' && $id) {
            db()->execute("DELETE FROM answers WHERE question_id=?", [$id]);
            db()->execute("DELETE FROM questions WHERE id=?", [$id]);
            flash('msg', t('deleted_success'));
        }
        elseif ($action === 'add' || ($action === 'edit' && $id)) {
            $ticket_id = (int)$_POST['ticket_id'] ?: null;
            $q_lat = Security::clean($_POST['question_latin'] ?? '', 2000);
            $q_cyr = Security::clean($_POST['question_cyrillic'] ?? '', 2000);
            if (!$q_cyr && $q_lat) $q_cyr = uz_latin_to_cyrillic($q_lat);
            if (!$q_lat) throw new Exception('Savol matni kerak');

            // Kamida 2 ta javob bo'lishi kerak
            $answer_count = 0;
            for ($i = 1; $i <= 4; $i++) {
                if (trim($_POST["answer_lat_$i"] ?? '')) $answer_count++;
            }
            if ($answer_count < 2) throw new Exception('Kamida 2 ta javob varianti kerak');

            $expl_lat = Security::clean($_POST['explanation_latin'] ?? '', 1000);
            $expl_cyr = Security::clean($_POST['explanation_cyrillic'] ?? '', 1000);
            if (!$expl_cyr && $expl_lat) $expl_cyr = uz_latin_to_cyrillic($expl_lat);

            $cat   = Security::clean($_POST['category'] ?? '', 100);
            $diff  = in_array($_POST['difficulty'] ?? '', ['easy','medium','hard']) ? $_POST['difficulty'] : 'medium';

            $image = $_POST['old_image'] ?? null;
            if (!empty($_FILES['image']['name'])) {
                $up = Security::upload_image($_FILES['image'], 'q');
                if ($up['ok']) { $image = $up['url']; @chmod(BASE_PATH . $up['url'], 0644); }
                else throw new Exception('Rasm: ' . $up['error']);
            }
            if (!empty($_POST['remove_image'])) $image = null;

            if ($action === 'add') {
                db()->execute(
                    "INSERT INTO questions (ticket_id, question_latin, question_cyrillic, image,
                     explanation_latin, explanation_cyrillic, category, difficulty)
                     VALUES (?,?,?,?,?,?,?,?)",
                    [$ticket_id, $q_lat, $q_cyr, $image, $expl_lat, $expl_cyr, $cat ?: null, $diff]);
                $qid = (int)db()->lastInsertId();
            } else {
                db()->execute(
                    "UPDATE questions SET ticket_id=?, question_latin=?, question_cyrillic=?, image=?,
                     explanation_latin=?, explanation_cyrillic=?, category=?, difficulty=? WHERE id=?",
                    [$ticket_id, $q_lat, $q_cyr, $image, $expl_lat, $expl_cyr, $cat ?: null, $diff, $id]);
                $qid = $id;
                db()->execute("DELETE FROM answers WHERE question_id=?", [$qid]);
            }

            $correct = (int)($_POST['correct'] ?? 1);
            for ($i = 1; $i <= 4; $i++) {
                $a_lat = Security::clean($_POST["answer_lat_$i"] ?? '', 500);
                $a_cyr = Security::clean($_POST["answer_cyr_$i"] ?? '', 500);
                if (!$a_lat) continue;
                if (!$a_cyr) $a_cyr = uz_latin_to_cyrillic($a_lat);
                $is_correct = ($i === $correct) ? 1 : 0;
                db()->execute(
                    "INSERT INTO answers (question_id, answer_latin, answer_cyrillic, is_correct, sort_order)
                     VALUES (?,?,?,?,?)", [$qid, $a_lat, $a_cyr, $is_correct, $i]);
            }
            flash('msg', $action === 'add' ? t('saved_success') : t('updated_success'));
        }
        elseif ($action === 'csv_import' && !empty($_FILES['csv']['tmp_name'])) {
            $h = fopen($_FILES['csv']['tmp_name'], 'r');
            if (!$h) throw new Exception('CSV o\'qilmadi');
            $count = 0;
            while ($row = fgetcsv($h)) {
                if (count($row) < 8) continue;
                db()->execute(
                    "INSERT INTO questions (ticket_id, question_latin, question_cyrillic) VALUES (?,?,?)",
                    [(int)$row[0] ?: null, $row[1], $row[2] ?: $row[1]]);
                $qid = db()->lastInsertId();
                for ($i = 0; $i < 4; $i++) {
                    db()->execute(
                        "INSERT INTO answers (question_id, answer_latin, answer_cyrillic, is_correct, sort_order)
                         VALUES (?,?,?,?,?)",
                        [$qid, $row[3+$i], $row[3+$i], ((int)$row[7] === $i+1)?1:0, $i+1]);
                }
                $count++;
            }
            fclose($h);
            flash('msg', "CSV: $count ta savol qo'shildi");
        }
        else { throw new Exception('Notog\'ri amal'); }
    } catch (Throwable $e) {
        flash('err', 'Xatolik: ' . $e->getMessage());
    }
    header("Location: $redir"); exit;
}

$search   = trim($_GET['q'] ?? '');
$ticket_f = (int)($_GET['ticket'] ?? 0);
$diff_f   = $_GET['diff'] ?? '';

$where = "1=1"; $params = [];
if ($search)   { $where .= " AND (q.question_latin LIKE ? OR q.question_cyrillic LIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; }
if ($ticket_f) { $where .= " AND q.ticket_id = ?"; $params[] = $ticket_f; }
if ($diff_f)   { $where .= " AND q.difficulty = ?"; $params[] = $diff_f; }

$questions = db()->fetchAll(
    "SELECT q.*, t.title_$lang_field tname, t.ticket_number
     FROM questions q LEFT JOIN tickets t ON q.ticket_id=t.id
     WHERE $where ORDER BY q.id DESC LIMIT 100", $params);
$tickets = db()->fetchAll("SELECT * FROM tickets ORDER BY ticket_number");

foreach ($questions as &$q) {
    $q['answers'] = db()->fetchAll(
        "SELECT * FROM answers WHERE question_id=? ORDER BY sort_order", [$q['id']]);
}
unset($q);

render_head(t('questions'));
?>
<div class="layout">
<?= panel_sidebar('admin', 'questions') ?>
<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title"><?= icon('help', 28) ?> <?= t('questions') ?></div>
      <div class="page-subtitle"><?= count($questions) ?> ta savol</div>
    </div>
    <div class="flex gap-2">
      <button type="button" class="btn btn-light" data-modal-open="csvModal"><?= icon('upload', 16) ?> CSV</button>
      <a href="/admin/savollar-form.php" class="btn btn-primary"><?= icon('plus', 16) ?> <?= t('add') ?></a>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= icon('check-circle',18) ?> <?= e($msg) ?></div><?php endif; ?>
  <?php if (!empty($err)): ?><div class="alert alert-danger"><?= icon('x-circle',18) ?> <?= e($err) ?></div><?php endif; ?>

  <form method="get" class="card mb-3" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end" data-no-loading>
    <div class="form-group flex-1" style="margin-bottom:0;min-width:180px">
      <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="<?= t('search') ?>...">
    </div>
    <div class="form-group" style="margin-bottom:0;min-width:160px">
      <select name="ticket" class="form-control">
        <option value="">— <?= t('all') ?> —</option>
        <?php foreach ($tickets as $t): ?>
          <option value="<?= $t['id'] ?>" <?= $ticket_f==$t['id']?'selected':'' ?>>#<?= $t['ticket_number'] ?> <?= e($t['title_'.$lang_field]) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin-bottom:0;min-width:120px">
      <select name="diff" class="form-control">
        <option value="">— Qiyinlik —</option>
        <option value="easy"   <?= $diff_f==='easy'?'selected':'' ?>>Oson</option>
        <option value="medium" <?= $diff_f==='medium'?'selected':'' ?>>O'rta</option>
        <option value="hard"   <?= $diff_f==='hard'?'selected':'' ?>>Qiyin</option>
      </select>
    </div>
    <button class="btn btn-primary"><?= icon('filter', 14) ?> <?= t('filter') ?></button>
    <?php if ($search || $ticket_f || $diff_f): ?><a href="?" class="btn btn-ghost"><?= icon('x',14) ?></a><?php endif; ?>
  </form>

  <?php if (empty($questions)): ?>
    <div class="card empty-state"><?= icon('help', 64) ?><h3 class="mt-2">Savollar topilmadi</h3></div>
  <?php else: ?>
  <div class="q-grid">
    <?php foreach ($questions as $q):
      $img = $q['image'] ?: $default_image;
      $diffCls = ['easy'=>'success','medium'=>'warning','hard'=>'danger'][$q['difficulty']] ?? 'mute';
    ?>
    <div class="q-card">
      <div class="q-image">
        <img src="<?= e($img) ?>" alt="" loading="lazy">
        <?php if (!$q['image']): ?><span class="q-default-badge">📷 Standart</span><?php endif; ?>
      </div>
      <div class="q-body">
        <div class="flex gap-1 mb-1 flex-wrap">
          <span class="badge badge-info">#<?= $q['id'] ?></span>
          <?php if ($q['tname']): ?><span class="badge badge-mute">Bilet #<?= $q['ticket_number'] ?></span><?php endif; ?>
          <span class="badge badge-<?= $diffCls ?>"><?= t($q['difficulty']) ?></span>
        </div>
        <p class="q-text"><?= e(mb_substr($q['question_'.$lang_field], 0, 130)) ?><?= mb_strlen($q['question_'.$lang_field]) > 130 ? '...' : '' ?></p>
        <ul class="q-answers">
          <?php foreach ($q['answers'] as $aIdx => $a):
            $letter = chr(65 + $aIdx);
            $isCorrect = !empty($a['is_correct']);
          ?>
          <li class="<?= $isCorrect?'is-correct':'' ?>">
            <span class="q-letter <?= $isCorrect?'is-correct':'' ?>"><?= $letter ?></span>
            <span><?= e(mb_substr($a['answer_'.$lang_field], 0, 60)) ?></span>
            <?php if ($isCorrect): ?><span style="color:var(--success);margin-left:auto"><?= icon('check', 14) ?></span><?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <div class="flex gap-1 mt-2">
          <button type="button" class="btn btn-light btn-sm"
            onclick='openQModal(<?= json_encode($q, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'>
            <?= icon('edit', 12) ?>
          </button>
          <form method="post" style="display:inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $q['id'] ?>">
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
.q-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:18px}
.q-card{background:#fff;border:1px solid var(--border);border-radius:var(--r-xl);overflow:hidden;transition:box-shadow .25s}
.q-card:hover{box-shadow:var(--shadow-md)}
.q-image{aspect-ratio:16/9;position:relative;overflow:hidden;background:var(--bg-soft)}
.q-image img{width:100%;height:100%;object-fit:cover}
.q-default-badge{position:absolute;bottom:8px;right:8px;background:rgba(15,23,42,.7);color:#fff;font-size:10px;padding:3px 8px;border-radius:6px}
.q-body{padding:16px}
.q-text{font-size:14px;font-weight:600;line-height:1.45;margin-bottom:12px;color:var(--text);
  display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.q-answers{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px}
.q-answers li{display:flex;align-items:center;gap:8px;padding:6px 10px;font-size:13px;color:var(--text-soft);
  background:var(--bg-soft);border-radius:6px;border:1px solid transparent}
.q-answers li.is-correct{background:var(--success-light);color:var(--success-dark);border-color:#A7F3D0}
.q-letter{flex-shrink:0;width:22px;height:22px;background:#fff;border:1px solid var(--border);
  border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--text-soft)}
.q-letter.is-correct{background:var(--success);color:#fff;border-color:var(--success)}
@media(max-width:640px){.q-grid{grid-template-columns:1fr}}
@media(max-width:880px){
  .q-grid{grid-template-columns:repeat(auto-fill,minmax(min(320px,100%),1fr));gap:12px}
  .q-card{border-radius:12px}
  .q-image{aspect-ratio:16/10}
  .q-body{padding:14px}
  .q-text{font-size:13px;margin-bottom:10px}
  .q-answers li{padding:6px 8px;font-size:12px;gap:6px}
  .q-letter{width:20px;height:20px;font-size:10px}
}
@media(max-width:480px){
  .q-grid{gap:10px}
  .q-body{padding:12px}
  .q-text{font-size:13px;-webkit-line-clamp:2}
}
</style>

<div id="qModal" class="modal-backdrop">
  <div class="modal modal-xl">
    <div class="modal-header">
      <h3 class="modal-title" id="qModalTitle"><?= t('add') ?></h3>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="q_action" value="add">
      <input type="hidden" name="id" id="q_id">
      <input type="hidden" name="old_image" id="q_old_image">
      <input type="hidden" name="remove_image" id="q_remove_image" value="0">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Savol rasmi <span class="text-mute">(ixtiyoriy)</span></label>
          <input type="file" name="image" accept="image/*" class="form-control" id="q_image">
          <div class="form-help">Bo'sh qoldirilsa standart rasm ko'rsatiladi</div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Bilet</label>
            <select name="ticket_id" id="q_ticket" class="form-control">
              <option value="">— Bilet tanlanmagan —</option>
              <?php foreach ($tickets as $t): ?>
                <option value="<?= $t['id'] ?>">#<?= $t['ticket_number'] ?> · <?= e($t['title_'.$lang_field]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Qiyinlik</label>
            <select name="difficulty" id="q_diff" class="form-control">
              <option value="easy">😊 Oson</option>
              <option value="medium" selected>🙂 O'rta</option>
              <option value="hard">😰 Qiyin</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Kategoriya</label>
          <input type="text" name="category" id="q_cat" class="form-control" maxlength="100" placeholder="Belgilar, Tezlik, Asoslar...">
        </div>

        <div class="form-group">
          <label class="form-label">Savol (Lotin) <span style="color:var(--danger)">*</span></label>
          <textarea name="question_latin" id="q_lat" class="form-control" required rows="3" maxlength="2000"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Савол (Кирилл) <small class="text-mute">— bo'sh = avto</small></label>
          <textarea name="question_cyrillic" id="q_cyr" class="form-control" rows="3" maxlength="2000"></textarea>
        </div>

        <h4 style="margin:18px 0 12px;font-size:14px;color:var(--text-soft);text-transform:uppercase;letter-spacing:.05em">
          Javob variantlari · <span class="text-mute" style="font-size:12px">To'g'ri javobni tanlang</span>
        </h4>

        <?php for ($i = 1; $i <= 4; $i++): ?>
        <div class="answer-item-form">
          <label class="answer-radio">
            <input type="radio" name="correct" value="<?= $i ?>" <?= $i==1?'checked':'' ?>>
            <span class="answer-letter-form"><?= chr(64 + $i) ?></span>
          </label>
          <div class="answer-inputs">
            <input type="text" name="answer_lat_<?= $i ?>" id="ans_lat_<?= $i ?>" class="form-control" placeholder="Latin (javob <?= $i ?>)" <?= $i<=2?'required':'' ?>>
            <input type="text" name="answer_cyr_<?= $i ?>" id="ans_cyr_<?= $i ?>" class="form-control" placeholder="Кирилл (avto)">
          </div>
        </div>
        <?php endfor; ?>

        <div class="form-row mt-3">
          <div class="form-group">
            <label class="form-label">Izoh (Lotin)</label>
            <textarea name="explanation_latin" id="q_expl_lat" class="form-control" rows="2" maxlength="1000"></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Изоҳ (Кирилл)</label>
            <textarea name="explanation_cyrillic" id="q_expl_cyr" class="form-control" rows="2" maxlength="1000"></textarea>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-modal-close><?= t('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> <?= t('save') ?></button>
      </div>
    </form>
  </div>
</div>

<style>
.answer-item-form{display:flex;gap:12px;margin-bottom:10px;align-items:flex-start;padding:10px;
  background:var(--bg-soft);border-radius:var(--r-md);border:1.5px solid transparent;transition:all .2s}
.answer-item-form:has(input[type=radio]:checked){background:var(--success-light);border-color:var(--success)}
.answer-radio{cursor:pointer;flex-shrink:0;display:flex;align-items:center}
.answer-radio input{display:none}
.answer-letter-form{width:36px;height:36px;border:2px solid var(--border);border-radius:50%;
  display:flex;align-items:center;justify-content:center;font-weight:700;background:#fff;color:var(--text-soft)}
.answer-radio input:checked + .answer-letter-form{background:var(--success);color:#fff;border-color:var(--success)}
.answer-inputs{flex:1;display:grid;grid-template-columns:1fr 1fr;gap:8px}
@media(max-width:640px){.answer-inputs{grid-template-columns:1fr}}
@media(max-width:880px){
  .answer-item-form{padding:10px;gap:10px;border-radius:10px}
  .answer-letter-form{width:32px;height:32px;font-size:13px}
  .answer-inputs{gap:6px}
  .answer-inputs input{min-height:44px;font-size:16px}
}
</style>

<div id="csvModal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">CSV Import</h3>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="csv_import">
      <div class="modal-body">
        <div class="alert alert-info" style="font-size:12px">
          Format: <code>ticket_id, q_lat, q_cyr, ans1, ans2, ans3, ans4, correct(1-4)</code>
        </div>
        <div class="form-group">
          <input type="file" name="csv" accept=".csv" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-modal-close><?= t('cancel') ?></button>
        <button type="submit" class="btn btn-primary">Import</button>
      </div>
    </form>
  </div>
</div>

<script>
function openQModal(q){
  const isEdit = !!q.id;
  document.getElementById('q_action').value = isEdit ? 'edit' : 'add';
  document.getElementById('q_id').value = q.id || '';
  document.getElementById('q_lat').value = q.question_latin || '';
  document.getElementById('q_cyr').value = q.question_cyrillic || '';
  document.getElementById('q_expl_lat').value = q.explanation_latin || '';
  document.getElementById('q_expl_cyr').value = q.explanation_cyrillic || '';
  document.getElementById('q_ticket').value = q.ticket_id || '';
  document.getElementById('q_diff').value = q.difficulty || 'medium';
  document.getElementById('q_cat').value = q.category || '';
  document.getElementById('q_old_image').value = q.image || '';
  document.getElementById('q_remove_image').value = '0';
  document.getElementById('qModalTitle').textContent = isEdit ? '<?= t('edit') ?>' : '<?= t('add') ?>';

  for (let i = 1; i <= 4; i++) {
    document.getElementById('ans_lat_'+i).value = '';
    document.getElementById('ans_cyr_'+i).value = '';
  }
  if (q.answers) {
    q.answers.forEach((a, idx) => {
      const i = idx + 1;
      if (i > 4) return;
      document.getElementById('ans_lat_'+i).value = a.answer_latin || '';
      document.getElementById('ans_cyr_'+i).value = a.answer_cyrillic || '';
      if (parseInt(a.is_correct, 10) === 1) {
        document.querySelector(`input[name=correct][value="${i}"]`).checked = true;
      }
    });
  }
  openModal('qModal');
}
</script>
<script><?= panel_js() ?></script>
</body></html>
