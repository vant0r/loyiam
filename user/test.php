<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$u = current_user();
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

// ============================================================
// Test boshlash yoki davom ettirish
// ============================================================
$attempt_id = (int)($_GET['attempt'] ?? 0);
$ticket_id  = (int)($_GET['ticket'] ?? 0);
$mode       = $_GET['mode'] ?? 'classic'; // classic | quick | mistakes | category

// Yangi test boshlash
if (!$attempt_id && $ticket_id) {
    $ticket = db()->fetch("SELECT * FROM tickets WHERE id=? AND status='active'", [$ticket_id]);
    if (!$ticket) { header('Location: /user/testlar.php'); exit; }

    db()->execute(
        "INSERT INTO test_attempts (user_id, ticket_id, total_questions, status) VALUES (?,?,?, 'in_progress')",
        [$u['id'], $ticket_id, $ticket['questions_count']]
    );
    $attempt_id = (int)db()->lastInsertId();
    audit('test_started', "Bilet #{$ticket['ticket_number']}");
    header("Location: /user/test.php?attempt=$attempt_id");
    exit;
}

// Mavjud urinishni yuklash
$attempt = db()->fetch("SELECT a.*, t.title_$lang_field title, t.time_minutes, t.questions_count
                        FROM test_attempts a LEFT JOIN tickets t ON a.ticket_id=t.id
                        WHERE a.id=? AND a.user_id=?", [$attempt_id, $u['id']]);

if (!$attempt) { header('Location: /user/testlar.php'); exit; }

// Tugagan testni qayta ko'rish — natija sahifasiga
if ($attempt['status'] === 'completed') {
    header("Location: /user/test-result.php?attempt=$attempt_id");
    exit;
}

// Vaqt tekshirish
$started = strtotime($attempt['started_at']);
$timeLimit = max(1, (int)$attempt['time_minutes']) * 60;
$elapsed = time() - $started;
$remaining = max(0, $timeLimit - $elapsed);

if ($remaining <= 0) {
    self_finish_test($attempt_id, $u['id'], $lang_field);
    header("Location: /user/test-result.php?attempt=$attempt_id&expired=1");
    exit;
}

// ============================================================
// AJAX: javobni saqlash
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_answer') {
    csrf_require();
    header('Content-Type: application/json');

    $qid = (int)($_POST['question_id'] ?? 0);
    $aid = (int)($_POST['answer_id'] ?? 0);

    if (!$qid) { echo json_encode(['ok' => false]); exit; }

    $q = db()->fetch("SELECT id FROM questions WHERE id=?", [$qid]);
    if (!$q) { echo json_encode(['ok' => false]); exit; }

    // Variant haqiqiyligi
    $is_correct = 0;
    if ($aid) {
        $ans = db()->fetch("SELECT is_correct FROM answers WHERE id=? AND question_id=?", [$aid, $qid]);
        if (!$ans) { echo json_encode(['ok' => false]); exit; }
        $is_correct = (int)$ans['is_correct'];
    }

    // Mavjud javobni o'chirib, qaytadan qo'shish (foydalanuvchi javobni o'zgartirishi mumkin)
    db()->execute("DELETE FROM test_answers WHERE attempt_id=? AND question_id=?", [$attempt_id, $qid]);
    db()->execute(
        "INSERT INTO test_answers (attempt_id, question_id, answer_id, is_correct) VALUES (?,?,?,?)",
        [$attempt_id, $qid, $aid ?: null, $is_correct]
    );
    echo json_encode(['ok' => true]);
    exit;
}

// ============================================================
// Testni tugatish
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'finish') {
    csrf_require();
    self_finish_test($attempt_id, $u['id'], $lang_field);
    header("Location: /user/test-result.php?attempt=$attempt_id");
    exit;
}

