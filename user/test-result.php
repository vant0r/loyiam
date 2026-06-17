<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$u = current_user();
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';
$attempt_id = (int)($_GET['attempt'] ?? 0);
$expired    = !empty($_GET['expired']);
$showAnswers = !empty($_GET['answers']);

$attempt = db()->fetch(
    "SELECT a.*, t.title_$lang_field title, t.ticket_number
     FROM test_attempts a LEFT JOIN tickets t ON a.ticket_id=t.id
     WHERE a.id=? AND a.user_id=?", [$attempt_id, $u['id']]);

if (!$attempt) { header('Location: /user/'); exit; }

// Agar test hali tugatilmagan bo'lsa
if ($attempt['status'] !== 'completed') {
    header("Location: /user/test.php?attempt=$attempt_id");
    exit;
}

$score = (float)$attempt['score_percent'];
$correct = (int)$attempt['correct_answers'];
$wrong = (int)$attempt['wrong_answers'];
$total = (int)$attempt['total_questions'];
$unanswered = max(0, $total - $correct - $wrong);
$timeSpent = (int)$attempt['time_spent'];
$mins = floor($timeSpent / 60);
$secs = $timeSpent % 60;

$isPass = $score >= 80;
$resultClass = $score >= 90 ? 'excellent' : ($score >= 80 ? 'good' : ($score >= 50 ? 'average' : 'poor'));
$resultEmoji = $score >= 90 ? '🏆' : ($score >= 80 ? '🎉' : ($score >= 50 ? '💪' : '📚'));

$resultTitles = [
    'excellent' => lang() === 'uz_cyrillic' ? 'Аъло натижа!' : "A'lo natija!",
    'good'      => lang() === 'uz_cyrillic' ? 'Яхши!' : 'Yaxshi!',
    'average'   => lang() === 'uz_cyrillic' ? 'Ўртача' : "O'rtacha",
    'poor'      => lang() === 'uz_cyrillic' ? 'Кўпроқ машқ қилинг' : "Ko'proq mashq qiling",
];
$resultMsgs = [
    'excellent' => lang() === 'uz_cyrillic' ? 'Сиз ажойиб натижа кўрсатдингиз!' : "Siz ajoyib natija ko'rsatdingiz!",
    'good'      => lang() === 'uz_cyrillic' ? 'Зўр иш! Имтиҳондан осонгина ўтасиз.' : "Zo'r ish! Imtihondan osongina o'tasiz.",
    'average'   => lang() === 'uz_cyrillic' ? 'Яхши, лекин янада яхши бўлиши мумкин.' : "Yaxshi, lekin yanada yaxshi bo'lishi mumkin.",
    'poor'      => lang() === 'uz_cyrillic' ? 'Қайта машқ қилинг ва такрорланг.' : 'Qayta mashq qiling va takrorlang.',
];

// Javoblar (agar ko'rsatish kerak bo'lsa)
$answerDetails = [];
if ($showAnswers) {
    $rows = db()->fetchAll(
        "SELECT q.id qid, q.question_$lang_field q_text, q.image, q.explanation_$lang_field expl, q.category, q.difficulty,
                ta.answer_id user_aid, ta.is_correct
         FROM questions q
         INNER JOIN test_answers ta ON ta.question_id = q.id
         WHERE ta.attempt_id = ? ORDER BY q.id", [$attempt_id]);

    foreach ($rows as $r) {
        $r['user_answer'] = $r['user_aid']
            ? db()->fetch("SELECT answer_$lang_field as txt FROM answers WHERE id=?", [$r['user_aid']])['txt'] ?? ''
            : '';
        $r['correct_answer'] = db()->fetch(
            "SELECT answer_$lang_field as txt FROM answers WHERE question_id=? AND is_correct=1",
            [$r['qid']])['txt'] ?? '';
        $answerDetails[] = $r;
    }
}

render_head(t('your_result'));
render_header();
?>

<style>
.result-hero{background:linear-gradient(135deg,
  <?= $isPass ? '#10B981 0%, #059669' : ($score >= 50 ? '#F59E0B 0%, #D97706' : '#EF4444 0%, #DC2626') ?> 100%);
  color:#fff;padding:60px 20px;text-align:center;position:relative;overflow:hidden}
.result-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at top,rgba(255,255,255,.2),transparent 60%)}
.result-emoji{font-size:96px;margin-bottom:16px;animation:bounce 1s var(--spring) both}
@keyframes bounce{0%{transform:scale(0)}60%{transform:scale(1.2)}80%{transform:scale(.9)}100%{transform:scale(1)}}
.result-percent{font-size:120px;font-weight:900;line-height:1;margin:20px 0;text-shadow:0 4px 20px rgba(0,0,0,.2)}
.result-percent .pct{font-size:60px;opacity:.8}
.result-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;max-width:780px;margin:40px auto 0;
  position:relative;z-index:1}
