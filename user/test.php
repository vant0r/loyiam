<?php
require_once __DIR__ . '/../includes/bootstrap.php';
auth_class();
require_login();

$u = current_user();
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

// ============================================================
// Test boshlash yoki davom ettirish
// ============================================================
$attempt_id = (int)($_GET['attempt'] ?? 0);
$ticket_id  = (int)($_GET['ticket'] ?? 0);

// Yangi test boshlash
if (!$attempt_id && $ticket_id) {
    $ticket = db()->fetch("SELECT * FROM tickets WHERE id=? AND status='active'", [$ticket_id]);
    if (!$ticket) { header('Location: /user/testlar.php'); exit; }

    db()->execute(
        "INSERT INTO test_attempts (user_id, ticket_id, total_questions, status) VALUES (?,?,?, 'in_progress')",
        [$u['id'], $ticket_id, $ticket['questions_count']]);
    $attempt_id = (int)db()->lastInsertId();
    audit('test_started', "Bilet #{$ticket['ticket_number']}");
    header("Location: /user/test.php?attempt=$attempt_id");
    exit;
}

// Mavjud urinishni yuklash
$attempt = db()->fetch(
    "SELECT a.*, t.title_$lang_field title, t.time_minutes, t.questions_count, t.ticket_number
     FROM test_attempts a LEFT JOIN tickets t ON a.ticket_id=t.id
     WHERE a.id=? AND a.user_id=?", [$attempt_id, $u['id']]);

if (!$attempt) { header('Location: /user/testlar.php'); exit; }

if ($attempt['status'] === 'completed') {
    header("Location: /user/test-result.php?attempt=$attempt_id");
    exit;
}

// Vaqt
$started   = strtotime($attempt['started_at']);
$timeLimit = max(1, (int)$attempt['time_minutes']) * 60;
$elapsed   = time() - $started;
$remaining = max(0, $timeLimit - $elapsed);

if ($remaining <= 0) {
    self_finish_test($attempt_id, $u['id']);
    header("Location: /user/test-result.php?attempt=$attempt_id&expired=1");
    exit;
}

// ============================================================
// AJAX: javob saqlash + tekshirish
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check_answer') {
    csrf_require();
    header('Content-Type: application/json; charset=utf-8');

    $qid = (int)($_POST['question_id'] ?? 0);
    $aid = (int)($_POST['answer_id'] ?? 0);

    if (!$qid || !$aid) { echo json_encode(['ok' => false]); exit; }

    $picked = db()->fetch(
        "SELECT id, is_correct FROM answers WHERE id=? AND question_id=?",
        [$aid, $qid]);
    if (!$picked) { echo json_encode(['ok' => false]); exit; }

    $is_correct = (int)$picked['is_correct'];

    // Mavjud javobni o'chirib, qaytadan saqlaymiz
    db()->execute("DELETE FROM test_answers WHERE attempt_id=? AND question_id=?", [$attempt_id, $qid]);
    db()->execute(
        "INSERT INTO test_answers (attempt_id, question_id, answer_id, is_correct) VALUES (?,?,?,?)",
        [$attempt_id, $qid, $aid, $is_correct]);

    // Question'ning to'g'ri javobi va izohi
    $q = db()->fetch(
        "SELECT explanation_$lang_field expl FROM questions WHERE id=?", [$qid]);
    $correct_ans = db()->fetch(
        "SELECT id FROM answers WHERE question_id=? AND is_correct=1 LIMIT 1", [$qid]);

    echo json_encode([
        'ok'                => true,
        'is_correct'        => $is_correct,
        'correct_answer_id' => (int)($correct_ans['id'] ?? 0),
        'explanation'       => $q['expl'] ?? '',
    ]);
    exit;
}

// Testni tugatish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'finish') {
    csrf_require();
    self_finish_test($attempt_id, $u['id']);
    header("Location: /user/test-result.php?attempt=$attempt_id");
    exit;
}

