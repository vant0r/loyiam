<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$question = null;
$answers = [];
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';
$default_image = setting('default_question_image', '/assets/images/default-question.svg');

if ($isEdit) {
    $question = db()->fetch("SELECT * FROM questions WHERE id = ?", [$id]);
    if (!$question) { flash('err', 'Topilmadi'); header('Location: /admin/savollar.php'); exit; }
    $answers = db()->fetchAll("SELECT * FROM answers WHERE question_id=? ORDER BY sort_order", [$id]);
}

$tickets = db()->fetchAll("SELECT * FROM tickets ORDER BY ticket_number");

$msg = flash('msg');
$err = flash('err');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = $isEdit ? "/admin/savollar-form.php?id=$id" : "/admin/savollar-form.php";
    if (!csrf_check()) { flash('err', 'CSRF xatosi'); header("Location: $back"); exit; }

    try {
        $ticket_id = (int)$_POST['ticket_id'] ?: null;
        $q_lat = Security::clean($_POST['question_latin'] ?? '', 2000);
        $q_cyr = Security::clean($_POST['question_cyrillic'] ?? '', 2000);
        if (!$q_cyr && $q_lat) $q_cyr = uz_latin_to_cyrillic($q_lat);
        if (!$q_lat) throw new Exception('Savol matni kerak');

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

        $image = $question['image'] ?? null;
        if (!empty($_FILES['image']['name'])) {
            $up = Security::upload_image($_FILES['image'], 'q');
            if ($up['ok']) { $image = $up['url']; @chmod(BASE_PATH . $up['url'], 0644); }
            else throw new Exception('Rasm: ' . $up['error']);
        }
        if (!empty($_POST['remove_image'])) $image = null;

        if ($isEdit) {
            db()->execute(
                "UPDATE questions SET ticket_id=?, question_latin=?, question_cyrillic=?, image=?,
                 explanation_latin=?, explanation_cyrillic=?, category=?, difficulty=? WHERE id=?",
                [$ticket_id, $q_lat, $q_cyr, $image, $expl_lat, $expl_cyr, $cat ?: null, $diff, $id]);
            $qid = $id;
            db()->execute("DELETE FROM answers WHERE question_id=?", [$qid]);
        } else {
            db()->execute(
                "INSERT INTO questions (ticket_id, question_latin, question_cyrillic, image,
                 explanation_latin, explanation_cyrillic, category, difficulty)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$ticket_id, $q_lat, $q_cyr, $image, $expl_lat, $expl_cyr, $cat ?: null, $diff]);
            $qid = (int)db()->lastInsertId();
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
        flash('msg', $isEdit ? t('updated_success') : t('saved_success'));
        header("Location: /admin/savollar.php"); exit;
    } catch (Throwable $e) {
        flash('err', 'Xatolik: ' . $e->getMessage());
        header("Location: $back"); exit;
    }
}

// Default correct answer
$correct_idx = 1;
foreach ($answers as $i => $a) {
    if ($a['is_correct']) { $correct_idx = $i + 1; break; }
}

