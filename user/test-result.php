<?php
/**
 * user/test-result.php — STANDALONE test result page
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$u = current_user();
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';
$attempt_id = (int)($_GET['attempt'] ?? 0);
$expired    = !empty($_GET['expired']);
$showAnswers = !empty($_GET['answers']);

$attempt = db()->fetch(
    "SELECT a.*, t.title_$lang_field title, t.ticket_number FROM test_attempts a
     LEFT JOIN tickets t ON a.ticket_id=t.id WHERE a.id=? AND a.user_id=?",
    [$attempt_id, $u['id']]);
if (!$attempt) { header('Location: /user/'); exit; }
if ($attempt['status'] !== 'completed') { header("Location: /user/test.php?attempt=$attempt_id"); exit; }

$score = (float)$attempt['score_percent'];
$correct = (int)$attempt['correct_answers'];
$wrong = (int)$attempt['wrong_answers'];
$total = (int)$attempt['total_questions'];
$unanswered = max(0, $total - $correct - $wrong);
$timeSpent = (int)$attempt['time_spent'];
$mins = floor($timeSpent / 60); $secs = $timeSpent % 60;
$isPass = $score >= 80;
$resultClass = $score >= 90 ? 'excellent' : ($score >= 80 ? 'good' : ($score >= 50 ? 'average' : 'poor'));
$resultEmoji = $score >= 90 ? '🏆' : ($score >= 80 ? '🎉' : ($score >= 50 ? '💪' : '📚'));
$titles = [
    'excellent' => lang() === 'uz_cyrillic' ? 'Аъло натижа!' : "A'lo natija!",
    'good'      => lang() === 'uz_cyrillic' ? 'Яхши!' : 'Yaxshi!',
    'average'   => lang() === 'uz_cyrillic' ? 'Ўртача' : "O'rtacha",
    'poor'      => lang() === 'uz_cyrillic' ? 'Кўпроқ машқ қилинг' : "Ko'proq mashq qiling",
];
$msgs = [
    'excellent' => lang() === 'uz_cyrillic' ? 'Сиз ажойиб натижа кўрсатдингиз!' : "Siz ajoyib natija ko'rsatdingiz!",
    'good'      => lang() === 'uz_cyrillic' ? "Зўр иш! Имтиҳондан осонгина ўтасиз." : "Zo'r ish! Imtihondan osongina o'tasiz.",
    'average'   => lang() === 'uz_cyrillic' ? "Яхши, лекин янада яхши бўлиши мумкин." : "Yaxshi, lekin yanada yaxshi bo'lishi mumkin.",
    'poor'      => lang() === 'uz_cyrillic' ? "Қайта машқ қилинг ва такрорланг." : "Qayta mashq qiling va takrorlang.",
];

$answerDetails = [];
if ($showAnswers) {
    $rows = db()->fetchAll(
        "SELECT q.id qid, q.question_$lang_field q_text, q.image, q.explanation_$lang_field expl, q.category, q.difficulty,
                ta.answer_id user_aid, ta.is_correct
         FROM questions q INNER JOIN test_answers ta ON ta.question_id = q.id
         WHERE ta.attempt_id = ? ORDER BY q.id", [$attempt_id]);
    foreach ($rows as $r) {
        $r['user_answer'] = $r['user_aid']
            ? (db()->fetch("SELECT answer_$lang_field as txt FROM answers WHERE id=?", [$r['user_aid']])['txt'] ?? '')
            : '';
        $r['correct_answer'] = db()->fetch("SELECT answer_$lang_field as txt FROM answers WHERE question_id=? AND is_correct=1", [$r['qid']])['txt'] ?? '';
        $answerDetails[] = $r;
    }
}

$site_name = setting('site_name', SITE_NAME);
$gradient = $isPass ? '#10B981 0%,#059669' : ($score >= 50 ? '#F59E0B 0%,#D97706' : '#EF4444 0%,#DC2626');
?><!DOCTYPE html>
<html lang="<?= lang() === 'uz_cyrillic' ? 'uz-Cyrl' : 'uz' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="<?= $isPass ? '#10B981' : '#EF4444' ?>">
<title><?= e(t('your_result')) ?> — <?= e($site_name) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(setting('site_logo', '/assets/images/logo.svg')) ?>">
<style>
<?= base_css() ?>

/* === USER/TEST-RESULT.PHP — celebration design === */
body{background:#F8FAFC}
.tr-header{padding:14px 22px;display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.85);backdrop-filter:blur(10px);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10}
.tr-back{display:inline-flex;align-items:center;gap:6px;color:var(--text-soft);font-size:13px;font-weight:600;text-decoration:none}
.tr-logo{display:inline-flex;align-items:center;gap:8px;font-weight:800;font-size:14px;color:var(--text);text-decoration:none}
.tr-logo .li{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#3B82F6,#1E40AF);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:11px}

.result-hero{background:linear-gradient(135deg,<?= $gradient ?> 100%);color:#fff;padding:48px 20px;text-align:center;position:relative;overflow:hidden}
.result-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at top,rgba(255,255,255,.2),transparent 60%)}
.result-emoji{font-size:80px;margin-bottom:12px;animation:bounceIn 1s cubic-bezier(.34,1.56,.64,1) both;position:relative;z-index:1}
@keyframes bounceIn{0%{transform:scale(0)}60%{transform:scale(1.2)}80%{transform:scale(.9)}100%{transform:scale(1)}}
.result-percent{font-size:96px;font-weight:900;line-height:1;margin:14px 0;text-shadow:0 4px 20px rgba(0,0,0,.2);position:relative;z-index:1}
.result-percent .pct{font-size:48px;opacity:.8}
.result-hero h1{color:#fff;font-size:28px;margin:6px 0;font-weight:800;position:relative;z-index:1}
.result-hero p{font-size:15px;opacity:.95;position:relative;z-index:1}
.result-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;max-width:780px;margin:32px auto 0;position:relative;z-index:1}
.result-stat{background:rgba(255,255,255,.15);backdrop-filter:blur(10px);padding:16px 12px;border-radius:14px;border:1px solid rgba(255,255,255,.2)}
.result-stat .v{font-size:26px;font-weight:800}
.result-stat .l{font-size:11.5px;opacity:.85;margin-top:3px}

