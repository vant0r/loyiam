<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$u = current_user();
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
$tid  = (int)($_GET['ticket'] ?? 0);
$status_f = $_GET['status'] ?? '';

$where = "WHERE a.user_id = ?";
$params = [$u['id']];
if ($from) { $where .= " AND DATE(a.started_at) >= ?"; $params[] = $from; }
if ($to)   { $where .= " AND DATE(a.started_at) <= ?"; $params[] = $to; }
if ($tid)  { $where .= " AND a.ticket_id = ?"; $params[] = $tid; }
if ($status_f === 'pass') { $where .= " AND a.score_percent >= 80"; }
elseif ($status_f === 'fail') { $where .= " AND a.score_percent < 50 AND a.status='completed'"; }

$results = db()->fetchAll(
    "SELECT a.*, t.title_$lang_field title
     FROM test_attempts a LEFT JOIN tickets t ON a.ticket_id=t.id
     $where ORDER BY a.started_at DESC LIMIT 100", $params);

$tickets = db()->fetchAll("SELECT * FROM tickets WHERE status='active' ORDER BY ticket_number");

// Umumiy statistika
$total_attempts = count($results);
$total_correct = array_sum(array_column($results, 'correct_answers'));
$total_questions = array_sum(array_column($results, 'total_questions'));
$avg_percent = $total_questions ? round($total_correct / $total_questions * 100, 1) : 0;
$pass_count = 0; $fail_count = 0;
foreach ($results as $r) {
    if ((float)$r['score_percent'] >= 80) $pass_count++;
    elseif ((float)$r['score_percent'] < 50 && $r['status']==='completed') $fail_count++;
}

render_head(t('results'));
?>
<div class="layout">
<?php render_sidebar('user', 'results'); ?>
<main class="main">

  <div class="page-header-modern">
    <div>
      <div class="page-eyebrow"><?= icon('chart', 12) ?> <?= lang()==='uz_cyrillic' ? "Сизнинг тарихингиз" : "Sizning tarixingiz" ?></div>
      <h1><?= t('results') ?></h1>
      <div class="page-subtitle">
        <?= lang()==='uz_cyrillic' ? "Барча тест натижалари ва ўз ўсишингизни кузатинг" : "Barcha test natijalari va o'z o'sishingizni kuzating" ?>
      </div>
    </div>
    <a href="/user/testlar.php" class="btn btn-primary btn-sm"><?= icon('play', 14) ?> <?= t('start_test') ?></a>
  </div>

  <!-- Stat metrics -->
  <div class="metric-grid mb-3">
    <div class="metric-card is-primary">
      <div class="metric-head">
        <div class="metric-icon"><?= icon('document', 18) ?></div>
      </div>
      <div class="metric-value"><?= $total_attempts ?></div>
      <div class="metric-label"><?= lang()==='uz_cyrillic' ? "Жами уринишлар" : "Jami urinishlar" ?></div>
    </div>
    <div class="metric-card is-success">
      <div class="metric-head">
        <div class="metric-icon"><?= icon('check-circle', 18) ?></div>
        <span class="metric-trend up"><?= $total_questions ? round($total_correct/$total_questions*100) : 0 ?>%</span>
      </div>
      <div class="metric-value"><?= $total_correct ?></div>
      <div class="metric-label"><?= lang()==='uz_cyrillic' ? "Тўғри жавоблар" : "To'g'ri javoblar" ?></div>
    </div>
    <div class="metric-card is-danger">
      <div class="metric-head">
        <div class="metric-icon"><?= icon('x-circle', 18) ?></div>
      </div>
      <div class="metric-value"><?= $total_questions - $total_correct ?></div>
      <div class="metric-label"><?= lang()==='uz_cyrillic' ? "Хато жавоблар" : "Xato javoblar" ?></div>
    </div>
    <div class="metric-card is-violet">
      <div class="metric-head">
        <div class="metric-icon"><?= icon('award', 18) ?></div>
      </div>
      <div class="metric-value"><?= $avg_percent ?>%</div>
      <div class="metric-label"><?= lang()==='uz_cyrillic' ? "Ўртача натижа" : "O'rtacha natija" ?></div>
    </div>
  </div>

  <!-- Filter chips + form -->
  <div class="section-card mb-3">
    <div class="section-card-head">
      <div class="section-card-title"><?= icon('filter', 16) ?> <?= lang()==='uz_cyrillic' ? "Филтр" : "Filtr" ?></div>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <a href="?" class="chip <?= !$status_f && !$from && !$to && !$tid?'is-active':'' ?>"><?= lang()==='uz_cyrillic' ? "Барчаси" : "Barchasi" ?> <span class="count-pill"><?= count($results) ?></span></a>
        <a href="?status=pass" class="chip <?= $status_f==='pass'?'is-active':'' ?>" style="<?= $status_f==='pass'?'background:#D1FAE5;color:#065F46':'' ?>"><?= icon('check-circle', 12) ?> <?= lang()==='uz_cyrillic' ? "Ўтган" : "O'tgan" ?> <span class="count-pill"><?= $pass_count ?></span></a>
        <a href="?status=fail" class="chip <?= $status_f==='fail'?'is-active':'' ?>" style="<?= $status_f==='fail'?'background:#FEE2E2;color:#991B1B':'' ?>"><?= icon('x-circle', 12) ?> <?= lang()==='uz_cyrillic' ? "Йиқилган" : "Yiqilgan" ?> <span class="count-pill"><?= $fail_count ?></span></a>
      </div>
    </div>
    <div class="section-card-body">
      <form method="get" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:12px;align-items:end">
        <div class="form-group" style="margin:0">
          <label class="form-label"><?= lang()==='uz_cyrillic' ? "Дан" : "Dan" ?></label>
          <input type="date" name="from" value="<?= e($from) ?>" class="form-control">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label"><?= lang()==='uz_cyrillic' ? "Гача" : "Gacha" ?></label>
          <input type="date" name="to" value="<?= e($to) ?>" class="form-control">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label"><?= lang()==='uz_cyrillic' ? "Билет" : "Bilet" ?></label>
          <select name="ticket" class="form-control">
            <option value="">— <?= lang()==='uz_cyrillic' ? "Барчаси" : "Barchasi" ?></option>
            <?php foreach ($tickets as $t): ?>
              <option value="<?= $t['id'] ?>" <?= $tid==$t['id']?'selected':'' ?>><?= e($t['title_'.$lang_field]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary"><?= icon('filter', 14) ?> <?= lang()==='uz_cyrillic' ? "Филтрлаш" : "Filtrlash" ?></button>
      </form>
    </div>
  </div>

  <!-- Results -->
  <div class="section-card">
    <div class="section-card-head">
      <div class="section-card-title">
        <?= icon('clock', 16) ?>
        <?= lang()==='uz_cyrillic' ? "Сўнгги натижалар" : "So'nggi natijalar" ?>
        <span class="count-pill"><?= count($results) ?></span>
      </div>
    </div>
    <div class="section-card-body" style="padding:14px">
      <?php if (empty($results)): ?>
        <div class="empty-state-v2">
          <div class="empty-state-v2-icon"><?= icon('document', 32) ?></div>
          <h3><?= lang()==='uz_cyrillic' ? "Натижалар топилмади" : "Natijalar topilmadi" ?></h3>
          <p><?= lang()==='uz_cyrillic' ? "Танланган филтр бўйича тестлар йўқ" : "Tanlangan filtr bo'yicha testlar yo'q" ?></p>
          <a href="/user/testlar.php" class="btn btn-primary"><?= icon('play', 14) ?> <?= t('start_test') ?></a>
        </div>
      <?php else: foreach ($results as $r):
        $p = (float)$r['score_percent'];
        $cls = $p>=80?'success':($p>=50?'warning':'danger');
        $emoji = $p>=80?'🏆':($p>=50?'👍':'📚');
        $circ = 2 * M_PI * 20; // r=20
      ?>
        <a href="/user/test-result.php?attempt=<?= $r['id'] ?>" class="result-card-modern">
          <div class="progress-circle" style="--pc-pct:<?= $p/100 ?>;--pc-color:var(--<?= $cls ?>);--pc-circ:<?= round($circ,2) ?>;width:48px;height:48px">
            <svg viewBox="0 0 48 48">
              <circle class="pc-track" cx="24" cy="24" r="20"/>
              <circle class="pc-fill" cx="24" cy="24" r="20"/>
            </svg>
            <div class="pc-text"><?= round($p) ?></div>
          </div>
          <div class="result-body-modern">
            <div class="result-title-modern">
              <?= e($r['title'] ?? 'Test') ?>
              <?php if ($r['status']==='completed'): ?>
                <span class="badge-soft <?= $cls ?>"><?= round($p,1) ?>%</span>
              <?php else: ?>
                <span class="badge-soft warning"><?= t('in_progress') ?></span>
              <?php endif; ?>
            </div>
            <div class="result-meta-modern">
              <span><?= icon('calendar', 12) ?> <?= date('d.m.Y H:i', strtotime($r['started_at'])) ?></span>
              <span class="activity-meta-dot"></span>
              <span><?= icon('check', 12) ?> <?= $r['correct_answers'] ?>/<?= $r['total_questions'] ?></span>
              <?php if ($r['status']==='completed' && $r['finished_at']):
                $dur = strtotime($r['finished_at']) - strtotime($r['started_at']);
                $mm = floor($dur/60); $ss = $dur%60;
              ?>
                <span class="activity-meta-dot"></span>
                <span><?= icon('clock', 12) ?> <?= $mm ?>:<?= str_pad($ss,2,'0',STR_PAD_LEFT) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <span class="data-cell-actions">
            <span class="btn btn-light btn-sm btn-icon"><?= icon('arrow-right', 14) ?></span>
          </span>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </div>

</main>
</div>
</body></html>
