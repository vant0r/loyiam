<?php
/**
 * user/testlar.php — STANDALONE testlar ro'yxati
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$u = current_user();
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

$inprogress = db()->fetchAll(
    "SELECT a.*, t.title_$lang_field title, t.questions_count, t.time_minutes
     FROM test_attempts a LEFT JOIN tickets t ON a.ticket_id=t.id
     WHERE a.user_id=? AND a.status='in_progress' ORDER BY a.started_at DESC", [$u['id']]);
$completed = db()->fetchAll(
    "SELECT a.*, t.title_$lang_field title FROM test_attempts a
     LEFT JOIN tickets t ON a.ticket_id=t.id
     WHERE a.user_id=? AND a.status='completed' ORDER BY a.finished_at DESC LIMIT 30", [$u['id']]);
$tickets = db()->fetchAll("SELECT * FROM tickets WHERE status='active' ORDER BY ticket_number ASC");
$site_name = setting('site_name', SITE_NAME);
$palettes = ['','violet','cyan','amber','emerald','rose','indigo'];
$palette_colors = [
    ''       => ['#3B82F6','#06B6D4'],
    'violet' => ['#8B5CF6','#EC4899'],
    'cyan'   => ['#06B6D4','#10B981'],
    'amber'  => ['#F59E0B','#F43F5E'],
    'emerald'=> ['#10B981','#06B6D4'],
    'rose'   => ['#F43F5E','#EC4899'],
    'indigo' => ['#6366F1','#8B5CF6'],
];
?><!DOCTYPE html>
<html lang="<?= lang() === 'uz_cyrillic' ? 'uz-Cyrl' : 'uz' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#3B82F6">
<title><?= e(t('tests')) ?> — <?= e($site_name) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(setting('site_logo', '/assets/images/logo.svg')) ?>">
<style>
<?= base_css() ?>
<?= panel_css() ?>

/* === USER/TESTLAR.PHP custom === */
.section-card{background:#fff;border:1px solid #EEF1F5;border-radius:14px;overflow:hidden;margin-bottom:16px}
.section-card-head{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #EEF1F5;background:#FAFBFC;flex-wrap:wrap;gap:8px}
.section-card-title{font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px}
.count-pill{display:inline-flex;padding:2px 8px;border-radius:100px;background:var(--bg-mute);color:var(--text-soft);font-size:11px;font-weight:600}
.section-card-body{padding:14px}

.tickets-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(220px,100%),1fr));gap:12px}
.ticket-card{position:relative;background:#fff;border:1px solid #EEF1F5;border-radius:14px;padding:16px;text-decoration:none;color:inherit;transition:all .25s;display:flex;flex-direction:column;gap:12px;overflow:hidden;isolation:isolate}
.ticket-card::before{content:'';position:absolute;top:0;left:0;width:4px;bottom:0;background:linear-gradient(180deg,var(--tc),var(--tcs));border-radius:4px 0 0 4px}
.ticket-card:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(15,23,42,.08);border-color:var(--primary-200);color:inherit}
.ticket-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.ticket-card-icon{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,var(--tc),var(--tcs));color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 12px rgba(59,130,246,.3)}
.ticket-card h3{font-size:14px;font-weight:700;margin:0;line-height:1.3}
.ticket-meta{display:flex;gap:6px;flex-wrap:wrap;font-size:11.5px;color:var(--text-soft)}
.ticket-meta span{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;background:var(--bg-soft);border-radius:100px;font-weight:500}
.ticket-cta{display:flex;justify-content:space-between;align-items:center;font-size:12.5px;font-weight:700;color:var(--primary);margin-top:auto}