// Savoldagi xato xabari
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'report_error' && csrf_check()) {
    header('Content-Type: application/json; charset=utf-8');
    $qid = (int)($_POST['question_id'] ?? 0);
    $note = Security::clean($_POST['note'] ?? '', 500);
    if ($qid && $note) {
        db()->execute(
            "INSERT INTO contact_messages (name, email, phone, message)
             VALUES (?, ?, ?, ?)",
            [$u['first_name'].' '.$u['last_name'],
             $u['email'] ?: null, $u['phone'] ?: null,
             "[Savol #$qid xato] $note"]);
        Notify::sendToAdmins('admin_alert',
            "Savol #$qid bo'yicha xato xabari",
            "Foydalanuvchi: {$u['first_name']} — $note");
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

function self_finish_test(int $aid, int $uid): void {
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
// Savollarni yuklash
// ============================================================
$default_question_image = setting('default_question_image', '/assets/images/default-question.svg');

$questions = db()->fetchAll(
    "SELECT id, question_$lang_field q, image, category, difficulty, explanation_$lang_field expl
     FROM questions WHERE ticket_id=? AND status='active'
     ORDER BY id LIMIT ?",
    [$attempt['ticket_id'], (int)$attempt['total_questions']]);

if (count($questions) < $attempt['total_questions']) {
    $extra = db()->fetchAll(
        "SELECT id, question_$lang_field q, image, category, difficulty, explanation_$lang_field expl
         FROM questions WHERE status='active' AND ticket_id != ?
         ORDER BY RAND() LIMIT ?",
        [$attempt['ticket_id'], (int)$attempt['total_questions'] - count($questions)]);
    $questions = array_merge($questions, $extra);
}

if (empty($questions)) {
    ?><!DOCTYPE html>
<html lang="<?= lang() === 'uz_cyrillic' ? 'uz-Cyrl' : 'uz' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#3B82F6">
<title><?= e('Test') ?> — <?= e(setting('site_name', SITE_NAME)) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(setting('site_logo', '/assets/images/logo.svg')) ?>">
<style>
<?= base_css() ?>
<?= panel_css() ?>
</style>
</head>
<body>
<?php
echo '<div class="container section text-center"><h2>Savollar mavjud emas</h2>';
    echo '<a href="/user/testlar.php" class="btn btn-primary">Ortga</a></div>';
    exit;
}

foreach ($questions as &$q) {
    $q['answers'] = db()->fetchAll(
        "SELECT id, answer_$lang_field as txt FROM answers WHERE question_id=? ORDER BY sort_order, id",
        [$q['id']]);
}
unset($q);

// Saqlangan javoblar (is_correct ham)
$saved = db()->fetchAll(
    "SELECT question_id, answer_id, is_correct FROM test_answers WHERE attempt_id=?", [$attempt_id]);
$savedMap = [];
foreach ($saved as $s) {
    $savedMap[$s['question_id']] = [
        'aid'        => (int)$s['answer_id'],
        'is_correct' => (int)$s['is_correct'],
    ];
}

render_head(t('test_taking'));
?>
<!-- Top header — progress bar + counter + exit -->
<header class="test-topbar">
  <div class="test-topbar-inner">
    <div class="test-progress-block">
      <div class="test-progress-label">
        <strong>Jarayon: <span id="progressPct">0</span>%</strong>
        <span class="test-counter">S<span id="curQ">1</span>/<span><?= count($questions) ?></span></span>
      </div>
      <div class="test-progress">
        <div class="test-progress-bar" id="progBar" style="width:0%"></div>
      </div>
    </div>
    <div class="test-topbar-right">
      <div class="test-timer" id="timer" data-remaining="<?= $remaining ?>">
        <?= icon('clock', 16) ?>
        <span id="timerText">--:--</span>
      </div>
      <button class="btn btn-light btn-sm" onclick="confirmExit()">
        <?= icon('logout', 14) ?> <?= t('exit_test') ?>
      </button>
    </div>
  </div>
</header>

<!-- Main layout -->
<div class="test-layout">
  <!-- Asosiy savol bloki -->
  <div class="test-main">
    <?php foreach ($questions as $idx => $q):
      $img = $q['image'] ?: $default_question_image;
      $isAnswered = isset($savedMap[$q['id']]);
      $userAid = $savedMap[$q['id']]['aid'] ?? 0;
      $userCorrect = $savedMap[$q['id']]['is_correct'] ?? null;
      $correctAns = null;
      foreach ($q['answers'] as $a) if ($a['id']) { /* placeholder */ }
      // Find correct answer for already-answered questions
      $correctAid = 0;
      if ($isAnswered) {
          foreach ($q['answers'] as $a) {
              $isC = db()->fetch("SELECT is_correct FROM answers WHERE id=?", [$a['id']])['is_correct'] ?? 0;
              if ($isC) { $correctAid = (int)$a['id']; break; }
          }
      }
    ?>
    <div class="q-block <?= $isAnswered?'is-checked':'' ?>"
         data-q-index="<?= $idx ?>"
         data-q-id="<?= $q['id'] ?>"
         data-correct-aid="<?= $correctAid ?>"
         style="<?= $idx===0?'':'display:none' ?>">

      <!-- Savol matni -->
      <div class="q-header">
        <h2 class="q-text"><?= nl2br(e($q['q'])) ?></h2>
        <?php if (!empty($q['category']) || !empty($q['difficulty'])): ?>
        <div class="q-tags">
          <?php if (!empty($q['category'])): ?>
            <span class="badge badge-info"><?= e($q['category']) ?></span>
          <?php endif; ?>
          <?php if (!empty($q['difficulty'])):
            $dc = ['easy'=>'success','medium'=>'warning','hard'=>'danger'][$q['difficulty']] ?? 'mute';
          ?>
            <span class="badge badge-<?= $dc ?>"><?= t($q['difficulty']) ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Rasm -->
      <div class="q-image">
        <img src="<?= e($img) ?>" alt="" loading="lazy" <?= !$q['image'] ? 'class="is-default"' : '' ?>>
      </div>

      <!-- Izoh tugmasi (har doim mavjud, yashirin) -->
      <?php if (!empty($q['expl'])): ?>
      <button type="button" class="explanation-toggle" onclick="toggleExpl(<?= $idx ?>)" id="exp-btn-<?= $idx ?>">
        💡 Qo'llanmani ko'rish <span class="exp-arrow">▾</span>
      </button>
      <div class="explanation-content" id="exp-<?= $idx ?>" style="display:none">
        <?= nl2br(e($q['expl'])) ?>
      </div>
      <?php endif; ?>

      <!-- Javob variantlari -->
      <div class="answer-list">
        <?php foreach ($q['answers'] as $aIdx => $a):
          $letter = chr(65 + $aIdx);
          $isUserAns = $userAid === (int)$a['id'];
          $isCorrectAns = $isAnswered && (int)$a['id'] === $correctAid;
          $cls = '';
          if ($isAnswered) {
              if ($isUserAns && $userCorrect) $cls = 'is-correct';
              elseif ($isUserAns && !$userCorrect) $cls = 'is-wrong';
              elseif ($isCorrectAns) $cls = 'is-correct';
              else $cls = 'is-disabled';
          }
        ?>
        <label class="answer-item <?= $cls ?>"
               onclick="<?= $isAnswered ? '' : "selectAnswer(this, {$q['id']}, {$a['id']}, $idx)" ?>">
          <input type="radio" name="answer_<?= $q['id'] ?>" value="<?= $a['id'] ?>"
                 <?= $isUserAns ? 'checked' : '' ?> <?= $isAnswered ? 'disabled' : '' ?>>
          <span class="answer-letter"><?= $letter ?></span>
          <span class="answer-text"><?= nl2br(e($a['txt'])) ?></span>
          <?php if ($isAnswered && $isCorrectAns): ?>
            <span class="answer-mark correct">✓</span>
          <?php elseif ($isAnswered && $isUserAns && !$userCorrect): ?>
            <span class="answer-mark wrong">✕</span>
          <?php endif; ?>
        </label>
        <?php endforeach; ?>
      </div>

      <!-- Feedback messasge -->
      <div class="answer-feedback" id="feedback-<?= $idx ?>" style="<?= $isAnswered?'':'display:none' ?>">
        <?php if ($userCorrect === 1): ?>
          <div class="feedback-msg success">
            <?= icon('check-circle', 18) ?> <strong>To'g'ri javob!</strong>
          </div>
        <?php elseif ($userCorrect === 0): ?>
          <div class="feedback-msg danger">
            <?= icon('x-circle', 18) ?> <strong>Noto'g'ri javob</strong>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Yon panel -->
  <aside class="test-side">
    <!-- Navigator -->
    <div class="nav-card">
      <div class="nav-title">SAVOL NAVIGATORI</div>
      <div class="nav-grid" id="navGrid">
        <?php foreach ($questions as $idx => $q):
          $sav = $savedMap[$q['id']] ?? null;
          $cls = $idx === 0 ? 'current' : '';
          if ($sav) $cls = $sav['is_correct'] ? 'correct' : 'wrong';
          if ($idx === 0 && $sav) $cls .= ' current';
        ?>
        <button type="button" class="nav-item <?= $cls ?>"
                data-idx="<?= $idx ?>"
                onclick="goTo(<?= $idx ?>)"><?= $idx+1 ?></button>
        <?php endforeach; ?>
      </div>
      <div class="nav-legend">
        <div><span class="dot correct"></span> To'g'ri</div>
        <div><span class="dot wrong"></span> Noto'g'ri</div>
        <div><span class="dot current"></span> Joriy</div>
        <div><span class="dot empty"></span> Qolgan</div>
      </div>
    </div>

    <!-- Tip card -->
    <div class="tip-card">
      <div class="tip-title">
        <?= icon('help', 14) ?> <strong>IMTIHON MASLAHATI</strong>
      </div>
      <p>Savolni diqqat bilan o'qing. Ba'zi belgilarning suratda bir qismi yopiq bo'lishi mumkin — bu sizning vaziyatni tushunish qobiliyatingizni sinash uchun.</p>
    </div>

    <!-- Report error -->
    <button type="button" class="report-btn" onclick="openReport()">
      <?= icon('flag', 14) ?> Savoldagi xatoni xabar qilish
    </button>
  </aside>
</div>

<!-- Bottom CTA -->
<div class="test-bottom">
  <div class="test-bottom-inner">
    <button id="nextBtn" class="btn btn-primary btn-xl btn-block-mobile" onclick="nextQ()">
      <span id="nextBtnText">Tekshiring va keyingi savolga o'ting!</span>
      <?= icon('arrow-right', 20) ?>
    </button>
    <button id="finishBtn" class="btn btn-success btn-xl btn-block-mobile" onclick="finishTest()" style="display:none">
      <?= icon('check', 20) ?> Testni tugatish
    </button>
  </div>
</div>

<!-- Yashirin form -->
<form id="finishForm" method="post" style="display:none">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="finish">
</form>

<!-- Report modal -->
<div id="reportModal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">⚠️ Savoldagi xato</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <p class="text-soft mb-2" style="font-size:14px">Bu savolda nimadir noto'g'rimi? Aniq yozib bering, admin tekshirib chiqadi.</p>
      <textarea id="reportText" class="form-control" rows="4" maxlength="500" placeholder="Misol: Savol matnida xatolik bor..."></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn btn-light" data-modal-close>Bekor</button>
      <button class="btn btn-primary" onclick="sendReport()">Yuborish</button>
    </div>
  </div>
</div>

<style>
/* ============================================================
   TEST PAGE — LEARNING MODE (v2.6)
   ============================================================ */
body{padding-top:64px;padding-bottom:96px;background:var(--bg-soft)}
.header{display:none}
.footer{display:none}
.bottom-nav{display:none !important}

/* Top bar */
.test-topbar{position:fixed;top:0;left:0;right:0;background:#fff;border-bottom:1px solid var(--border);
  z-index:90;padding:10px 0;backdrop-filter:saturate(180%) blur(20px);background:rgba(255,255,255,.92)}
.test-topbar-inner{max-width:1280px;margin:0 auto;padding:0 20px;display:flex;align-items:center;gap:20px;justify-content:space-between}
.test-progress-block{flex:1;max-width:600px;min-width:0}
.test-progress-label{display:flex;justify-content:space-between;align-items:center;font-size:13px;margin-bottom:6px}
.test-progress-label strong{color:var(--primary)}
.test-counter{color:var(--text-soft);font-weight:600}
.test-progress{height:5px;background:var(--bg-mute);border-radius:3px;overflow:hidden}
.test-progress-bar{height:100%;background:linear-gradient(90deg,var(--primary),var(--primary-700));
  transition:width .5s var(--ease-soft);border-radius:3px}
.test-topbar-right{display:flex;gap:10px;align-items:center;flex-shrink:0}
.test-timer{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:var(--bg-mute);
  border-radius:var(--r-md);font-weight:700;font-size:13px;font-variant-numeric:tabular-nums;color:var(--text)}
.test-timer.warning{background:var(--warning-light);color:var(--warning-dark);animation:pulse 1.5s infinite}
.test-timer.danger{background:var(--danger-light);color:var(--danger-dark);animation:pulse .8s infinite}

/* Main layout */
.test-layout{max-width:1280px;margin:20px auto 0;padding:0 20px;display:grid;
  grid-template-columns:1fr 320px;gap:24px;align-items:start}

/* Question card */
.test-main{background:#fff;border:1px solid var(--border);border-radius:var(--r-xl);padding:28px;box-shadow:var(--shadow-xs)}
.q-block{animation:fadeUpRefined .4s var(--ease-soft) both}
.q-header{margin-bottom:18px}
.q-text{font-size:18px;font-weight:700;line-height:1.45;color:var(--text);margin:0 0 10px}
.q-tags{display:flex;gap:6px;flex-wrap:wrap}
.q-image{margin-bottom:18px;text-align:center;background:var(--bg-soft);border-radius:var(--r-md);overflow:hidden}
.q-image img{max-width:100%;max-height:420px;width:auto;display:block;margin:0 auto;transition:transform .3s var(--ease-soft)}
.q-image img:hover{transform:scale(1.02)}
.q-image img.is-default{opacity:.8}

/* Explanation toggle */
.explanation-toggle{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;
  background:var(--warning-light);color:var(--warning-dark);border:none;border-radius:var(--r-md);
  font-weight:600;font-size:13px;cursor:pointer;margin-bottom:18px;transition:all .2s}
.explanation-toggle:hover{background:#FCD34D;transform:translateY(-1px)}
.explanation-toggle .exp-arrow{transition:transform .3s}
.explanation-toggle.open .exp-arrow{transform:rotate(180deg)}
.explanation-content{background:#FFFBEB;border:1px solid #FCD34D;border-radius:var(--r-md);
  padding:14px 18px;font-size:14px;color:#78350F;line-height:1.6;margin-bottom:18px;
  animation:slideDown .3s var(--ease-soft)}

/* Answer items */
.answer-list{display:flex;flex-direction:column;gap:10px}
.answer-item{display:flex;align-items:center;gap:14px;padding:14px 18px;border:2px solid var(--border);
  border-radius:var(--r-md);cursor:pointer;background:#fff;transition:all .2s var(--ease-soft);
  user-select:none;position:relative}
.answer-item:not(.is-correct):not(.is-wrong):not(.is-disabled):hover{
  border-color:var(--primary-300);background:var(--primary-50)}
.answer-item input{display:none}
.answer-letter{flex-shrink:0;width:28px;height:28px;border:2px solid var(--border);border-radius:50%;
  display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:var(--text-soft);
  background:#fff;transition:all .2s}
.answer-item:has(input:checked) .answer-letter{background:var(--primary);color:#fff;border-color:var(--primary)}
.answer-text{flex:1;line-height:1.5;font-size:15px;color:var(--text);font-weight:500}
.answer-mark{flex-shrink:0;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;
  justify-content:center;font-weight:800;font-size:14px}
.answer-mark.correct{background:var(--success);color:#fff}
.answer-mark.wrong{background:var(--danger);color:#fff}

/* Answer states (after check) */
.answer-item.is-correct{border-color:var(--success);background:var(--success-light)}
.answer-item.is-correct .answer-text{color:var(--success-dark);font-weight:700}
.answer-item.is-correct .answer-letter{background:var(--success);color:#fff;border-color:var(--success)}

.answer-item.is-wrong{border-color:var(--danger);background:#FEF2F2}
.answer-item.is-wrong .answer-text{color:var(--danger-dark);font-weight:700}
.answer-item.is-wrong .answer-letter{background:var(--danger);color:#fff;border-color:var(--danger)}

.answer-item.is-disabled{opacity:.55;cursor:default}

/* Feedback message */
.answer-feedback{margin-top:14px}
.feedback-msg{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:var(--r-md);
  font-weight:600;animation:fadeUpRefined .3s var(--ease-soft)}
.feedback-msg.success{background:var(--success-light);color:var(--success-dark)}
.feedback-msg.danger{background:var(--danger-light);color:var(--danger-dark)}

/* Sidebar */
.test-side{position:sticky;top:80px;display:flex;flex-direction:column;gap:14px}

.nav-card{background:#fff;border:1px solid var(--border);border-radius:var(--r-xl);padding:18px;box-shadow:var(--shadow-xs)}
.nav-title{font-size:11px;font-weight:800;color:var(--text-soft);text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px}
.nav-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:6px;margin-bottom:14px}
.nav-item{aspect-ratio:1;display:flex;align-items:center;justify-content:center;border-radius:var(--r-sm);
  border:2px solid var(--border);background:var(--bg-soft);font-size:13px;font-weight:700;cursor:pointer;
  color:var(--text-soft);transition:all .2s var(--ease-soft);min-height:44px}
.nav-item:hover{border-color:var(--primary);color:var(--primary);transform:scale(1.05)}
.nav-item.current{background:var(--primary);border-color:var(--primary);color:#fff;
  box-shadow:0 4px 12px rgba(59,130,246,.3);transform:scale(1.05)}
.nav-item.correct{background:#D1FAE5;border-color:var(--success);color:var(--success-dark)}
.nav-item.wrong{background:#FEE2E2;border-color:var(--danger);color:var(--danger-dark)}
.nav-item.correct.current,.nav-item.wrong.current{box-shadow:0 0 0 3px var(--primary-100)}
.nav-legend{display:flex;flex-direction:column;gap:6px;font-size:11px;color:var(--text-soft);
  padding-top:10px;border-top:1px solid var(--border)}
.nav-legend > div{display:flex;align-items:center;gap:8px}
.nav-legend .dot{width:14px;height:14px;border-radius:3px;flex-shrink:0;border:1.5px solid transparent}
.nav-legend .dot.correct{background:#D1FAE5;border-color:var(--success)}
.nav-legend .dot.wrong{background:#FEE2E2;border-color:var(--danger)}
.nav-legend .dot.current{background:var(--primary)}
.nav-legend .dot.empty{background:var(--bg-soft);border-color:var(--border)}

/* Tip card */
.tip-card{background:#EFF6FF;border:1px solid #BFDBFE;border-radius:var(--r-md);padding:16px;font-size:13px;color:var(--primary-800);line-height:1.55}
.tip-title{display:flex;align-items:center;gap:6px;margin-bottom:8px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--primary)}
.tip-card p{margin:0}

/* Report button */
.report-btn{display:flex;align-items:center;justify-content:center;gap:8px;background:#fff;
  border:1px solid var(--border);border-radius:var(--r-md);padding:12px 14px;font-size:13px;
  font-weight:600;color:var(--text-soft);cursor:pointer;transition:all .2s;width:100%}
.report-btn:hover{border-color:var(--warning);color:var(--warning-dark);background:var(--warning-light)}

/* Bottom bar */
.test-bottom{position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid var(--border);
  padding:14px 0;z-index:90;backdrop-filter:saturate(180%) blur(20px);background:rgba(255,255,255,.96)}
.test-bottom-inner{max-width:1280px;margin:0 auto;padding:0 20px;display:flex;justify-content:flex-end;gap:10px}
.test-bottom .btn{min-width:300px}

/* Responsive — Tablet */
@media (max-width: 1024px) {
  .test-layout{grid-template-columns:1fr;gap:14px}
  .test-side{position:static;display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .nav-card{grid-column:1 / -1}
  .test-bottom .btn{min-width:240px}
}

/* Responsive — Mobile */
@media (max-width: 720px) {
  body{padding-top:90px}
  .test-topbar{padding:10px 0}
  .test-topbar-inner{flex-direction:column;align-items:stretch;gap:8px;padding:0 14px}
  .test-progress-block{max-width:none;width:100%}
  .test-topbar-right{justify-content:space-between;width:100%}
  .test-layout{padding:0 14px;margin-top:14px}
  .test-side{grid-template-columns:1fr}
  .test-main{padding:18px}
  .q-text{font-size:16px}
  .q-image img{max-height:280px}
  .answer-item{padding:12px 14px;gap:12px}
  .answer-text{font-size:14px}
  .nav-grid{grid-template-columns:repeat(5,1fr);gap:4px}
  .nav-item{font-size:12px;min-height:38px}
  .test-bottom-inner{padding:0 14px}
  .test-bottom .btn{min-width:auto;width:100%;font-size:14px;padding:14px}
  .btn-block-mobile{width:100%}
}
@media (max-width: 480px) {
  body{padding-bottom:88px}
  .test-main{padding:14px}
  .q-text{font-size:15px}
  .q-image{margin-bottom:14px}
  .answer-letter{width:26px;height:26px;font-size:12px}
  .answer-item{padding:10px 12px}
  .nav-item{font-size:11px;min-height:34px}
  .test-bottom .btn{font-size:13px;padding:13px}
}

/* Reduce side panel on mobile */
@media (max-width: 480px) {
  .test-side{order:2}
  .test-main{order:1}
}

/* ============================================================
   MOBILE-FIRST OVERRIDES v3.0 — test.php (aggressive)
   Test ishlash mobil tajribasi - kritik sahifa
   ============================================================ */
@media (max-width: 880px){
  body{padding-top:96px;padding-bottom:84px}

  /* Top bar — better stacking, bigger touch */
  .test-topbar{padding:8px 0;background:rgba(255,255,255,.98)}
  .test-topbar-inner{flex-direction:column;align-items:stretch;gap:6px;padding:0 12px}
  .test-progress-label{font-size:12px;margin-bottom:4px}
  .test-progress{height:6px}
  .test-topbar-right{justify-content:space-between;width:100%;gap:6px}
  .test-timer{padding:7px 12px;font-size:13px;min-height:36px;border-radius:8px}
  .test-topbar-right .btn{padding:7px 12px;font-size:12px;min-height:36px}

  /* Layout */
  .test-layout{padding:0 12px;margin-top:12px;gap:12px}
  .test-main{padding:18px 16px;border-radius:14px;order:1}
  .test-side{order:2;grid-template-columns:1fr;gap:10px}

  /* Question */
  .q-header{margin-bottom:14px}
  .q-text{font-size:16px;line-height:1.5;margin-bottom:8px}
  .q-tags{gap:4px}
  .q-tags .badge{font-size:10px;padding:3px 8px}
  .q-image{margin-bottom:14px;border-radius:10px}
  .q-image img{max-height:240px}

  /* Explanation */
  .explanation-toggle{padding:9px 14px;font-size:13px;min-height:40px;margin-bottom:14px}
  .explanation-content{padding:12px 14px;font-size:13px;margin-bottom:14px}

  /* Answers — BIGGER taps, easier reading */
  .answer-list{gap:8px}
  .answer-item{padding:13px 14px;gap:12px;border-radius:10px;border-width:2px;min-height:56px;align-items:center}
  .answer-letter{width:28px;height:28px;font-size:13px;flex-shrink:0}
  .answer-text{font-size:15px;line-height:1.45}
  .answer-mark{width:24px;height:24px;font-size:12px}

  /* Feedback */
  .answer-feedback{margin-top:12px}
  .feedback-msg{padding:10px 12px;font-size:13px;border-radius:8px}

  /* Sidebar nav grid — bigger touch */
  .nav-card{padding:14px;border-radius:12px}
  .nav-title{font-size:10px;margin-bottom:10px}
  .nav-grid{grid-template-columns:repeat(6,1fr);gap:5px;margin-bottom:10px}
  .nav-item{font-size:12px;min-height:40px;border-width:2px;border-radius:8px;font-weight:700}
  .nav-legend{font-size:10px;gap:5px;padding-top:8px}
  .nav-legend .dot{width:12px;height:12px}

  /* Tip */
  .tip-card{padding:12px;font-size:12px;border-radius:10px}
  .tip-title{font-size:10px;margin-bottom:6px}

  /* Report */
  .report-btn{padding:10px 12px;font-size:12px;min-height:42px}

  /* Bottom action bar */
  .test-bottom{padding:10px 0;background:rgba(255,255,255,.98);box-shadow:0 -4px 12px rgba(15,23,42,.06)}
  .test-bottom-inner{padding:0 12px;flex-direction:row;gap:8px}
  .test-bottom .btn{min-width:0;flex:1;font-size:14px;padding:13px 14px;min-height:48px;border-radius:10px}
}

@media (max-width: 480px){
  body{padding-top:90px;padding-bottom:78px}
  .test-topbar-inner{padding:0 10px}
  .test-layout{padding:0 10px;margin-top:10px}
  .test-main{padding:14px 12px;border-radius:12px}

  .q-text{font-size:15px}
  .q-image img{max-height:200px}
  .q-image{border-radius:8px}

  .answer-item{padding:12px 12px;gap:10px;min-height:54px}
  .answer-letter{width:26px;height:26px;font-size:12px}
  .answer-text{font-size:14px}

  .nav-grid{grid-template-columns:repeat(5,1fr);gap:4px}
  .nav-item{font-size:11px;min-height:36px}

  .test-bottom-inner{padding:0 10px;gap:6px}
  .test-bottom .btn{font-size:13px;padding:12px;min-height:46px}
  .test-bottom .btn span{font-size:13px}
}

@media (max-width: 360px){
  .test-topbar-inner{padding:0 8px}
  .test-layout{padding:0 8px}
  .test-main{padding:12px 10px}
  .q-text{font-size:14px}
  .answer-item{padding:10px;gap:9px;min-height:50px}
  .answer-text{font-size:13px}
  .nav-grid{grid-template-columns:repeat(4,1fr)}
  .nav-item{min-height:34px;font-size:11px}
  .test-bottom .btn{font-size:12px;padding:11px}
}

/* Touch — disable hover */
@media (hover:none){
  .answer-item:not(.is-correct):not(.is-wrong):not(.is-disabled):hover{
    border-color:var(--border);background:#fff;
  }
  .answer-item:active:not(.is-correct):not(.is-wrong):not(.is-disabled){
    background:var(--primary-50);border-color:var(--primary-300);transform:scale(.99);
  }
  .nav-item:hover{transform:none;border-color:var(--border);color:var(--text-soft)}
  .nav-item:active{transform:scale(.95)}
  .explanation-toggle:hover{transform:none}
  .q-image img:hover{transform:none}
}

/* Performance — disable expensive blur on mobile */
@media (max-width: 880px){
  .test-topbar,.test-bottom{
    backdrop-filter:saturate(150%) blur(10px);
    -webkit-backdrop-filter:saturate(150%) blur(10px);
  }
}

/* Landscape phone — better space usage */
@media (max-height: 480px) and (orientation: landscape){
  body{padding-top:54px;padding-bottom:64px}
  .test-topbar-inner{flex-direction:row;align-items:center;padding:0 14px}
  .test-progress-block{flex:1}
  .q-image img{max-height:140px}
  .test-bottom .btn{padding:10px}
}
</style>

<script>
const TOTAL = <?= count($questions) ?>;
const ATTEMPT_ID = <?= $attempt_id ?>;
const CSRF = '<?= csrf_token() ?>';
let currentIdx = 0;
let timerInterval;
let answeredCount = <?= count($savedMap) ?>;

// ============== TIMER ==============
function startTimer(){
  let remaining = parseInt(document.getElementById('timer').dataset.remaining, 10);
  const txt = document.getElementById('timerText');
  const tWrap = document.getElementById('timer');
  function tick(){
    if (remaining <= 0) {
      clearInterval(timerInterval);
      alert("Vaqt tugadi!");
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
  if (block) block.style.display = '';
  currentIdx = idx;
  document.getElementById('curQ').textContent = idx + 1;

  // Update nav
  document.querySelectorAll('.nav-item').forEach((n, i) => {
    n.classList.toggle('current', i === idx);
  });

  // Update progress
  updateProgress();

  // Update buttons
  updateButtons();

  // Scroll to top
  window.scrollTo({top: 0, behavior: 'smooth'});
}

function nextQ(){
  if (currentIdx < TOTAL - 1) showQ(currentIdx + 1);
  else finishTest();
}

function goTo(idx){ showQ(idx); }

function updateProgress(){
  const pct = Math.round(((currentIdx + 1) / TOTAL) * 100);
  document.getElementById('progressPct').textContent = pct;
  document.getElementById('progBar').style.width = pct + '%';
}

function updateButtons(){
  const isLast = currentIdx === TOTAL - 1;
  document.getElementById('nextBtn').style.display = isLast ? 'none' : '';
  document.getElementById('finishBtn').style.display = isLast ? '' : 'none';

  // Button text — javob berilganmi?
  const block = document.querySelector(`.q-block[data-q-index="${currentIdx}"]`);
  const isChecked = block && block.classList.contains('is-checked');
  const txt = document.getElementById('nextBtnText');
  if (txt) txt.textContent = isChecked
    ? "Keyingi savolga o'ting"
    : "Tekshiring va keyingi savolga o'ting!";
}

// ============== ANSWER SELECT ==============
async function selectAnswer(el, qid, aid, idx){
  const block = document.querySelector(`.q-block[data-q-index="${idx}"]`);
  if (block.classList.contains('is-checked')) return; // allaqachon javob berilgan

  // Vizual loading
  block.style.pointerEvents = 'none';
  el.style.opacity = '.6';

  try {
    const fd = new FormData();
    fd.append('action', 'check_answer');
    fd.append('csrf_token', CSRF);
    fd.append('question_id', qid);
    fd.append('answer_id', aid);
    const res = await fetch(window.location.pathname + '?attempt=' + ATTEMPT_ID, {method:'POST', body:fd});
    const j = await res.json();

    if (!j.ok) {
      el.style.opacity = '';
      block.style.pointerEvents = '';
      if (window.toast) toast('Xatolik!', 'danger');
      return;
    }

    // Block tomon — checked
    block.classList.add('is-checked');
    block.dataset.correctAid = j.correct_answer_id;

    // Barcha javoblarni disabled qilamiz
    block.querySelectorAll('.answer-item').forEach(item => {
      const inp = item.querySelector('input');
      const itemAid = parseInt(inp.value, 10);
      inp.disabled = true;
      item.style.cursor = 'default';
      item.onclick = null;
      item.style.opacity = '';

      if (itemAid === j.correct_answer_id) {
        item.classList.add('is-correct');
        // ✓ marker qo'shamiz
        if (!item.querySelector('.answer-mark')) {
          const m = document.createElement('span');
          m.className = 'answer-mark correct';
          m.textContent = '✓';
          item.appendChild(m);
        }
      } else if (itemAid === aid && !j.is_correct) {
        item.classList.add('is-wrong');
        if (!item.querySelector('.answer-mark')) {
          const m = document.createElement('span');
          m.className = 'answer-mark wrong';
          m.textContent = '✕';
          item.appendChild(m);
        }
      } else {
        item.classList.add('is-disabled');
      }
    });

    // Feedback message
    const fb = document.getElementById('feedback-' + idx);
    fb.style.display = '';
    fb.innerHTML = j.is_correct
      ? '<div class="feedback-msg success">✓ <strong>To\'g\'ri javob!</strong></div>'
      : '<div class="feedback-msg danger">✕ <strong>Noto\'g\'ri javob</strong></div>';

    // Nav update
    const navBtn = document.querySelector(`.nav-item[data-idx="${idx}"]`);
    if (navBtn) {
      navBtn.classList.remove('current');
      navBtn.classList.add(j.is_correct ? 'correct' : 'wrong');
      // Joriy holatga qaytarish (current bo'lsa)
      if (currentIdx === idx) navBtn.classList.add('current');
    }

    // Auto-show explanation (agar bor bo'lsa va xato bo'lsa)
    if (!j.is_correct && j.explanation) {
      setTimeout(() => {
        const expBtn = document.getElementById('exp-btn-' + idx);
        if (expBtn) expBtn.click();
      }, 800);
    }

    // Vibrate (mobile)
    if (navigator.vibrate) {
      navigator.vibrate(j.is_correct ? [50] : [100, 50, 100]);
    }

    answeredCount++;
    updateButtons();

  } catch (e) {
    el.style.opacity = '';
    block.style.pointerEvents = '';
    if (window.toast) toast('Tarmoq xatosi', 'danger');
    console.error(e);
  }
}

// ============== EXPLANATION TOGGLE ==============
function toggleExpl(idx){
  const btn = document.getElementById('exp-btn-' + idx);
  const cnt = document.getElementById('exp-' + idx);
  if (!btn || !cnt) return;
  const isOpen = cnt.style.display !== 'none';
  cnt.style.display = isOpen ? 'none' : '';
  btn.classList.toggle('open', !isOpen);
}

// ============== FINISH ==============
function finishTest(forced){
  const unanswered = TOTAL - answeredCount;
  if (forced || confirm(
      `Testni tugatishni tasdiqlaysizmi?\n\n${answeredCount}/${TOTAL} javob berilgan` +
      (unanswered > 0 ? `\n${unanswered} ta savol javobsiz qoladi.` : ''))) {
    document.getElementById('finishForm').submit();
  }
}

function confirmExit(){
  if (confirm('Haqiqatan ham testdan chiqmoqchimisiz? Javoblaringiz saqlanadi va keyinroq davom ettirishingiz mumkin.')) {
    window.location.href = '/user/testlar.php';
  }
}

// ============== REPORT ==============
function openReport(){ openModal('reportModal'); }
async function sendReport(){
  const text = document.getElementById('reportText').value.trim();
  if (text.length < 10) {
    if (window.toast) toast('Iltimos, kamida 10 ta belgi yozing', 'warning');
    return;
  }
  const block = document.querySelector(`.q-block[data-q-index="${currentIdx}"]`);
  const qid = block.dataset.qId;

  try {
    const fd = new FormData();
    fd.append('action', 'report_error');
    fd.append('csrf_token', CSRF);
    fd.append('question_id', qid);
    fd.append('note', text);
    await fetch(window.location.pathname + '?attempt=' + ATTEMPT_ID, {method:'POST', body:fd});
    if (window.toast) toast('Rahmat! Adminga xabar yetkazildi', 'success');
    document.getElementById('reportText').value = '';
    closeModal('reportModal');
  } catch (e) {
    if (window.toast) toast('Yuborishda xato', 'danger');
  }
}

// ============== KEYBOARD ==============
document.addEventListener('keydown', e => {
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
  if (e.key === 'ArrowRight' || e.key === 'Enter') { e.preventDefault(); nextQ(); }
  if (e.key === 'ArrowLeft' && currentIdx > 0) showQ(currentIdx - 1);
  if (['1','2','3','4','a','b','c','d'].includes(e.key.toLowerCase())) {
    const block = document.querySelector(`.q-block[data-q-index="${currentIdx}"]`);
    if (block && !block.classList.contains('is-checked')) {
      const map = {'1':0,'2':1,'3':2,'4':3,'a':0,'b':1,'c':2,'d':3};
      const idx = map[e.key.toLowerCase()];
      const items = block.querySelectorAll('.answer-item');
      if (items[idx]) items[idx].click();
    }
  }
});

// Beforeunload
window.addEventListener('beforeunload', e => {
  if (answeredCount < TOTAL) {
    e.preventDefault();
    e.returnValue = '';
  }
});

// Boshlash
startTimer();
updateProgress();
updateButtons();
</script>
<script><?= panel_js() ?></script>
</body></html>