.tr-actions{padding:24px 20px;text-align:center;display:flex;gap:10px;justify-content:center;flex-wrap:wrap}

.answer-detail{background:#fff;border:1px solid #EEF1F5;border-radius:12px;padding:16px;margin-bottom:10px;border-left:3px solid var(--success)}
.answer-detail.wrong{border-left-color:var(--danger)}
.answer-detail .qtxt{font-size:14px;font-weight:600;margin-bottom:10px;line-height:1.4}
.ans-row{padding:8px 12px;border-radius:8px;margin-bottom:6px;font-size:13px;display:flex;gap:8px;align-items:flex-start}
.ans-row.user{background:#FEE2E2;color:#991B1B}
.ans-row.user.correct{background:#D1FAE5;color:#065F46}
.ans-row.correct-ans{background:#D1FAE5;color:#065F46}
.ans-row .label{font-size:10.5px;font-weight:700;text-transform:uppercase;flex-shrink:0;width:60px;opacity:.8}

.tr-footer{text-align:center;padding:20px;color:var(--text-soft);font-size:12.5px}

@media (max-width:640px){
  .result-hero{padding:32px 16px}
  .result-emoji{font-size:60px}
  .result-percent{font-size:64px}
  .result-percent .pct{font-size:32px}
  .result-stats{grid-template-columns:repeat(2,1fr);gap:10px;max-width:100%}
  .result-hero h1{font-size:22px}
}
</style>
</head>
<body>

<header class="tr-header">
  <a href="/user/" class="tr-back"><?= icon('arrow-left', 14) ?> <?= t('dashboard') ?></a>
  <a href="/" class="tr-logo"><span class="li">VP</span><span><?= e($site_name) ?></span></a>
</header>

<section class="result-hero">
  <div class="result-emoji"><?= $resultEmoji ?></div>
  <h1><?= $titles[$resultClass] ?></h1>
  <div class="result-percent"><?= round($score) ?><span class="pct">%</span></div>
  <p><?= $msgs[$resultClass] ?></p>

  <?php if ($expired): ?>
    <div style="margin:18px auto;max-width:380px;padding:9px 14px;background:rgba(255,255,255,.2);border-radius:10px;font-size:13px;position:relative;z-index:1">
      ⏰ <?= t('time_up') ?>
    </div>
  <?php endif; ?>

  <div class="result-stats">
    <div class="result-stat"><div class="v"><?= $total ?></div><div class="l"><?= lang()==='uz_cyrillic' ? "Жами" : "Jami" ?></div></div>
    <div class="result-stat"><div class="v"><?= $correct ?></div><div class="l"><?= lang()==='uz_cyrillic' ? "Тўғри" : "To'g'ri" ?></div></div>
    <div class="result-stat"><div class="v"><?= $wrong ?></div><div class="l"><?= lang()==='uz_cyrillic' ? "Хато" : "Xato" ?></div></div>
    <div class="result-stat"><div class="v"><?= $mins ?>:<?= str_pad($secs,2,'0',STR_PAD_LEFT) ?></div><div class="l"><?= lang()==='uz_cyrillic' ? "Вақт" : "Vaqt" ?></div></div>
  </div>
</section>

<div class="tr-actions">
  <a href="/user/testlar.php" class="btn btn-primary"><?= icon('refresh', 14) ?> <?= lang()==='uz_cyrillic' ? "Янги тест" : "Yangi test" ?></a>
  <?php if (!$showAnswers): ?>
    <a href="?attempt=<?= $attempt_id ?>&answers=1" class="btn btn-light"><?= icon('eye', 14) ?> <?= lang()==='uz_cyrillic' ? "Жавобларни кўриш" : "Javoblarni ko'rish" ?></a>
  <?php endif; ?>
  <a href="/user/natijalar.php" class="btn btn-light"><?= icon('chart', 14) ?> <?= t('results') ?></a>
</div>

<?php if ($showAnswers && !empty($answerDetails)): ?>
<section class="container" style="max-width:760px;padding:8px 16px 32px">
  <h2 style="font-size:18px;font-weight:800;margin-bottom:14px"><?= lang()==='uz_cyrillic' ? "Сизнинг жавобларингиз" : "Sizning javoblaringiz" ?></h2>
  <?php foreach ($answerDetails as $i => $ad): ?>
    <div class="answer-detail <?= $ad['is_correct'] ? '' : 'wrong' ?>">
      <div class="qtxt"><?= $i+1 ?>. <?= e($ad['q_text']) ?></div>
      <?php if ($ad['user_answer']): ?>
        <div class="ans-row user <?= $ad['is_correct']?'correct':'' ?>">
          <span class="label"><?= lang()==='uz_cyrillic' ? "Сиз" : "Siz" ?></span>
          <span><?= e($ad['user_answer']) ?> <?= $ad['is_correct'] ? '✓' : '✕' ?></span>
        </div>
      <?php endif; ?>
      <?php if (!$ad['is_correct'] && $ad['correct_answer']): ?>
        <div class="ans-row correct-ans">
          <span class="label"><?= lang()==='uz_cyrillic' ? "Тўғри" : "To'g'ri" ?></span>
          <span><?= e($ad['correct_answer']) ?> ✓</span>
        </div>
      <?php endif; ?>
      <?php if ($ad['expl']): ?>
        <div style="margin-top:8px;padding:8px 12px;background:#FFFBEB;border-radius:6px;font-size:12.5px;color:#78350F;line-height:1.5">
          💡 <?= e($ad['expl']) ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<footer class="tr-footer">© <?= date('Y') ?> <?= e($site_name) ?></footer>

<?php if ($score >= 80): ?>
<script>
// Confetti effect
(function(){
  const colors = ['#10B981','#3B82F6','#F59E0B','#EC4899','#8B5CF6'];
  for (let i = 0; i < 50; i++) {
    const c = document.createElement('div');
    c.style.cssText = `position:fixed;width:10px;height:10px;background:${colors[Math.floor(Math.random()*colors.length)]};
      top:-10px;left:${Math.random()*100}vw;z-index:1000;border-radius:2px;pointer-events:none;
      transform:rotate(${Math.random()*360}deg);
      animation:fall ${2+Math.random()*3}s linear forwards;
      animation-delay:${Math.random()*1.5}s`;
    document.body.appendChild(c);
    setTimeout(() => c.remove(), 6000);
  }
  if (!document.getElementById('cf-anim')) {
    const s = document.createElement('style');
    s.id = 'cf-anim';
    s.textContent = '@keyframes fall{from{transform:translateY(0) rotate(0)}to{transform:translateY(110vh) rotate(720deg)}}';
    document.head.appendChild(s);
  }
})();
</script>
<?php endif; ?>

</body></html>