.recent-item{display:flex;align-items:center;gap:12px;padding:11px 14px;background:#fff;border:1px solid #EEF1F5;border-radius:10px;text-decoration:none;color:inherit;margin-bottom:6px;transition:all .15s}
.recent-item:hover{border-color:var(--primary-200);transform:translateX(2px)}
.recent-icon{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;flex-shrink:0;font-variant-numeric:tabular-nums}
.recent-icon.success{background:#D1FAE5;color:#065F46}
.recent-icon.warning{background:#FEF3C7;color:#92400E}
.recent-icon.danger{background:#FEE2E2;color:#991B1B}
.recent-body{flex:1;min-width:0}
.recent-title{font-weight:600;font-size:13.5px;margin-bottom:3px}
.recent-meta{font-size:11.5px;color:var(--text-mute)}

.empty-state{padding:36px 20px;text-align:center;background:linear-gradient(180deg,var(--bg-soft),transparent);border-radius:12px}
.empty-icon{width:60px;height:60px;border-radius:14px;background:#fff;border:1px solid var(--border);margin:0 auto 12px;display:flex;align-items:center;justify-content:center;color:var(--text-mute)}

.continue-card{background:#fff;border-left:3px solid var(--warning);border:1px solid #EEF1F5;border-left:3px solid var(--warning);border-radius:14px;padding:16px;display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:center;margin-bottom:8px}
.continue-icon{width:42px;height:42px;background:#FEF3C7;color:#92400E;border-radius:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
@media (max-width:520px){.continue-card{grid-template-columns:1fr;gap:8px}.continue-card a{width:100%;justify-content:center}}
</style>
</head>
<body>
<div class="layout">
<?= panel_sidebar('user', 'tests') ?>
<main class="main">

<div class="page-header-modern">
  <div>
    <div class="page-eyebrow"><?= icon('document', 12) ?> <?= lang()==='uz_cyrillic' ? "Имтиҳон тайёрлов" : "Imtihon tayyorlov" ?></div>
    <h1><?= t('tests') ?></h1>
    <div class="page-subtitle"><?= lang()==='uz_cyrillic' ? "Билет танлаб тестни бошланг" : "Bilet tanlab testni boshlang" ?></div>
  </div>
  <a href="/user/natijalar.php" class="btn btn-light btn-sm"><?= icon('chart', 14) ?> <?= t('results') ?></a>
</div>

<?php if (!empty($inprogress)): ?>
<div class="section-card" style="border-left:3px solid var(--warning)">
  <div class="section-card-head">
    <div class="section-card-title"><?= icon('clock', 16) ?> <?= lang()==='uz_cyrillic' ? "Давом этаётган" : "Davom etayotgan" ?> <span class="count-pill"><?= count($inprogress) ?></span></div>
  </div>
  <div class="section-card-body">
    <?php foreach ($inprogress as $r):
      $elapsed = time() - strtotime($r['started_at']);
      $remaining = max(0, ($r['time_minutes'] * 60) - $elapsed);
      $isExpired = $remaining <= 0;
      $rem_min = floor($remaining/60); $rem_sec = $remaining%60;
    ?>
    <div class="continue-card">
      <div class="continue-icon"><?= icon('clock', 20) ?></div>
      <div>
        <div style="font-weight:700;font-size:14px"><?= e($r['title'] ?? '—') ?></div>
        <div style="font-size:12px;color:var(--text-soft);margin-top:3px">
          <?= date('d.m H:i', strtotime($r['started_at'])) ?>
          <?php if (!$isExpired): ?> · <?= $rem_min ?>:<?= str_pad($rem_sec,2,'0',STR_PAD_LEFT) ?><?php endif; ?>
        </div>
      </div>
      <a href="/user/test.php?attempt=<?= $r['id'] ?>" class="btn btn-primary btn-sm"><?= icon('play', 14) ?> <?= t('continue_test') ?></a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="section-card">
  <div class="section-card-head">
    <div class="section-card-title"><?= icon('ticket', 16) ?> <?= lang()==='uz_cyrillic' ? "Мавжуд билетлар" : "Mavjud biletlar" ?> <span class="count-pill"><?= count($tickets) ?></span></div>
    <span style="font-size:11.5px;color:var(--text-mute)"><?= lang()==='uz_cyrillic' ? "Билет устида босинг" : "Bilet ustida bosing" ?></span>
  </div>
  <div class="section-card-body">
    <?php if (empty($tickets)): ?>
      <div class="empty-state"><div class="empty-icon"><?= icon('ticket', 28) ?></div><h3 style="font-size:15px"><?= lang()==='uz_cyrillic' ? "Билетлар йўқ" : "Biletlar yo'q" ?></h3></div>
    <?php else: ?>
      <div class="tickets-grid">
        <?php foreach ($tickets as $i => $tk):
          $palette = $palettes[$i % count($palettes)];
          [$tc, $tcs] = $palette_colors[$palette];
        ?>
        <a href="/user/test.php?ticket=<?= $tk['id'] ?>" class="ticket-card" style="--tc:<?= $tc ?>;--tcs:<?= $tcs ?>">
          <div class="ticket-card-head">
            <div class="ticket-card-icon"><?= icon('ticket', 18) ?></div>
            <span style="color:var(--text-mute);font-size:10.5px;font-weight:700">#<?= $tk['ticket_number'] ?></span>
          </div>
          <h3><?= e($tk['title_'.$lang_field]) ?></h3>
          <div class="ticket-meta">
            <span><?= icon('help', 11) ?> <?= $tk['questions_count'] ?></span>
            <span><?= icon('clock', 11) ?> <?= $tk['time_minutes'] ?> <?= lang()==='uz_cyrillic' ? "дақ" : "daq" ?></span>
          </div>
          <div class="ticket-cta">
            <span><?= icon('play', 12) ?> <?= t('start_test') ?></span>
            <?= icon('arrow-right', 13) ?>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="section-card">
  <div class="section-card-head">
    <div class="section-card-title"><?= icon('check-circle', 16) ?> <?= lang()==='uz_cyrillic' ? "Тугалланганлар" : "Tugallangan" ?> <span class="count-pill"><?= count($completed) ?></span></div>
    <a href="/user/natijalar.php" style="font-size:12px;color:var(--primary);font-weight:600;text-decoration:none"><?= t('view_all') ?> →</a>
  </div>
  <div class="section-card-body">
    <?php if (empty($completed)): ?>
      <div class="empty-state"><div class="empty-icon"><?= icon('document', 28) ?></div><h3 style="font-size:15px"><?= t('no_tests') ?></h3><p style="font-size:13px;color:var(--text-soft);margin-top:6px"><?= t('first_test') ?></p></div>
    <?php else: foreach (array_slice($completed, 0, 10) as $r):
      $p = (float)$r['score_percent'];
      $cls = $p>=80?'success':($p>=50?'warning':'danger');
    ?>
      <a href="/user/test-result.php?attempt=<?= $r['id'] ?>" class="recent-item">
        <div class="recent-icon <?= $cls ?>"><?= round($p) ?></div>
        <div class="recent-body">
          <div class="recent-title"><?= e($r['title'] ?? '—') ?></div>
          <div class="recent-meta"><?= date('d.m.Y H:i', strtotime($r['finished_at'] ?? $r['started_at'])) ?> · <?= $r['correct_answers'] ?>/<?= $r['total_questions'] ?></div>
        </div>
        <span class="badge badge-<?= $cls ?>"><?= round($p,1) ?>%</span>
      </a>
    <?php endforeach; endif; ?>
  </div>
</div>

</main>
</div>
<script><?= panel_js() ?></script>
</body></html>
