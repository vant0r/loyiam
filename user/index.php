<?php
/**
 * user/index.php — STANDALONE foydalanuvchi dashboard
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$u = current_user();
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

// ============================================================
// STATISTIKA
// ============================================================
$stat_total   = (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts WHERE user_id=? AND status='completed'", [$u['id']])['c'] ?? 0);
$stat_correct = (int)(db()->fetch("SELECT COALESCE(SUM(correct_answers),0) c FROM test_attempts WHERE user_id=? AND status='completed'", [$u['id']])['c'] ?? 0);
$stat_total_q = (int)(db()->fetch("SELECT COALESCE(SUM(total_questions),0) c FROM test_attempts WHERE user_id=? AND status='completed'", [$u['id']])['c'] ?? 0);
$stat_percent = $stat_total_q ? round($stat_correct / $stat_total_q * 100, 1) : 0;

$today_tests = (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts WHERE user_id=? AND DATE(started_at)=CURDATE()", [$u['id']])['c'] ?? 0);

$rank_row = db()->fetch(
    "SELECT COUNT(*)+1 c FROM (
        SELECT user_id FROM test_attempts WHERE status='completed'
        GROUP BY user_id
        HAVING AVG(score_percent) > (SELECT COALESCE(AVG(score_percent),0) FROM test_attempts WHERE user_id=? AND status='completed')
    ) t", [$u['id']]);
$stat_rank = (int)($rank_row['c'] ?? 0);

$streak = 0;
for ($i = 0; $i < 30; $i++) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $c = (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts WHERE user_id=? AND DATE(started_at)=?", [$u['id'], $d])['c'] ?? 0);
    if ($c > 0) $streak++; elseif ($i > 0) break;
}

$current_tariff = $u['tariff_id'] ? db()->fetch("SELECT * FROM tariffs WHERE id=?", [$u['tariff_id']]) : null;
$days_left = 0; $tariff_status = 'free';
if ($current_tariff && $u['tariff_expires_at']) {
    $days_left = max(0, ceil((strtotime($u['tariff_expires_at']) - time()) / 86400));
    $tariff_status = $days_left > 7 ? 'active' : ($days_left > 0 ? 'expiring' : 'expired');
}

$weekly = []; $max_count = 0;
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $c = (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts WHERE user_id=? AND DATE(started_at)=?", [$u['id'], $d])['c'] ?? 0);
    $weekly[] = ['d' => date('D', strtotime($d)), 'c' => $c];
    if ($c > $max_count) $max_count = $c;
}

$recent = db()->fetchAll(
    "SELECT a.*, t.title_$lang_field title FROM test_attempts a LEFT JOIN tickets t ON a.ticket_id=t.id
     WHERE a.user_id=? AND a.status='completed' ORDER BY a.started_at DESC LIMIT 5", [$u['id']]);

$pending_payment = db()->fetch(
    "SELECT p.*, t.name_$lang_field tname FROM payments p LEFT JOIN tariffs t ON p.tariff_id=t.id
     WHERE p.user_id=? AND p.status='pending' ORDER BY p.created_at DESC LIMIT 1", [$u['id']]);

$site_name = setting('site_name', SITE_NAME);
?><!DOCTYPE html>
<html lang="<?= lang() === 'uz_cyrillic' ? 'uz-Cyrl' : 'uz' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#3B82F6">
<title><?= e(t('dashboard')) ?> — <?= e($site_name) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(setting('site_logo', '/assets/images/logo.svg')) ?>">
<style>
<?= base_css() ?>
<?= panel_css() ?>

/* === USER/INDEX.PHP — dashboard custom === */
.welcome-card{
  position:relative;background:linear-gradient(135deg,#3B82F6 0%,#2563EB 50%,#1E40AF 100%);
  border-radius:24px;padding:30px 26px;color:#fff;overflow:hidden;
  box-shadow:0 16px 40px rgba(59,130,246,.25);margin-bottom:18px;
}
.welcome-card::before{content:'';position:absolute;top:-30%;right:-10%;width:300px;height:300px;background:radial-gradient(circle,rgba(255,255,255,.18),transparent 70%);border-radius:50%;animation:floatY 8s ease-in-out infinite}
@keyframes floatY{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}
.welcome-content{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;gap:24px;flex-wrap:wrap}
.welcome-text .eyebrow-d{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;opacity:.85}
.welcome-text h1{color:#fff;font-size:28px;margin:6px 0 8px;line-height:1.1;font-weight:800}
.welcome-text p{opacity:.92;font-size:14.5px;margin-bottom:16px;line-height:1.5}
.welcome-actions{display:flex;gap:10px;flex-wrap:wrap}
.btn-white{background:#fff;color:var(--primary);font-weight:700;padding:11px 20px;border-radius:10px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-size:13px;box-shadow:0 4px 12px rgba(0,0,0,.1);transition:all .2s}
.btn-white:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(0,0,0,.15);color:var(--primary)}
.btn-glass{background:rgba(255,255,255,.18);color:#fff;backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.3);padding:11px 20px;border-radius:10px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;transition:all .2s}
.btn-glass:hover{background:rgba(255,255,255,.28);color:#fff}
.welcome-avatar{position:relative;width:88px;height:88px;border-radius:50%;flex-shrink:0;border:3px solid rgba(255,255,255,.3);overflow:visible;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center}
.welcome-avatar img{width:100%;height:100%;border-radius:50%;object-fit:cover}
.welcome-avatar-letter{font-size:36px;font-weight:900;color:#fff}
.streak-badge{position:absolute;bottom:-4px;right:-4px;background:linear-gradient(135deg,#F59E0B,#D97706);color:#fff;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:800;display:inline-flex;align-items:center;gap:3px;border:2px solid #fff;box-shadow:0 4px 12px rgba(245,158,11,.4)}

/* Metric cards */
.metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(180px,100%),1fr));gap:12px;margin-bottom:18px}
.metric-card{background:#fff;border:1px solid #EEF1F5;border-radius:14px;padding:16px;transition:all .2s}
.metric-card:hover{transform:translateY(-2px);box-shadow:0 8px 16px rgba(15,23,42,.06)}
.metric-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.metric-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center}
.metric-icon.blue{background:#EFF6FF;color:#2563EB}
.metric-icon.green{background:#D1FAE5;color:#065F46}
.metric-icon.amber{background:#FEF3C7;color:#92400E}
.metric-icon.pink{background:#FCE7F3;color:#9F1239}
.metric-value{font-size:22px;font-weight:800;line-height:1.05;color:var(--text);font-variant-numeric:tabular-nums}
.metric-label{font-size:11.5px;color:var(--text-soft);margin-top:3px;font-weight:500}

.tariff-card-status{border-radius:14px;padding:16px 20px;margin-bottom:18px;background:#fff;border:1px solid #EEF1F5;border-left:4px solid var(--success);display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}
.tariff-card-status.expiring{border-left-color:var(--warning);background:linear-gradient(90deg,rgba(245,158,11,.05),transparent 30%)}
.tariff-card-status.expired{border-left-color:var(--danger);background:linear-gradient(90deg,rgba(239,68,68,.05),transparent 30%)}
.tariff-card-status.free-banner{background:linear-gradient(135deg,#FEF3C7,#FCD34D);border:none}
.tariff-icon-big{width:48px;height:48px;background:var(--primary-light);color:var(--primary);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}

/* Charts grid */
.dash-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px}
@media (max-width:880px){.dash-grid{grid-template-columns:1fr}}
.dash-card{background:#fff;border:1px solid #EEF1F5;border-radius:14px;padding:18px}
.dash-card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
.dash-card-title{font-size:13.5px;font-weight:700;display:flex;align-items:center;gap:8px}
.weekly-chart{display:flex;align-items:flex-end;gap:8px;height:140px;padding:10px 0}
.chart-bar{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%;position:relative}
.chart-fill{width:100%;max-width:32px;background:linear-gradient(180deg,#3B82F6,#93C5FD);border-radius:6px 6px 0 0;height:0;animation:chartGrow 1s ease forwards;flex:1;min-height:4px}
@keyframes chartGrow{from{height:0}to{height:var(--h)}}
.chart-label{font-size:10px;color:var(--text-soft);font-weight:600}
.chart-tooltip{position:absolute;top:-24px;background:var(--text);color:#fff;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:600;opacity:0;transition:opacity .2s;white-space:nowrap;pointer-events:none}
.chart-bar:hover .chart-tooltip{opacity:1}

.achievements-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.achievement{padding:12px 6px;text-align:center;border-radius:10px;background:var(--bg-soft);position:relative}
.achievement.unlocked{background:linear-gradient(135deg,rgba(16,185,129,.1),rgba(16,185,129,.05));border:1px solid rgba(16,185,129,.3)}
.achievement.locked{opacity:.4;filter:grayscale(.6)}
.achievement-icon{font-size:24px;margin-bottom:3px;line-height:1}
.achievement-name{font-size:10.5px;font-weight:600;color:var(--text-soft);line-height:1.3}
.achievement.unlocked .achievement-name{color:#065F46}

.recent-list{display:flex;flex-direction:column;gap:5px}
.recent-item{display:flex;align-items:center;gap:12px;padding:10px;border-radius:10px;text-decoration:none;color:inherit;transition:all .15s}
.recent-item:hover{background:var(--bg-soft);transform:translateX(3px)}
.recent-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.recent-icon.success{background:#D1FAE5}
.recent-icon.warning{background:#FEF3C7}
.recent-icon.danger{background:#FEE2E2}
.recent-body{flex:1;min-width:0}
.recent-title{font-weight:600;font-size:13.5px}
.recent-meta{font-size:11.5px;color:var(--text-soft);margin-top:2px}

@media (max-width:520px){
  .welcome-card{padding:22px 18px}
  .welcome-text h1{font-size:22px}
  .welcome-avatar{width:64px;height:64px}
  .welcome-avatar-letter{font-size:26px}
  .achievements-grid{grid-template-columns:repeat(2,1fr)}
}
</style>
</head>
<body>

<div class="layout">
<?= panel_sidebar('user', 'dashboard') ?>
<main class="main">

  <!-- Welcome card -->
  <div class="welcome-card">
    <div class="welcome-content">
      <div class="welcome-text">
        <div class="eyebrow-d"><?= date('l, d F Y') ?></div>
        <h1><?= lang()==='uz_cyrillic' ? 'Хуш келибсиз' : 'Xush kelibsiz' ?>, <?= e($u['first_name']) ?>!</h1>
        <p>
          <?= $today_tests > 0
              ? (lang()==='uz_cyrillic' ? "Бугун $today_tests та тест ечдингиз — давом этинг! 🚀" : "Bugun $today_tests ta test yechdingiz — davom eting! 🚀")
              : (lang()==='uz_cyrillic' ? "Бугун ҳали тест ечмадингиз. Бошлаш вақти келди! 🎯" : "Bugun hali test yechmadingiz. Boshlash vaqti keldi! 🎯") ?>
        </p>
        <div class="welcome-actions">
          <a href="/user/testlar.php" class="btn-white"><?= icon('play', 14) ?> <?= t('start_test') ?></a>
          <?php if (!$current_tariff || $days_left < 7): ?>
            <a href="/user/tariflar.php" class="btn-glass"><?= icon('gem', 14) ?> <?= t('tariffs') ?></a>
          <?php endif; ?>
        </div>
      </div>
      <div class="welcome-avatar">
        <?php if (!empty($u['avatar'])): ?>
          <img src="<?= e($u['avatar']) ?>" alt="">
        <?php else: ?>
          <div class="welcome-avatar-letter"><?= mb_substr($u['first_name'],0,1) ?></div>
        <?php endif; ?>
        <?php if ($streak >= 3): ?>
          <span class="streak-badge">🔥 <?= $streak ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Pending payment alert -->
  <?php if ($pending_payment): ?>
  <div class="alert alert-warning" style="display:flex;align-items:center;gap:14px;margin-bottom:18px">
    <?= icon('clock', 22) ?>
    <div style="flex:1">
      <strong><?= lang()==='uz_cyrillic' ? "Тўловингиз кўриб чиқилмоқда" : "To'lovingiz ko'rib chiqilmoqda" ?></strong>
      <div style="font-size:12.5px"><?= e($pending_payment['tname']) ?> · <?= money($pending_payment['amount']) ?> · <?= date('d.m.Y H:i', strtotime($pending_payment['created_at'])) ?></div>
    </div>
    <a href="/user/tariflar.php" class="btn btn-light btn-sm"><?= icon('arrow-right', 14) ?></a>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="metric-grid">
    <div class="metric-card">
      <div class="metric-head"><div class="metric-icon blue"><?= icon('document', 18) ?></div></div>
      <div class="metric-value"><?= $stat_total ?></div>
      <div class="metric-label"><?= t('total_tests') ?></div>
    </div>
    <div class="metric-card">
      <div class="metric-head"><div class="metric-icon green"><?= icon('check-circle', 18) ?></div></div>
      <div class="metric-value"><?= $stat_correct ?></div>
      <div class="metric-label"><?= t('correct_answers') ?></div>
    </div>
    <div class="metric-card">
      <div class="metric-head"><div class="metric-icon amber"><?= icon('chart', 18) ?></div></div>
      <div class="metric-value"><?= $stat_percent ?>%</div>
      <div class="metric-label"><?= t('success_rate') ?></div>
    </div>
    <div class="metric-card">
      <div class="metric-head"><div class="metric-icon pink"><?= icon('trophy', 18) ?></div></div>
      <div class="metric-value">#<?= $stat_rank ?: '—' ?></div>
      <div class="metric-label"><?= t('rating') ?></div>
    </div>
  </div>

  <!-- Tariff card -->
  <?php if ($current_tariff): ?>
  <div class="tariff-card-status <?= $tariff_status ?>">
    <div style="display:flex;align-items:center;gap:14px;flex:1;min-width:0">
      <div class="tariff-icon-big"><?= icon('gem', 22) ?></div>
      <div>
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-soft);font-weight:600"><?= t('current_tariff') ?></div>
        <div style="font-size:18px;font-weight:800"><?= e($current_tariff['name_'.$lang_field]) ?></div>
        <?php if ($u['tariff_expires_at']): ?>
          <div style="font-size:12.5px;color:var(--text-soft)"><?= t('expires_at') ?>: <strong><?= date('d.m.Y', strtotime($u['tariff_expires_at'])) ?></strong> · <span style="color:var(--<?= $days_left > 7 ? 'success-dark' : ($days_left > 0 ? 'warning-dark' : 'danger-dark') ?>)"><?= $days_left ?> <?= lang()==='uz_cyrillic' ? "кун қолди" : "kun qoldi" ?></span></div>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($days_left <= 7): ?><a href="/user/tariflar.php" class="btn btn-primary btn-sm"><?= icon('refresh', 14) ?> <?= lang()==='uz_cyrillic' ? "Узайтириш" : "Uzaytirish" ?></a><?php endif; ?>
  </div>
  <?php else: ?>
  <div class="tariff-card-status free-banner">
    <div style="display:flex;align-items:center;gap:14px;flex:1">
      <div style="width:44px;height:44px;background:rgba(255,255,255,.4);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#92400E"><?= icon('gift', 22) ?></div>
      <div>
        <strong style="color:#92400E"><?= lang()==='uz_cyrillic' ? "Бепул режимдасиз" : "Bepul rejimdasiz" ?></strong>
        <div style="font-size:12.5px;color:#78350F"><?= lang()==='uz_cyrillic' ? "Юқори тарифга ўтиб барча имкониятлардан фойдаланинг" : "Yuqori tarifga o'tib barcha imkoniyatlardan foydalaning" ?></div>
      </div>
    </div>
    <a href="/user/tariflar.php" class="btn btn-primary btn-sm"><?= t('choose_plan') ?> →</a>
  </div>
  <?php endif; ?>

  <!-- Charts grid -->
  <div class="dash-grid">

    <div class="dash-card">
      <div class="dash-card-head">
        <div class="dash-card-title"><?= icon('chart', 16) ?> <?= t('weekly_progress') ?></div>
        <span class="badge badge-info"><?= array_sum(array_column($weekly,'c')) ?> ta</span>
      </div>
      <div class="weekly-chart">
        <?php foreach ($weekly as $w):
          $h = $max_count ? max(8, ($w['c'] / $max_count) * 100) : 8;
        ?>
        <div class="chart-bar">
          <div class="chart-tooltip"><?= $w['c'] ?> ta</div>
          <div class="chart-fill" style="--h:<?= $h ?>%"></div>
          <div class="chart-label"><?= mb_substr($w['d'], 0, 2) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="dash-card">
      <div class="dash-card-head">
        <div class="dash-card-title"><?= icon('trophy', 16) ?> <?= lang()==='uz_cyrillic' ? "Ютуқлар" : "Yutuqlar" ?></div>
      </div>
      <div class="achievements-grid">
        <?php
        $achievements = [
            ['🎯', $stat_total >= 1,  lang()==='uz_cyrillic' ? "Биринчи қадам" : "Birinchi qadam"],
            ['🔟', $stat_total >= 10, lang()==='uz_cyrillic' ? "10 та тест" : "10 ta test"],
            ['💯', $stat_percent >= 90, lang()==='uz_cyrillic' ? "Аъло (90%+)" : "A'lo (90%+)"],
            ['🔥', $streak >= 3,      lang()==='uz_cyrillic' ? "$streak кун" : "$streak kun"],
            ['⭐', $stat_rank > 0 && $stat_rank <= 10, "Top 10"],
            ['🏆', $stat_total >= 50, lang()==='uz_cyrillic' ? "50 та тест" : "50 ta test"],
        ];
        foreach ($achievements as $a): ?>
          <div class="achievement <?= $a[1] ? 'unlocked' : 'locked' ?>">
            <div class="achievement-icon"><?= $a[0] ?></div>
            <div class="achievement-name"><?= e($a[2]) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

  <!-- Recent tests -->
  <div class="dash-card">
    <div class="dash-card-head">
      <div class="dash-card-title"><?= icon('clock', 16) ?> <?= t('recent_tests') ?></div>
      <a href="/user/natijalar.php" style="font-size:13px;font-weight:600;color:var(--primary);text-decoration:none"><?= t('view_all') ?> →</a>
    </div>
    <?php if (empty($recent)): ?>
      <div style="padding:24px;text-align:center;color:var(--text-soft)">
        <?= icon('document', 36) ?>
        <p style="margin:10px 0;font-size:14px"><?= t('no_tests') ?></p>
        <a href="/user/testlar.php" class="btn btn-primary btn-sm"><?= t('start_test') ?> →</a>
      </div>
    <?php else: ?>
    <div class="recent-list">
      <?php foreach ($recent as $r):
        $p = (float)$r['score_percent'];
        $cls = $p>=80?'success':($p>=50?'warning':'danger');
        $emoji = $p>=80?'🏆':($p>=50?'👍':'📚');
      ?>
      <a href="/user/test-result.php?attempt=<?= $r['id'] ?>" class="recent-item">
        <div class="recent-icon <?= $cls ?>"><?= $emoji ?></div>
        <div class="recent-body">
          <div class="recent-title"><?= e($r['title'] ?? 'Test') ?></div>
          <div class="recent-meta"><?= date('d.m.Y H:i', strtotime($r['finished_at'] ?? $r['started_at'])) ?> · <?= $r['correct_answers'] ?>/<?= $r['total_questions'] ?></div>
        </div>
        <span class="badge badge-<?= $cls ?>"><?= $p ?>%</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

</main>
</div>

<script>
<?= panel_js() ?>
</script>
</body></html>