render_head($isEdit ? t('edit') : t('add'));
?>
<div class="layout">
<?php render_sidebar('admin','questions'); ?>
<main class="main">
  <div class="page-header">
    <div>
      <a href="/admin/savollar.php" class="text-soft" style="font-size:13px;display:inline-flex;align-items:center;gap:4px;text-decoration:none">
        <?= icon('arrow-left', 14) ?> <?= t('questions') ?>
      </a>
      <div class="page-title mt-1"><?= icon('help', 28) ?> <?= $isEdit ? t('edit') : t('add') ?> <?= t('questions') ?></div>
    </div>
  </div>

  <?php if ($err): ?><div class="alert alert-danger"><?= icon('x-circle',18) ?> <?= e($err) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" action="">
    <?= csrf_field() ?>

    <div class="card">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">🖼️ Savol rasmi (ixtiyoriy)</h3>
      <div class="image-up" id="imageUp">
        <img id="imagePrev" src="<?= e($question['image'] ?? $default_image) ?>">
        <div class="image-overlay"><?= icon('upload', 32) ?> <strong>Rasm tanlash</strong></div>
        <input type="file" name="image" accept="image/*" id="imageInput" hidden>
      </div>
      <div class="form-help mt-1">Bo'sh qoldirilsa standart rasm ishlatiladi</div>
    </div>

    <div class="card mt-3">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">❓ Savol</h3>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Bilet</label>
          <select name="ticket_id" class="form-control">
            <option value="">— Bilet tanlanmagan —</option>
            <?php foreach ($tickets as $t): ?>
              <option value="<?= $t['id'] ?>" <?= ($question['ticket_id']??0)==$t['id']?'selected':'' ?>>
                #<?= $t['ticket_number'] ?> · <?= e($t['title_'.$lang_field]) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Qiyinlik</label>
          <select name="difficulty" class="form-control">
            <option value="easy"   <?= ($question['difficulty']??'')==='easy'?'selected':'' ?>>😊 Oson</option>
            <option value="medium" <?= ($question['difficulty']??'medium')==='medium'?'selected':'' ?>>🙂 O'rta</option>
            <option value="hard"   <?= ($question['difficulty']??'')==='hard'?'selected':'' ?>>😰 Qiyin</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Kategoriya</label>
        <input type="text" name="category" class="form-control" maxlength="100"
               value="<?= e($question['category'] ?? '') ?>" placeholder="Belgilar, Tezlik...">
      </div>
      <div class="form-group">
        <label class="form-label">Savol (Lotin) <span style="color:var(--danger)">*</span></label>
        <textarea name="question_latin" class="form-control" required rows="3" maxlength="2000"><?= e($question['question_latin'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Савол (Кирилл) <small class="text-mute">— bo'sh = avto</small></label>
        <textarea name="question_cyrillic" class="form-control" rows="3" maxlength="2000"><?= e($question['question_cyrillic'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="card mt-3">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">✓ Javob variantlari</h3>
      <p class="text-soft" style="font-size:13px;margin-bottom:14px">To'g'ri javobni tanlang (kamida 2 ta variant kerak)</p>

      <?php for ($i = 1; $i <= 4; $i++):
        $a = $answers[$i-1] ?? null;
      ?>
      <div class="answer-item-form">
        <label class="answer-radio">
          <input type="radio" name="correct" value="<?= $i ?>" <?= $correct_idx === $i ?'checked':'' ?>>
          <span class="answer-letter-form"><?= chr(64 + $i) ?></span>
        </label>
        <div class="answer-inputs">
          <input type="text" name="answer_lat_<?= $i ?>" class="form-control"
                 placeholder="Latin (javob <?= $i ?>)" <?= $i<=2?'required':'' ?>
                 value="<?= e($a['answer_latin'] ?? '') ?>">
          <input type="text" name="answer_cyr_<?= $i ?>" class="form-control"
                 placeholder="Кирилл (avto)"
                 value="<?= e($a['answer_cyrillic'] ?? '') ?>">
        </div>
      </div>
      <?php endfor; ?>
    </div>

    <div class="card mt-3">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">💡 Izoh (ixtiyoriy)</h3>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Izoh (Lotin)</label>
          <textarea name="explanation_latin" class="form-control" rows="3" maxlength="1000"><?= e($question['explanation_latin'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Изоҳ (Кирилл)</label>
          <textarea name="explanation_cyrillic" class="form-control" rows="3" maxlength="1000"><?= e($question['explanation_cyrillic'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <div class="form-actions">
      <a href="/admin/savollar.php" class="btn btn-light"><?= t('cancel') ?></a>
      <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> <?= t('save') ?></button>
    </div>
  </form>
</main>
</div>

<style>
.form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:24px;padding:18px 0}
@media(max-width:640px){.form-actions{flex-direction:column-reverse}.form-actions .btn{width:100%}}

.image-up{position:relative;aspect-ratio:16/9;border:2px dashed var(--border);border-radius:var(--r-lg);
  overflow:hidden;cursor:pointer;background:var(--bg-soft);max-height:300px;margin:0 auto}
.image-up:hover{border-color:var(--primary)}
.image-up img{width:100%;height:100%;object-fit:cover;display:block}
.image-overlay{position:absolute;inset:0;background:rgba(15,23,42,.5);color:#fff;display:flex;flex-direction:column;
  align-items:center;justify-content:center;gap:6px;opacity:0;transition:opacity .25s}
.image-up:hover .image-overlay{opacity:1}

.answer-item-form{display:flex;gap:12px;margin-bottom:10px;align-items:flex-start;padding:10px;
  background:var(--bg-soft);border-radius:var(--r-md);border:1.5px solid transparent}
.answer-item-form:has(input[type=radio]:checked){background:var(--success-light);border-color:var(--success)}
.answer-radio{cursor:pointer;flex-shrink:0;display:flex;align-items:center}
.answer-radio input{display:none}
.answer-letter-form{width:36px;height:36px;border:2px solid var(--border);border-radius:50%;
  display:flex;align-items:center;justify-content:center;font-weight:700;background:#fff}
.answer-radio input:checked + .answer-letter-form{background:var(--success);color:#fff;border-color:var(--success)}
.answer-inputs{flex:1;display:grid;grid-template-columns:1fr 1fr;gap:8px}
@media(max-width:640px){.answer-inputs{grid-template-columns:1fr}}
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
</body></html>