// Testni tugatish funksiyasi
function self_finish_test(int $aid, int $uid, string $lf): void {
    $att = db()->fetch("SELECT * FROM test_attempts WHERE id=? AND user_id=?", [$aid, $uid]);
    if (!$att || $att['status'] === 'completed') return;

    $stats = db()->fetch(
        "SELECT COUNT(*) total, COALESCE(SUM(is_correct),0) correct
         FROM test_answers WHERE attempt_id=?", [$aid]);

    $total   = (int)$att['total_questions'];
    $correct = (int)($stats['correct'] ?? 0);
    $wrong   = max(0, ((int)($stats['total'] ?? 0)) - $correct);
    $percent = $total ? round($correct / $total * 100, 2) : 0;
    $time    = time() - strtotime($att['started_at']);

    db()->execute(
        "UPDATE test_attempts SET correct_answers=?, wrong_answers=?, score_percent=?,
            time_spent=?, status='completed', finished_at=NOW() WHERE id=?",
        [$correct, $wrong, $percent, $time, $aid]);
    audit('test_completed', "Natija: $percent% ($correct/$total)", 'info', $uid);
}

// ============================================================
// Savollarni yuklash (1 marta — bilet bo'yicha)
// ============================================================
$questions = db()->fetchAll(
    "SELECT id, question_$lang_field q, image, category, difficulty
     FROM questions WHERE ticket_id=? AND status='active'
     ORDER BY id LIMIT ?",
    [$attempt['ticket_id'], (int)$attempt['total_questions']]);

// Agar bilet'da savollar yetarli bo'lmasa, boshqa biletlardan to'ldiramiz (demo uchun)
if (count($questions) < $attempt['total_questions']) {
    $extra = db()->fetchAll(
        "SELECT id, question_$lang_field q, image, category, difficulty
         FROM questions WHERE status='active' AND ticket_id != ?
         ORDER BY RAND() LIMIT ?",
        [$attempt['ticket_id'], (int)$attempt['total_questions'] - count($questions)]);
    $questions = array_merge($questions, $extra);
}

if (empty($questions)) {
    render_head('Test');
    render_header();
    echo '<div class="container section text-center">';
    echo '<h2>Savollar mavjud emas</h2>';
    echo '<p>Iltimos, admin bilan bog\'laning yoki keyinroq qayta urinib ko\'ring.</p>';
    echo '<a href="/user/testlar.php" class="btn btn-primary">Ortga</a>';
    echo '</div>';
    render_footer();
    exit;
}

// Har bir savol uchun variantlar
foreach ($questions as &$q) {
    $q['answers'] = db()->fetchAll(
        "SELECT id, answer_$lang_field as txt FROM answers WHERE question_id=? ORDER BY sort_order, id",
        [$q['id']]);
}
unset($q);

// Allaqachon javob berilgan savollar
$saved = db()->fetchAll("SELECT question_id, answer_id FROM test_answers WHERE attempt_id=?", [$attempt_id]);
$savedMap = [];
foreach ($saved as $s) $savedMap[$s['question_id']] = (int)$s['answer_id'];

render_head(t('test_taking'), ['extra_head' => '<style>
/* Test-specific styles */
.test-layout{display:grid;grid-template-columns:1fr 280px;gap:24px;max-width:1300px;margin:0 auto;padding:20px}
.test-main{background:#fff;border-radius:var(--r-xl);padding:32px;box-shadow:var(--shadow-sm);min-height:600px;display:flex;flex-direction:column}
.test-side{position:sticky;top:20px;align-self:flex-start;display:flex;flex-direction:column;gap:16px}
.test-header{display:flex;justify-content:space-between;align-items:center;padding:14px 20px;background:#fff;
  border-radius:var(--r-xl);box-shadow:var(--shadow-sm);margin-bottom:20px;flex-wrap:wrap;gap:12px}
.test-timer{display:flex;align-items:center;gap:10px;padding:10px 18px;background:var(--primary);color:#fff;
  border-radius:var(--r-md);font-weight:700;font-size:18px;font-variant-numeric:tabular-nums}
.test-timer.warning{background:var(--warning);animation:pulse 1.5s infinite}
.test-timer.danger{background:var(--danger);animation:pulse .8s infinite}
.test-progress{flex:1;min-width:200px}
.q-num{font-size:14px;color:var(--text-soft);margin-bottom:8px;display:flex;justify-content:space-between}
.q-text{font-size:18px;font-weight:600;line-height:1.5;margin-bottom:24px;color:var(--text)}
.q-image{margin-bottom:24px;text-align:center}
.q-image img{max-height:300px;border-radius:var(--r-md);border:1px solid var(--border)}
.answer-list{display:flex;flex-direction:column;gap:12px;flex:1}
.answer-item{padding:16px 20px;border:2px solid var(--border);border-radius:var(--r-md);
  cursor:pointer;display:flex;align-items:flex-start;gap:14px;transition:all .15s;background:#fff}
.answer-item:hover{border-color:var(--primary-300);background:var(--primary-50)}
.answer-item.selected{border-color:var(--primary);background:var(--primary-50);box-shadow:0 0 0 3px var(--primary-100)}
.answer-letter{flex-shrink:0;width:32px;height:32px;border:2px solid var(--border);border-radius:50%;
  display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:var(--text-soft);
  background:#fff;transition:all .15s}
.answer-item.selected .answer-letter{background:var(--primary);color:#fff;border-color:var(--primary)}
.answer-text{flex:1;line-height:1.5;font-size:15px}
.test-actions{display:flex;justify-content:space-between;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--border);flex-wrap:wrap}

/* Side navigator */
.nav-card{background:#fff;border-radius:var(--r-xl);padding:18px;box-shadow:var(--shadow-sm)}
.nav-title{font-size:14px;font-weight:700;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center}
.nav-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:6px}
.nav-item{aspect-ratio:1;display:flex;align-items:center;justify-content:center;border-radius:6px;
  border:1.5px solid var(--border);background:#fff;font-size:12px;font-weight:600;cursor:pointer;
  color:var(--text-soft);transition:all .15s}
.nav-item:hover{border-color:var(--primary);color:var(--primary)}
.nav-item.current{border-color:var(--primary);background:var(--primary);color:#fff;transform:scale(1.05)}
.nav-item.answered{background:var(--success-light);border-color:var(--success);color:var(--success-dark)}
.nav-item.marked{background:var(--warning-light);border-color:var(--warning);color:var(--warning-dark);position:relative}
.nav-item.marked::after{content:"";position:absolute;top:-3px;right:-3px;width:8px;height:8px;background:var(--warning);border-radius:50%}
.nav-legend{margin-top:14px;display:flex;flex-direction:column;gap:6px;font-size:12px;color:var(--text-soft)}
.nav-legend > div{display:flex;align-items:center;gap:8px}
.nav-legend .dot{width:12px;height:12px;border-radius:3px}
.save-indicator{font-size:11px;color:var(--text-mute);display:flex;align-items:center;gap:4px}
.save-indicator.saving{color:var(--primary)}
.save-indicator.saved{color:var(--success-dark)}

@media (max-width: 900px){
  .test-layout{grid-template-columns:1fr;padding:12px}
  .test-side{position:static;order:-1}
  .nav-grid{grid-template-columns:repeat(10,1fr)}
  .test-main{padding:20px}
  .q-text{font-size:16px}
}
</style>']);
?>

<div class="test-header container">
  <div class="logo">
    <span class="logo-icon">VP</span>
    <span><?= e($attempt['title']) ?></span>
  </div>
  <div class="test-progress">
    <div class="q-num">
      <span><?= t('question') ?> <span id="curQ">1</span>/<span><?= count($questions) ?></span></span>
      <span class="save-indicator" id="saveIndicator"></span>
    </div>
    <div class="progress"><div class="progress-bar" id="progBar" style="width:0%"></div></div>
  </div>
  <div class="test-timer" id="timer" data-remaining="<?= $remaining ?>">
    <?= icon('clock', 18) ?>
    <span id="timerText">--:--</span>
  </div>
  <button class="btn btn-light btn-sm" onclick="confirmExit()"><?= t('exit_test') ?></button>
</div>

<div class="test-layout container">
  <!-- Asosiy savol bloki -->
  <div class="test-main fade-in">
    <?php foreach ($questions as $idx => $q): ?>
    <div class="q-block" data-q-index="<?= $idx ?>" data-q-id="<?= $q['id'] ?>" style="<?= $idx === 0 ? '' : 'display:none' ?>;flex:1;display:<?= $idx===0?'flex':'none' ?>;flex-direction:column">
      <div class="q-num">
        <span><strong><?= t('question') ?> <?= $idx+1 ?></strong> <?= t('of') ?> <?= count($questions) ?></span>
        <span style="display:flex;gap:6px">
          <?php if (!empty($q['category'])): ?><span class="badge badge-info"><?= e($q['category']) ?></span><?php endif; ?>
          <?php if (!empty($q['difficulty'])):
            $dc = ['easy'=>'success','medium'=>'warning','hard'=>'danger'][$q['difficulty']] ?? 'mute';
          ?><span class="badge badge-<?= $dc ?>"><?= t($q['difficulty']) ?></span><?php endif; ?>
        </span>
      </div>
      <h2 class="q-text"><?= nl2br(e($q['q'])) ?></h2>
      <?php if (!empty($q['image'])): ?>
        <div class="q-image"><img src="<?= e($q['image']) ?>" alt=""></div>
      <?php endif; ?>
      <div class="answer-list">
        <?php foreach ($q['answers'] as $aIdx => $a):
          $letter = chr(65 + $aIdx);
          $selected = ($savedMap[$q['id']] ?? 0) == $a['id'];
        ?>
        <label class="answer-item <?= $selected ? 'selected' : '' ?>" onclick="selectAnswer(this, <?= $q['id'] ?>, <?= $a['id'] ?>, <?= $idx ?>)">
          <input type="radio" name="answer_<?= $q['id'] ?>" value="<?= $a['id'] ?>" <?= $selected?'checked':'' ?> style="display:none">
          <div class="answer-letter"><?= $letter ?></div>
          <div class="answer-text"><?= nl2br(e($a['txt'])) ?></div>
        </label>
        <?php endforeach; ?>
      </div>

      <div class="test-actions">
        <button class="btn btn-light" onclick="prevQ()" <?= $idx===0?'disabled':'' ?>>
          <?= icon('arrow-left', 16) ?> <?= t('back') ?>
        </button>
        <button class="btn btn-ghost" onclick="toggleMark(<?= $idx ?>)" id="markBtn-<?= $idx ?>">
          <?= icon('bookmark', 16) ?> <?= t('mark_review') ?>
        </button>
        <?php if ($idx === count($questions)-1): ?>
          <button class="btn btn-success" onclick="finishTest()">
            <?= t('finish_test') ?> <?= icon('check', 16) ?>
          </button>
        <?php else: ?>
          <button class="btn btn-primary" onclick="nextQ()">
            <?= t('next') ?> <?= icon('arrow-right', 16) ?>
          </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Yon panel -->
  <div class="test-side">
    <div class="nav-card">
      <div class="nav-title">
        <span><?= t('questions') ?></span>
        <span style="font-size:12px;color:var(--text-soft)">
          <span id="answeredCount">0</span>/<?= count($questions) ?>
        </span>
      </div>
      <div class="nav-grid" id="navGrid">
        <?php foreach ($questions as $idx => $q): ?>
        <button class="nav-item <?= $idx===0?'current':'' ?> <?= isset($savedMap[$q['id']]) && $savedMap[$q['id']] ? 'answered' : '' ?>"
                data-idx="<?= $idx ?>" onclick="goTo(<?= $idx ?>)"><?= $idx+1 ?></button>
        <?php endforeach; ?>
      </div>
      <div class="nav-legend">
        <div><span class="dot" style="background:var(--primary)"></span> <?= lang()==='uz_cyrillic' ? 'Жорий' : 'Joriy' ?></div>
        <div><span class="dot" style="background:var(--success-light);border:1px solid var(--success)"></span> <?= t('answered') ?></div>
        <div><span class="dot" style="background:var(--warning-light);border:1px solid var(--warning)"></span> <?= t('marked') ?></div>
        <div><span class="dot" style="background:#fff;border:1px solid var(--border)"></span> <?= t('unanswered') ?></div>
      </div>
    </div>

    <button class="btn btn-success btn-block btn-lg" onclick="finishTest()">
      <?= icon('check-circle', 18) ?> <?= t('finish_test') ?>
    </button>
  </div>
</div>

<!-- Yashirin form (testni tugatish) -->
<form id="finishForm" method="post" style="display:none">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="finish">
</form>

<script>
const TOTAL = <?= count($questions) ?>;
const ATTEMPT_ID = <?= $attempt_id ?>;
const CSRF = '<?= csrf_token() ?>';
let currentIdx = 0;
const marked = new Set();
const answered = new Set(<?= json_encode(array_keys($savedMap)) ?>);
let timerInterval;

// ============== TIMER ==============
function startTimer(){
  let remaining = parseInt(document.getElementById('timer').dataset.remaining, 10);
  const txt = document.getElementById('timerText');
  const tWrap = document.getElementById('timer');
  function tick(){
    if (remaining <= 0) {
      clearInterval(timerInterval);
      alert("<?= t('time_up') ?>");
      finishTest(true);
      return;
    }
    const m = Math.floor(remaining/60);
    const s = remaining % 60;
    txt.textContent = String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
    if (remaining < 60) tWrap.classList.add('danger');
    else if (remaining < 300) tWrap.classList.add('warning');
    remaining--;
  }
  tick();
  timerInterval = setInterval(tick, 1000);
}

// ============== NAVIGATION ==============
function showQ(idx){
  document.querySelectorAll('.q-block').forEach(b => b.style.display = 'none');
  const block = document.querySelector(`.q-block[data-q-index="${idx}"]`);
  if (block) block.style.display = 'flex';
  currentIdx = idx;
  document.getElementById('curQ').textContent = idx + 1;
  // Nav highlight
  document.querySelectorAll('.nav-item').forEach((n, i) => {
    n.classList.remove('current');
    if (i === idx) n.classList.add('current');
  });
  // Progress
  document.getElementById('progBar').style.width = ((idx+1)/TOTAL*100) + '%';
  // Mark button state
  const mBtn = document.getElementById(`markBtn-${idx}`);
  if (mBtn) mBtn.classList.toggle('btn-warning', marked.has(idx));
}
function nextQ(){ if (currentIdx < TOTAL-1) showQ(currentIdx+1); }
function prevQ(){ if (currentIdx > 0) showQ(currentIdx-1); }
function goTo(idx){ showQ(idx); }

// ============== ANSWER SELECT ==============
async function selectAnswer(el, qid, aid, idx){
  // UI
  const block = document.querySelector(`.q-block[data-q-index="${idx}"]`);
  block.querySelectorAll('.answer-item').forEach(a => a.classList.remove('selected'));
  el.classList.add('selected');
  el.querySelector('input').checked = true;
  answered.add(qid);
  updateAnsweredCount();
  // Nav update
  document.querySelectorAll('.nav-item').forEach((n, i) => {
    if (i === idx) n.classList.add('answered');
  });

  // Save (AJAX)
  showSaving();
  try {
    const fd = new FormData();
    fd.append('action', 'save_answer');
    fd.append('csrf_token', CSRF);
    fd.append('question_id', qid);
    fd.append('answer_id', aid);
    const res = await fetch(window.location.pathname + '?attempt=' + ATTEMPT_ID, {method:'POST', body:fd});
    const j = await res.json();
    if (j.ok) showSaved();
    else showError();
  } catch(e) { showError(); }
}

function updateAnsweredCount(){
  document.getElementById('answeredCount').textContent = answered.size;
}

function toggleMark(idx){
  if (marked.has(idx)) marked.delete(idx); else marked.add(idx);
  document.querySelectorAll('.nav-item').forEach((n, i) => {
    n.classList.toggle('marked', marked.has(i));
  });
  const btn = document.getElementById(`markBtn-${idx}`);
  if (btn) btn.classList.toggle('btn-warning', marked.has(idx));
}

// ============== SAVE INDICATOR ==============
let saveTimer;
function showSaving(){
  const el = document.getElementById('saveIndicator');
  el.className = 'save-indicator saving';
  el.innerHTML = '\u25CF <?= t('auto_save') ?>';
}
function showSaved(){
  clearTimeout(saveTimer);
  const el = document.getElementById('saveIndicator');
  el.className = 'save-indicator saved';
  el.innerHTML = '\u2713 <?= lang()==='uz_cyrillic' ? 'Сақланди' : 'Saqlandi' ?>';
  saveTimer = setTimeout(() => { el.innerHTML = ''; }, 2000);
}
function showError(){
  const el = document.getElementById('saveIndicator');
  el.className = 'save-indicator';
  el.style.color = 'var(--danger)';
  el.innerHTML = '\u2715 Xatolik';
}

// ============== FINISH ==============
function finishTest(forced){
  if (forced || confirm("<?= t('confirm_finish') ?>\n\n" + answered.size + "/" + TOTAL + " <?= t('answered') ?>")) {
    document.getElementById('finishForm').submit();
  }
}
function confirmExit(){
  if (confirm('<?= lang()==='uz_cyrillic' ? 'Ҳақиқатан ҳам тестдан чиқмоқчимисиз? Жавобларингиз сақланади.' : 'Haqiqatan ham testdan chiqmoqchimisiz? Javoblaringiz saqlanadi.' ?>')) {
    window.location.href = '/user/testlar.php';
  }
}

// ============== KEYBOARD SHORTCUTS ==============
document.addEventListener('keydown', e => {
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
  if (e.key === 'ArrowRight') nextQ();
  if (e.key === 'ArrowLeft') prevQ();
  if (e.key >= '1' && e.key <= '4') {
    const block = document.querySelector(`.q-block[data-q-index="${currentIdx}"]`);
    if (block) {
      const items = block.querySelectorAll('.answer-item');
      const idx = parseInt(e.key, 10) - 1;
      if (items[idx]) items[idx].click();
    }
  }
  if (e.key === 'm' || e.key === 'M') toggleMark(currentIdx);
});

// Window beforeunload — testdan tasodifan chiqishni oldini olish
window.addEventListener('beforeunload', e => {
  if (answered.size < TOTAL) {
    e.preventDefault();
    e.returnValue = '';
  }
});

// Boshlash
startTimer();
updateAnsweredCount();
</script>
</body></html>
