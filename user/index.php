<?php
require_once __DIR__ . '/../includes/auth.php';
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

// Bugungi
$today_tests = (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts WHERE user_id=? AND DATE(started_at)=CURDATE()", [$u['id']])['c'] ?? 0);

// Reyting o'rni
$rank_row = db()->fetch(
    "SELECT COUNT(*)+1 c FROM (
        SELECT user_id FROM test_attempts WHERE status='completed'
        GROUP BY user_id
        HAVING AVG(score_percent) > (SELECT COALESCE(AVG(score_percent),0) FROM test_attempts WHERE user_id=? AND status='completed')
    ) t", [$u['id']]);
$stat_rank = (int)($rank_row['c'] ?? 0);

// Streak (ketma-ket faol kunlar)
$streak = 0;
for ($i = 0; $i < 30; $i++) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $c = (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts WHERE user_id=? AND DATE(started_at)=?", [$u['id'], $d])['c'] ?? 0);
    if ($c > 0) $streak++;
    else if ($i > 0) break;
}

// Joriy tarif
$current_tariff = $u['tariff_id']
    ? db()->fetch("SELECT * FROM tariffs WHERE id=?", [$u['tariff_id']])
    : null;
$days_left = 0;
$tariff_status = 'free';
if ($current_tariff && $u['tariff_expires_at']) {
    $days_left = max(0, ceil((strtotime($u['tariff_expires_at']) - time()) / 86400));
    if ($days_left > 7) $tariff_status = 'active';
    elseif ($days_left > 0) $tariff_status = 'expiring';
    else $tariff_status = 'expired';
}

// Haftalik progress
$weekly = [];
$max_count = 0;
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $c = (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts WHERE user_id=? AND DATE(started_at)=?", [$u['id'], $d])['c'] ?? 0);
    $weekly[] = ['d' => date('D', strtotime($d)), 'date' => date('d.m', strtotime($d)), 'c' => $c];
    if ($c > $max_count) $max_count = $c;
}

// So'nggi 5 ta test
$recent = db()->fetchAll(
    "SELECT a.*, t.title_$lang_field title
     FROM test_attempts a LEFT JOIN tickets t ON a.ticket_id=t.id
     WHERE a.user_id=? AND a.status='completed'
     ORDER BY a.started_at DESC LIMIT 5", [$u['id']]);

// Pending to'lov mavjudmi?
$pending_payment = db()->fetch(
    "SELECT p.*, t.name_$lang_field tname FROM payments p
     LEFT JOIN tariffs t ON p.tariff_id=t.id
     WHERE p.user_id=? AND p.status='pending' ORDER BY p.created_at DESC LIMIT 1",
    [$u['id']]);

render_head(t('dashboard'));
?>
<div class="layout">
<?php render_sidebar('user', 'dashboard'); ?>
<main class="main">

  <!-- Welcome card -->
  <div class="welcome-card mb-3">
    <div class="welcome-content">
      <div class="welcome-text">
        <div class="eyebrow" style="opacity:.85;color:rgba(255,255,255,.85)">
          <?= date('l, d F Y') ?>
        </div>
        <h1 style="color:#fff;font-size:30px;margin:6px 0 8px;line-height:1.1">
          <?= lang()==='uz_cyrillic' ? 'Хуш келибсиз' : 'Xush kelibsiz' ?>, <?= e($u['first_name']) ?>!
        </h1>
        <p style="opacity:.92;font-size:15px;margin-bottom:18px">
          <?= $today_tests > 0
              ? (lang()==='uz_cyrillic' ? "Бугун $today_tests та тест ечдингиз — давом этинг! 🚀" : "Bugun $today_tests ta test yechdingiz — davom eting! 🚀")
              : (lang()==='uz_cyrillic' ? "Бугун ҳали тест ечмадингиз. Бошлаш вақти келди! 🎯" : "Bugun hali test yechmadingiz. Boshlash vaqti keldi! 🎯") ?>
        </p>
        <div class="flex gap-2 flex-wrap">
          <a href="/user/testlar.php" class="btn btn-lg" style="background:#fff;color:var(--primary)">
            <?= icon('play', 18) ?> <?= t('start_test') ?>
          </a>
          <?php if (!$current_tariff || $days_left < 7): ?>
          <a href="/user/tariflar.php" class="btn btn-lg" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);backdrop-filter:blur(10px)">
            <?= icon('gem', 18) ?> <?= t('tariffs') ?>
          </a>
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
          <span class="streak-badge"><?= icon('flame', 14) ?> <?= $streak ?></span>
        <?php endif; ?>
      </div>
    </div>
    <!-- Decorative pattern -->
    <div class="welcome-pattern"></div>
  </div>

  <!-- Pending payment alert -->
  <?php if ($pending_payment): ?>
  <div class="alert alert-warning mb-3" style="display:flex;align-items:center;gap:14px">
    <?= icon('clock', 24) ?>
    <div style="flex:1">
      <strong><?= lang()==='uz_cyrillic' ? "Тўловингиз кўриб чиқилмоқда" : "To'lovingiz ko'rib chiqilmoqda" ?></strong>
      <div style="font-size:13px">
        <?= e($pending_payment['tname']) ?> — <?= money($pending_payment['amount']) ?> <?= t('soum') ?>
        · <?= date('d.m.Y H:i', strtotime($pending_payment['created_at'])) ?>
      </div>
    </div>
    <a href="/user/tariflar.php" class="btn btn-light btn-sm"><?= icon('arrow-right', 14) ?></a>
  </div>
  <?php endif; ?>

  <!-- Stats grid -->
  <div class="grid-4 mb-3 stagger">
    <div class="stat-card lift">
      <div class="stat-icon"><?= icon('document', 22) ?></div>
      <div class="value"><?= $stat_total ?></div>
      <div class="label"><?= t('total_tests') ?></div>
    </div>
    <div class="stat-card lift">
      <div class="stat-icon success"><?= icon('check-circle', 22) ?></div>
      <div class="value"><?= $stat_correct ?></div>
      <div class="label"><?= t('correct_answers') ?></div>
    </div>
    <div class="stat-card lift">
      <div class="stat-icon warning"><?= icon('chart', 22) ?></div>
      <div class="value gradient-text"><?= $stat_percent ?>%</div>
      <div class="label"><?= t('success_rate') ?></div>
      <div class="progress mt-1" style="height:4px"><div class="progress-bar" style="width:<?= min(100,$stat_percent) ?>%"></div></div>
    </div>
    <div class="stat-card lift">
      <div class="stat-icon" style="background:#FCE7F3;color:#9F1239"><?= icon('trophy', 22) ?></div>
      <div class="value">#<?= $stat_rank ?></div>
      <div class="label"><?= t('rating') ?></div>
    </div>
  </div>

  <!-- Tariff status card -->
  <?php if ($current_tariff): ?>
  <div class="card mb-3 tariff-card-status <?= $tariff_status ?>">
    <div class="flex justify-between items-center flex-wrap gap-3">
      <div class="flex items-center gap-3">
        <div class="tariff-icon-big"><?= icon('gem', 28) ?></div>
        <div>
          <div class="text-soft" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;font-weight:600">
            <?= t('current_tariff') ?>
          </div>
          <div style="font-size:20px;font-weight:800;margin-top:2px"><?= e($current_tariff['name_'.$lang_field]) ?></div>
          <?php if ($u['tariff_expires_at']): ?>
            <div class="text-soft" style="font-size:13px">
              <?= t('expires_at') ?>: <strong><?= date('d.m.Y', strtotime($u['tariff_expires_at'])) ?></strong>
              · <span style="color:var(--<?= $days_left > 7 ? 'success-dark' : ($days_left > 0 ? 'warning-dark' : 'danger-dark') ?>)">
                <?= $days_left ?> <?= t('days') ?> qoldi
              </span>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($days_left <= 7): ?>
        <a href="/user/tariflar.php" class="btn btn-primary"><?= icon('refresh', 16) ?> Uzaytirish</a>
      <?php endif; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="card mb-3" style="background:linear-gradient(135deg,#FEF3C7,#FCD34D);border:none">
    <div class="flex justify-between items-center flex-wrap gap-3">
      <div class="flex items-center gap-3">
        <div style="width:48px;height:48px;background:rgba(255,255,255,.3);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#92400E">
          <?= icon('gift', 24) ?>
        </div>
        <div>
          <strong style="color:#92400E"><?= lang()==='uz_cyrillic' ? "Бепул режимдасиз" : "Bepul rejimdasiz" ?></strong>
          <div style="font-size:13px;color:#78350F"><?= lang()==='uz_cyrillic' ? "Юқори тарифга ўтиб барча имкониятлардан фойдаланинг" : "Yuqori tarifga o'tib barcha imkoniyatlardan foydalaning" ?></div>
        </div>
      </div>
      <a href="/user/tariflar.php" class="btn btn-primary"><?= t('choose_plan') ?> →</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Charts grid -->
  <div class="grid-2 mb-3">
    <!-- Haftalik progress -->
    <div class="card">
      <div class="flex justify-between items-center mb-3">
        <h3 style="font-size:16px;font-weight:700;margin:0;display:flex;align-items:center;gap:10px">
          <?= icon('chart', 20) ?> <?= t('weekly_progress') ?>
        </h3>
        <span class="badge badge-info"><?= array_sum(array_column($weekly,'c')) ?> ta</span>
      </div>
      <div class="weekly-chart">
        <?php foreach ($weekly as $i => $w):
          $h = $max_count ? max(8, ($w['c'] / $max_count) * 100) : 8;
        ?>
        <div class="chart-bar" style="--h:<?= $h ?>%;animation-delay:<?= $i * .08 ?>s">
          <div class="chart-tooltip"><?= $w['c'] ?> ta</div>
          <div class="chart-fill"></div>
          <div class="chart-label"><?= mb_substr($w['d'], 0, 2) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Achievements -->
    <div class="card">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:10px">
        <?= icon('trophy', 20) ?> <?= lang()==='uz_cyrillic' ? "Ютуқлар" : "Yutuqlar" ?>
      </h3>
      <div class="achievements-grid">
        <?php
        $achievements = [
            ['🎯', $stat_total >= 1,  lang()==='uz_cyrillic' ? "Биринчи қадам" : "Birinchi qadam"],
            ['🔟', $stat_total >= 10, lang()==='uz_cyrillic' ? "10 та тест" : "10 ta test"],
            ['💯', $stat_percent >= 90, lang()==='uz_cyrillic' ? "Аъло (90%+)" : "A'lo (90%+)"],
            ['🔥', $streak >= 3,      lang()==='uz_cyrillic' ? "$streak кун streak" : "$streak kun streak"],
            ['⭐', $stat_rank <= 10,  lang()==='uz_cyrillic' ? "Топ 10" : "Top 10"],
            ['🏆', $stat_total >= 50, lang()==='uz_cyrillic' ? "50 та тест" : "50 ta test"],
        ];
        foreach ($achievements as $a):
        ?>
        <div class="achievement <?= $a[1] ? 'unlocked' : 'locked' ?>">
          <div class="achievement-icon"><?= $a[0] ?></div>
          <div class="achievement-name"><?= e($a[2]) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Recent tests -->
  <div class="card">
    <div class="flex justify-between items-center mb-3">
      <h3 style="font-size:16px;font-weight:700;margin:0;display:flex;align-items:center;gap:10px">
        <?= icon('clock', 20) ?> <?= t('recent_tests') ?>
      </h3>
      <a href="/user/natijalar.php" class="text-primary" style="font-size:13px;font-weight:600">
        <?= t('view_all') ?> →
      </a>
    </div>
    <?php if (empty($recent)): ?>
      <div class="empty-state" style="padding:40px 20px">
        <?= icon('document', 48) ?>
        <h3 class="mt-2" style="font-size:16px"><?= t('no_tests') ?></h3>
        <p style="font-size:13px"><?= t('first_test') ?></p>
        <a href="/user/testlar.php" class="btn btn-primary mt-2"><?= t('start_test') ?> →</a>
      </div>
    <?php else: ?>
    <div class="recent-list">
      <?php foreach ($recent as $r):
        $p = (float)$r['score_percent'];
        $cls = $p>=80?'success':($p>=50?'warning':'danger');
      ?>
      <a href="/user/test-result.php?attempt=<?= $r['id'] ?>" class="recent-item">
        <div class="recent-icon <?= $cls ?>">
          <?php if ($p >= 80): ?>🏆<?php elseif ($p >= 50): ?>👍<?php else: ?>📚<?php endif; ?>
        </div>
        <div class="recent-body">
          <div class="recent-title"><?= e($r['title'] ?? 'Test') ?></div>
          <div class="recent-meta">
            <?= date('d.m.Y H:i', strtotime($r['finished_at'] ?? $r['started_at'])) ?>
            · <?= $r['correct_answers'] ?>/<?= $r['total_questions'] ?>
          </div>
        </div>
        <div class="recent-score"><span class="badge badge-<?= $cls ?>"><?= $p ?>%</span></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</main>
</div>

<style>
/* Welcome card */
.welcome-card{position:relative;background:linear-gradient(135deg,#3B82F6 0%,#2563EB 50%,#1E40AF 100%);
  border-radius:var(--r-2xl);padding:32px 28px;color:#fff;overflow:hidden;
  box-shadow:0 16px 40px rgba(59,130,246,.25)}
.welcome-content{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;gap:24px;flex-wrap:wrap}
.welcome-pattern{position:absolute;inset:0;background:
  radial-gradient(circle at top right, rgba(255,255,255,.2), transparent 50%),
  radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 50%);
  z-index:0}
.welcome-pattern::after{content:'';position:absolute;top:-30%;right:-10%;width:300px;height:300px;
  background:radial-gradient(circle, rgba(255,255,255,.1), transparent 70%);
  border-radius:50%;animation:floatY 8s ease-in-out infinite}
.welcome-avatar{position:relative;width:96px;height:96px;border-radius:50%;flex-shrink:0;
  border:4px solid rgba(255,255,255,.3);overflow:visible}
.welcome-avatar img{width:100%;height:100%;border-radius:50%;object-fit:cover}
.welcome-avatar-letter{width:100%;height:100%;border-radius:50%;background:rgba(255,255,255,.2);
  display:flex;align-items:center;justify-content:center;font-size:42px;font-weight:900;color:#fff;
  backdrop-filter:blur(10px)}
.streak-badge{position:absolute;bottom:-6px;right:-6px;background:linear-gradient(135deg,#F59E0B,#D97706);
  color:#fff;padding:4px 10px;border-radius:14px;font-size:13px;font-weight:800;
  display:inline-flex;align-items:center;gap:4px;border:2px solid #fff;
  box-shadow:0 4px 12px rgba(245,158,11,.4);animation:bounce 2s ease-in-out infinite}

/* Tariff status card */
.tariff-card-status{border-left:4px solid var(--success)}
.tariff-card-status.expiring{border-left-color:var(--warning);background:linear-gradient(90deg, rgba(245,158,11,.05), transparent 30%)}
.tariff-card-status.expired{border-left-color:var(--danger);background:linear-gradient(90deg, rgba(239,68,68,.05), transparent 30%)}
.tariff-icon-big{width:56px;height:56px;background:var(--primary-light);color:var(--primary);
  border-radius:var(--r-lg);display:flex;align-items:center;justify-content:center;flex-shrink:0}

/* Weekly chart */
.weekly-chart{display:flex;align-items:flex-end;gap:10px;height:160px;padding:10px 0}
.chart-bar{flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;height:100%;position:relative}
.chart-fill{width:100%;background:linear-gradient(180deg,var(--primary) 0%,var(--primary-300) 100%);
  border-radius:8px 8px 0 0;transition:height 1.2s var(--ease-soft);
  height:0;animation:chartGrow 1.2s var(--ease-soft) forwards;flex:1;min-height:8px}
@keyframes chartGrow{from{height:0}to{height:var(--h)}}
.chart-label{font-size:11px;color:var(--text-soft);font-weight:600}
.chart-tooltip{position:absolute;top:-28px;background:var(--text);color:#fff;padding:3px 8px;
  border-radius:6px;font-size:11px;font-weight:600;opacity:0;transition:opacity .2s;pointer-events:none;white-space:nowrap;z-index:1}
.chart-bar:hover .chart-tooltip{opacity:1}
.chart-bar:hover .chart-fill{filter:brightness(1.1)}

/* Achievements */
.achievements-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.achievement{padding:12px 8px;text-align:center;border-radius:var(--r-md);
  background:var(--bg-soft);transition:all .25s var(--ease-soft);position:relative}
.achievement.unlocked{background:linear-gradient(135deg, rgba(16,185,129,.1), rgba(16,185,129,.05));
  border:1px solid rgba(16,185,129,.3)}
.achievement.locked{opacity:.4;filter:grayscale(.5)}
.achievement.unlocked:hover{transform:translateY(-2px);box-shadow:var(--shadow-sm)}
.achievement-icon{font-size:28px;margin-bottom:4px;line-height:1}
.achievement-name{font-size:11px;font-weight:600;color:var(--text-soft);line-height:1.3}
.achievement.unlocked .achievement-name{color:var(--success-dark)}

/* Recent list */
.recent-list{display:flex;flex-direction:column;gap:6px}
.recent-item{display:flex;align-items:center;gap:14px;padding:12px;border-radius:var(--r-md);
  text-decoration:none;color:inherit;transition:all .2s ease}
.recent-item:hover{background:var(--bg-soft);transform:translateX(4px)}
.recent-icon{width:42px;height:42px;border-radius:var(--r-md);display:flex;align-items:center;
  justify-content:center;font-size:20px;flex-shrink:0}
.recent-icon.success{background:var(--success-light)}
.recent-icon.warning{background:var(--warning-light)}
.recent-icon.danger{background:var(--danger-light)}
.recent-body{flex:1;min-width:0}
.recent-title{font-weight:600;font-size:14px}
.recent-meta{font-size:12px;color:var(--text-soft);margin-top:2px}
.recent-score{flex-shrink:0}

@media(max-width:520px){
  .welcome-card{padding:24px 20px}
  .welcome-content h1{font-size:24px}
  .welcome-avatar{width:72px;height:72px}
  .welcome-avatar-letter{font-size:32px}
  .achievements-grid{grid-template-columns:repeat(2,1fr)}
  .weekly-chart{height:120px;gap:6px}
  .recent-meta{font-size:11px}
}
</style>
</body></html>