.result-stat{background:rgba(255,255,255,.15);backdrop-filter:blur(10px);padding:20px;border-radius:var(--r-lg);
  border:1px solid rgba(255,255,255,.2)}
.result-stat .v{font-size:32px;font-weight:800}
.result-stat .l{font-size:13px;opacity:.85;margin-top:4px}
.confetti{position:fixed;width:10px;height:10px;pointer-events:none;z-index:1000;border-radius:2px}

.answer-detail{background:#fff;border:1px solid var(--border);border-radius:var(--r-lg);padding:20px;margin-bottom:16px}
.answer-detail.correct{border-left:4px solid var(--success)}
.answer-detail.wrong{border-left:4px solid var(--danger)}
.answer-detail .qtxt{font-size:16px;font-weight:600;margin-bottom:14px}
.ans-row{padding:10px 14px;border-radius:var(--r-sm);margin-bottom:8px;font-size:14px;display:flex;align-items:flex-start;gap:10px}
.ans-row.user{background:#FEE2E2;color:var(--danger-dark)}
.ans-row.user.is-correct{background:var(--success-light);color:var(--success-dark)}
.ans-row.correct{background:var(--success-light);color:var(--success-dark)}
.ans-row .label{font-size:11px;font-weight:700;text-transform:uppercase;flex-shrink:0;width:100px}

@media (max-width: 640px){
  .result-percent{font-size:80px}
  .result-emoji{font-size:64px}
  .result-stats{grid-template-columns:repeat(2,1fr);gap:12px}
}

/* ============================================================
   MOBILE-FIRST OVERRIDES v3.0 — test-result.php
   ============================================================ */
@media (max-width: 880px){
  .result-hero{padding:40px 16px}
  .result-emoji{font-size:60px;margin-bottom:10px}
  .result-percent{font-size:78px;margin:14px 0}
  .result-percent .pct{font-size:38px}
  .result-stats{grid-template-columns:repeat(2,1fr);gap:10px;margin-top:24px;max-width:100%}
  .result-stat{padding:14px 12px;border-radius:12px}
  .result-stat .v{font-size:24px}
  .result-stat .l{font-size:11px;line-height:1.3}
  .result-hero h1{font-size:26px !important;margin-bottom:6px !important}
  .result-hero p{font-size:14px !important}
  .answer-detail{padding:14px;margin-bottom:10px;border-radius:10px}
  .answer-detail .qtxt{font-size:14px;margin-bottom:10px}
  .ans-row{padding:8px 10px;font-size:13px;gap:8px;flex-direction:column;align-items:flex-start}
  .ans-row .label{width:auto;font-size:10px;margin-bottom:2px}
}

@media (max-width: 480px){
  .result-hero{padding:30px 14px}
  .result-emoji{font-size:48px}
  .result-percent{font-size:60px;margin:10px 0}
  .result-percent .pct{font-size:30px}
  .result-stat .v{font-size:20px}
  .result-hero h1{font-size:22px !important}
  .result-hero p{font-size:13px !important}
}
</style>

<section class="result-hero fade-in">
  <div class="result-emoji"><?= $resultEmoji ?></div>
  <h1 style="color:#fff;font-size:36px;font-weight:800;margin-bottom:8px;position:relative;z-index:1">
    <?= $resultTitles[$resultClass] ?>
  </h1>
  <p style="opacity:.95;font-size:17px;position:relative;z-index:1"><?= $resultMsgs[$resultClass] ?></p>

  <?php if ($expired): ?>
    <div style="margin:20px auto;max-width:400px;padding:10px 16px;background:rgba(255,255,255,.2);border-radius:var(--r-md);font-size:14px;position:relative;z-index:1">
      ⏰ <?= t('time_up') ?>
    </div>
  <?php endif; ?>

  <div class="result-percent" data-percent="<?= $score ?>">
    <span id="scoreNum">0</span><span class="pct">%</span>
  </div>

  <div class="result-stats">
    <div class="result-stat">
      <div class="v"><?= $correct ?></div>
      <div class="l"><?= t('correct') ?></div>
    </div>
    <div class="result-stat">
      <div class="v"><?= $wrong ?></div>
      <div class="l"><?= t('wrong') ?></div>
    </div>
    <div class="result-stat">
      <div class="v"><?= $unanswered ?></div>
      <div class="l"><?= lang()==='uz_cyrillic' ? 'Жавобсиз' : 'Javobsiz' ?></div>
    </div>
    <div class="result-stat">
      <div class="v"><?= sprintf('%d:%02d', $mins, $secs) ?></div>
      <div class="l"><?= lang()==='uz_cyrillic' ? 'Сарф вақт' : 'Sarf vaqt' ?></div>
    </div>
  </div>
</section>

<section class="section-sm">
  <div class="container" style="max-width:780px">

    <!-- Action buttons -->
    <div class="flex gap-3 justify-center flex-wrap mb-4">
      <a href="/user/testlar.php" class="btn btn-primary btn-lg">
        <?= icon('refresh', 18) ?> <?= t('try_again') ?>
      </a>
      <?php if (!$showAnswers): ?>
      <a href="?attempt=<?= $attempt_id ?>&answers=1" class="btn btn-outline btn-lg">
        <?= icon('eye', 18) ?> <?= t('view_answers') ?>
      </a>
      <?php endif; ?>
      <?php if ($isPass): ?>
      <button class="btn btn-success btn-lg" onclick="downloadCert()">
        <?= icon('download', 18) ?> <?= t('download_certificate') ?>
      </button>
      <?php endif; ?>
      <button class="btn btn-light btn-lg" onclick="shareResult()">
        <?= icon('send', 18) ?> <?= t('share_result') ?>
      </button>
    </div>

    <!-- Statistika kartasi -->
    <div class="card mb-4">
      <h3 style="margin-bottom:18px;display:flex;align-items:center;gap:10px">
        <?= icon('chart', 22) ?> <?= lang()==='uz_cyrillic' ? 'Тафсилот' : 'Tafsilot' ?>
      </h3>
      <div class="grid-2 gap-3">
        <div>
          <div class="text-soft mb-1" style="font-size:13px"><?= t('tickets') ?></div>
          <div class="font-semibold"><?= e($attempt['title'] ?? '—') ?></div>
        </div>
        <div>
          <div class="text-soft mb-1" style="font-size:13px"><?= t('date') ?></div>
          <div class="font-semibold"><?= date('d.m.Y H:i', strtotime($attempt['finished_at'])) ?></div>
        </div>
        <div>
          <div class="text-soft mb-1" style="font-size:13px"><?= lang()==='uz_cyrillic' ? 'Самарадорлик' : 'Samaradorlik' ?></div>
          <div class="progress mt-1"><div class="progress-bar" style="width:<?= $score ?>%"></div></div>
          <div class="font-semibold mt-1"><?= $score ?>%</div>
        </div>
        <div>
          <div class="text-soft mb-1" style="font-size:13px"><?= lang()==='uz_cyrillic' ? 'Натижа' : 'Natija' ?></div>
          <?php if ($isPass): ?>
            <span class="badge badge-success" style="font-size:13px;padding:6px 14px">✓ <?= lang()==='uz_cyrillic' ? "Ўтди" : "O'tdi" ?></span>
          <?php else: ?>
            <span class="badge badge-warning" style="font-size:13px;padding:6px 14px">✗ <?= lang()==='uz_cyrillic' ? "Ўтмади" : "O'tmadi" ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Javoblar tahlili -->
    <?php if ($showAnswers && !empty($answerDetails)): ?>
    <h2 style="margin-bottom:18px;display:flex;align-items:center;gap:10px">
      <?= icon('document', 24) ?> <?= lang()==='uz_cyrillic' ? 'Жавоблар таҳлили' : 'Javoblar tahlili' ?>
    </h2>

    <?php foreach ($answerDetails as $i => $a):
      $isCorrect = !empty($a['is_correct']);
    ?>
    <div class="answer-detail <?= $isCorrect?'correct':'wrong' ?>">
      <div class="flex justify-between items-start gap-2 mb-2">
        <div class="qtxt"><?= ($i+1).'. '.nl2br(e($a['q_text'])) ?></div>
        <span class="badge badge-<?= $isCorrect?'success':'danger' ?>">
          <?= $isCorrect ? '✓ '.t('correct') : '✗ '.t('wrong') ?>
        </span>
      </div>
      <?php if (!empty($a['image'])): ?>
        <img src="<?= e($a['image']) ?>" style="max-height:200px;margin-bottom:14px;border-radius:8px">
      <?php endif; ?>
      <?php if (!$isCorrect && !empty($a['user_answer'])): ?>
        <div class="ans-row user">
          <span class="label"><?= t('your_answer') ?>:</span>
          <span><?= e($a['user_answer']) ?></span>
        </div>
      <?php elseif ($isCorrect && !empty($a['user_answer'])): ?>
        <div class="ans-row user is-correct">
          <span class="label">✓ <?= t('your_answer') ?>:</span>
          <span><?= e($a['user_answer']) ?></span>
        </div>
      <?php endif; ?>
      <?php if (!$isCorrect): ?>
      <div class="ans-row correct">
        <span class="label"><?= t('correct_answer') ?>:</span>
        <span><?= e($a['correct_answer']) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($a['expl'])): ?>
      <div style="margin-top:12px;padding:12px;background:var(--bg-soft);border-radius:8px;font-size:14px;color:var(--text-soft)">
        <strong><?= t('explanation') ?>:</strong> <?= nl2br(e($a['expl'])) ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<script>
// Score countup animation
const scoreEl = document.getElementById('scoreNum');
const target = <?= $score ?>;
let cur = 0;
const step = target / 50;
const interval = setInterval(() => {
  cur += step;
  if (cur >= target) {
    scoreEl.textContent = target.toFixed(target % 1 ? 1 : 0);
    clearInterval(interval);
  } else {
    scoreEl.textContent = Math.floor(cur);
  }
}, 30);

// Confetti animation (a'lo bo'lsa)
<?php if ($isPass): ?>
function launchConfetti(){
  const colors = ['#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899'];
  for (let i = 0; i < 80; i++) {
    setTimeout(() => {
      const c = document.createElement('div');
      c.className = 'confetti';
      c.style.left = Math.random() * 100 + 'vw';
      c.style.top = '-10px';
      c.style.background = colors[Math.floor(Math.random()*colors.length)];
      c.style.transform = `rotate(${Math.random()*360}deg)`;
      c.style.animation = `confetti ${2 + Math.random() * 2}s linear forwards`;
      c.style.animationDelay = Math.random() * 0.5 + 's';
      document.body.appendChild(c);
      setTimeout(() => c.remove(), 4500);
    }, i * 30);
  }
}
launchConfetti();
<?php endif; ?>

function shareResult(){
  const text = `<?= lang()==='uz_cyrillic' ? "Мен" : "Men" ?> VatanParvar Yaypan'da <?= $score ?>% <?= lang()==='uz_cyrillic' ? "натижа кўрсатдим!" : "natija ko'rsatdim!" ?>`;
  if (navigator.share) {
    navigator.share({title:'<?= e(setting('site_name')) ?>', text, url:window.location.origin});
  } else {
    navigator.clipboard.writeText(text + ' ' + window.location.origin).then(() => {
      toast('<?= lang()==='uz_cyrillic' ? "Нусхаланди!" : "Nusxalandi!" ?>', 'success');
    });
  }
}

function downloadCert(){
  // Sertifikat PDF (oddiy versiyasi - browser print qila oladi)
  const w = window.open('', '_blank');
  w.document.write(`
    <html><head><title>Sertifikat</title>
    <style>
      body{margin:0;padding:40px;font-family:Georgia,serif;background:#F0F9FF;display:flex;align-items:center;justify-content:center;min-height:100vh}
      .cert{background:#fff;padding:60px;border:8px solid #3B82F6;text-align:center;max-width:800px;
        box-shadow:0 20px 60px rgba(0,0,0,.1);position:relative}
      .cert::before{content:'';position:absolute;inset:14px;border:2px solid #93C5FD;pointer-events:none}
      h1{font-size:48px;color:#1E40AF;margin-bottom:8px;font-style:italic}
      .sub{color:#475569;letter-spacing:.3em;text-transform:uppercase;font-size:14px;margin-bottom:50px}
      .name{font-size:42px;color:#0F172A;margin:30px 0;border-bottom:1px solid #E2E8F0;padding-bottom:20px}
      .body{color:#475569;line-height:1.8;font-size:16px}
      .score{font-size:64px;font-weight:bold;color:#10B981;margin:30px 0}
      .footer{margin-top:50px;display:flex;justify-content:space-between;align-items:center;font-size:14px;color:#64748B}
    </style></head><body>
    <div class="cert">
      <h1>Sertifikat</h1>
      <div class="sub">— Avtomaktab Imtihoni —</div>
      <div class="body">Quyidagi shaxsga beriladi</div>
      <div class="name"><?= e($u['first_name'].' '.$u['last_name']) ?></div>
      <div class="body">VatanParvar Yaypan platformasida nazariy imtihondan muvaffaqiyatli o'tdi</div>
      <div class="score"><?= $score ?>%</div>
      <div class="footer">
        <div><strong><?= e(setting('site_name')) ?></strong></div>
        <div><?= date('d.m.Y') ?></div>
      </div>
    </div>
    <script>setTimeout(() => window.print(), 500);<\/script>
    </body></html>
  `);
  w.document.close();
}
</script>
</body></html>
