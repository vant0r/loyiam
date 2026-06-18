<?php
/**
 * user/natijalar.php — STANDALONE test results history
 */
require_once __DIR__ . '/../includes/bootstrap.php';
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
if ($status_f === 'pass') $where .= " AND a.score_percent >= 80";
elseif ($status_f === 'fail') $where .= " AND a.score_percent < 50 AND a.status='completed'";

$results = db()->fetchAll(
    "SELECT a.*, t.title_$lang_field title FROM test_attempts a LEFT JOIN tickets t ON a.ticket_id=t.id
     $where ORDER BY a.started_at DESC LIMIT 100", $params);
$tickets = db()->fetchAll("SELECT * FROM tickets WHERE status='active' ORDER BY ticket_number");

$total_attempts = count($results);
$total_correct = array_sum(array_column($results, 'correct_answers'));
$total_questions = array_sum(array_column($results, 'total_questions'));
$avg_percent = $total_questions ? round($total_correct / $total_questions * 100, 1) : 0;
$pass_count = 0; $fail_count = 0;
foreach ($results as $r) {
    if ((float)$r['score_percent'] >= 80) $pass_count++;
    elseif ((float)$r['score_percent'] < 50 && $r['status']==='completed') $fail_count++;
}

$site_name = setting('site_name', SITE_NAME);
?><!DOCTYPE html>
<html lang="<?= lang() === 'uz_cyrillic' ? 'uz-Cyrl' : 'uz' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#3B82F6">
<title><?= e(t('results')) ?> — <?= e($site_name) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(setting('site_logo', '/assets/images/logo.svg')) ?>">
<style>
<?= base_css() ?>
<?= panel_css() ?>

/* === USER/NATIJALAR.PHP custom === */
.metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(180px,100%),1fr));gap:12px;margin-bottom:18px}
.metric-card{background:#fff;border:1px solid #EEF1F5;border-radius:14px;padding:16px}
.metric-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:10px}
.metric-icon.blue{background:#EFF6FF;color:#2563EB}
.metric-icon.green{background:#D1FAE5;color:#065F46}
.metric-icon.danger{background:#FEE2E2;color:#991B1B}
.metric-icon.violet{background:#EDE9FE;color:#5B21B6}
.metric-value{font-size:22px;font-weight:800;line-height:1.05;font-variant-numeric:tabular-nums}
.metric-label{font-size:11.5px;color:var(--text-soft);margin-top:3px}

.section-card{background:#fff;border:1px solid #EEF1F5;border-radius:14px;overflow:hidden;margin-bottom:14px}
.section-card-head{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #EEF1F5;background:#FAFBFC;flex-wrap:wrap;gap:8px}
.section-card-title{font-size:13.5px;font-weight:700;display:flex;align-items:center;gap:8px}
.count-pill{display:inline-flex;padding:2px 8px;border-radius:100px;background:var(--bg-mute);color:var(--text-soft);font-size:11px;font-weight:600}
.section-card-body{padding:14px}
.section-card-body.flush{padding:0}

.chip{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:100px;background:var(--bg-mute);color:var(--text-soft);font-size:11.5px;font-weight:600;text-decoration:none;border:1px solid transparent;transition:all .15s}
.chip:hover{background:var(--bg-hover);color:var(--text)}
.chip.is-active{background:var(--primary-light);color:var(--primary-dark);border-color:var(--primary-200)}

.result-row{display:flex;gap:14px;align-items:center;padding:12px 14px;background:#fff;border:1px solid #EEF1F5;border-radius:12px;margin-bottom:8px;transition:all .15s;text-decoration:none;color:inherit}
.result-row:hover{border-color:var(--primary-200);transform:translateX(2px);color:inherit}
.progress-circle{position:relative;width:48px;height:48px;flex-shrink:0}
.progress-circle svg{transform:rotate(-90deg);width:100%;height:100%}
.pc-track{fill:none;stroke:var(--bg-mute);stroke-width:5}
.pc-fill{fill:none;stroke:var(--pc-color);stroke-width:5;stroke-linecap:round;stroke-dasharray:126;stroke-dashoffset:calc(126 * (1 - var(--pc-pct)));transition:stroke-dashoffset .8s}
.pc-text{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;font-variant-numeric:tabular-nums}
.result-body{flex:1;min-width:0}
.result-title{font-weight:600;font-size:14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.result-meta{font-size:11.5px;color:var(--text-mute);display:flex;gap:8px;flex-wrap:wrap;margin-top:3px}

.filter-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;align-items:end}

.empty-state{padding:36px 20px;text-align:center;background:linear-gradient(180deg,var(--bg-soft),transparent);border-radius:12px}
.empty-icon{width:60px;height:60px;border-radius:14px;background:#fff;border:1px solid var(--border);margin:0 auto 12px;display:flex;align-items:center;justify-content:center;color:var(--text-mute)}
</style>
</head>
<body>
<div class="layout">
<?= panel_sidebar('user', 'results') ?>
<main class="main">

<div class="page-header-modern">
  <div>
    <div class="page-eyebrow"><?= icon('chart', 12) ?> <?= lang()==='uz_cyrillic' ? "Сизнинг тарихингиз" : "Sizning tarixingiz" ?></div>
    <h1><?= t('results') ?></h1>
    <div class="page-subtitle"><?= lang()==='uz_cyrillic' ? "Барча тест натижалари" : "Barcha test natijalari" ?></div>
  </div>
  <a href="/user/testlar.php" class="btn btn-primary btn-sm"><?= icon('play', 14) ?> <?= t('start_test') ?></a>
</div>

<div class="metric-grid">
  <div class="metric-card"><div class="metric-icon blue"><?= icon('document', 18) ?></div><div class="metric-value"><?= $total_attempts ?></div><div class="metric-label"><?= lang()==='uz_cyrillic' ? "Уринишлар" : "Urinishlar" ?></div></div>
  <div class="metric-card"><div class="metric-icon green"><?= icon('check-circle', 18) ?></div><div class="metric-value"><?= $total_correct ?></div><div class="metric-label"><?= lang()==='uz_cyrillic' ? "Тўғри" : "To'g'ri" ?></div></div>
  <div class="metric-card"><div class="metric-icon danger"><?= icon('x-circle', 18) ?></div><div class="metric-value"><?= $total_questions - $total_correct ?></div><div class="metric-label"><?= lang()==='uz_cyrillic' ? "Хато" : "Xato" ?></div></div>
  <div class="metric-card"><div class="metric-icon violet"><?= icon('award', 18) ?></div><div class="metric-value"><?= $avg_percent ?>%</div><div class="metric-label"><?= lang()==='uz_cyrillic' ? "Ўртача" : "O'rtacha" ?></div></div>
</div>

<div class="section-card">
  <div class="section-card-head">
    <div class="section-card-title"><?= icon('filter', 16) ?> <?= lang()==='uz_cyrillic' ? "Филтр" : "Filtr" ?></div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <a href="?" class="chip <?= !$status_f && !$from && !$to && !$tid?'is-active':'' ?>"><?= lang()==='uz_cyrillic' ? "Барчаси" : "Barchasi" ?> <span class="count-pill"><?= count($results) ?></span></a>
      <a href="?status=pass" class="chip <?= $status_f==='pass'?'is-active':'' ?>" style="<?= $status_f==='pass'?'background:#D1FAE5;color:#065F46':'' ?>"><?= icon('check-circle', 11) ?> <?= lang()==='uz_cyrillic' ? "Ўтган" : "O'tgan" ?> <span class="count-pill"><?= $pass_count ?></span></a>
      <a href="?status=fail" class="chip <?= $status_f==='fail'?'is-active':'' ?>" style="<?= $status_f==='fail'?'background:#FEE2E2;color:#991B1B':'' ?>"><?= icon('x-circle', 11) ?> <?= lang()==='uz_cyrillic' ? "Йиқилган" : "Yiqilgan" ?> <span class="count-pill"><?= $fail_count ?></span></a>
    </div>
  </div>
  <div class="section-card-body">
    <form method="get" class="filter-form">
      <div class="form-group" style="margin:0"><label class="form-label"><?= lang()==='uz_cyrillic' ? "Дан" : "Dan" ?></label><input type="date" name="from" value="<?= e($from) ?>" class="form-control"></div>
      <div class="form-group" style="margin:0"><label class="form-label"><?= lang()==='uz_cyrillic' ? "Гача" : "Gacha" ?></label><input type="date" name="to" value="<?= e($to) ?>" class="form-control"></div>
      <div class="form-group" style="margin:0"><label class="form-label"><?= lang()==='uz_cyrillic' ? "Билет" : "Bilet" ?></label>
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

<div class="section-card">
  <div class="section-card-head">
    <div class="section-card-title"><?= icon('clock', 16) ?> <?= lang()==='uz_cyrillic' ? "Натижалар" : "Natijalar" ?> <span class="count-pill"><?= count($results) ?></span></div>
  </div>
  <div class="section-card-body">
    <?php if (empty($results)): ?>
      <div class="empty-state"><div class="empty-icon"><?= icon('document', 28) ?></div><h3 style="font-size:15px"><?= lang()==='uz_cyrillic' ? "Натижалар йўқ" : "Natijalar yo'q" ?></h3><p style="font-size:13px;color:var(--text-soft);margin:8px 0 14px"><?= lang()==='uz_cyrillic' ? "Тестни бошланг" : "Testni boshlang" ?></p><a href="/user/testlar.php" class="btn btn-primary btn-sm"><?= icon('play', 14) ?> <?= t('start_test') ?></a></div>
    <?php else: foreach ($results as $r):
      $p = (float)$r['score_percent'];
      $cls = $p>=80?'success':($p>=50?'warning':'danger');
      $cls_color = $p>=80?'#10B981':($p>=50?'#F59E0B':'#EF4444');
    ?>
      <a href="/user/test-result.php?attempt=<?= $r['id'] ?>" class="result-row">
        <div class="progress-circle" style="--pc-pct:<?= $p/100 ?>;--pc-color:<?= $cls_color ?>">
          <svg viewBox="0 0 48 48"><circle class="pc-track" cx="24" cy="24" r="20"/><circle class="pc-fill" cx="24" cy="24" r="20"/></svg>
          <div class="pc-text"><?= round($p) ?></div>
        </div>
        <div class="result-body">
          <div class="result-title">
            <?= e($r['title'] ?? 'Test') ?>
            <?php if ($r['status']==='completed'): ?><span class="badge badge-<?= $cls ?>"><?= round($p,1) ?>%</span>
            <?php else: ?><span class="badge badge-warning"><?= t('in_progress') ?></span><?php endif; ?>
          </div>
          <div class="result-meta">
            <span><?= icon('calendar', 11) ?> <?= date('d.m.Y H:i', strtotime($r['started_at'])) ?></span>
            <span><?= icon('check', 11) ?> <?= $r['correct_answers'] ?>/<?= $r['total_questions'] ?></span>
          </div>
        </div>
        <?= icon('arrow-right', 14) ?>
      </a>
    <?php endforeach; endif; ?>
  </div>
</div>

</main>
</div>
<script><?= panel_js() ?></script>
</body></html>
