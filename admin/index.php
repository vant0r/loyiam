<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

// ================== Statistikalar ==================
$total_users     = (int)(db()->fetch("SELECT COUNT(*) c FROM users WHERE role='user'")['c'] ?? 0);
$active_users    = (int)(db()->fetch("SELECT COUNT(*) c FROM users WHERE role='user' AND status='active'")['c'] ?? 0);
$total_tests     = (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts")['c'] ?? 0);
$total_questions = (int)(db()->fetch("SELECT COUNT(*) c FROM questions")['c'] ?? 0);
$total_payments  = (float)(db()->fetch("SELECT COALESCE(SUM(amount),0) c FROM payments WHERE status='approved'")['c'] ?? 0);
$pending_pays    = (int)(db()->fetch("SELECT COUNT(*) c FROM payments WHERE status='pending'")['c'] ?? 0);

// Trend (joriy hafta vs oldingi hafta)
$users_this_week = (int)(db()->fetch("SELECT COUNT(*) c FROM users WHERE role='user' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['c'] ?? 0);
$users_prev_week = (int)(db()->fetch("SELECT COUNT(*) c FROM users WHERE role='user' AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")['c'] ?? 0);
$users_trend = $users_prev_week ? round(($users_this_week - $users_prev_week) / $users_prev_week * 100, 1) : ($users_this_week ? 100 : 0);

$tests_this_week = (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts WHERE started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['c'] ?? 0);
$tests_prev_week = (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts WHERE started_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND started_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")['c'] ?? 0);
$tests_trend = $tests_prev_week ? round(($tests_this_week - $tests_prev_week) / $tests_prev_week * 100, 1) : 0;

$pay_this_week = (float)(db()->fetch("SELECT COALESCE(SUM(amount),0) c FROM payments WHERE status='approved' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['c'] ?? 0);
$pay_prev_week = (float)(db()->fetch("SELECT COALESCE(SUM(amount),0) c FROM payments WHERE status='approved' AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")['c'] ?? 0);
$pay_trend = $pay_prev_week ? round(($pay_this_week - $pay_prev_week) / $pay_prev_week * 100, 1) : 0;

// Foydalanuvchilar (oxirgi 14 kun) — sparkline uchun
$users_14d = [];
for ($i=13; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $c = (int)(db()->fetch("SELECT COUNT(*) c FROM users WHERE DATE(created_at)=?", [$d])['c'] ?? 0);
    $users_14d[] = ['d' => date('d.m', strtotime($d)), 'c' => $c];
}
$users_chart = array_slice($users_14d, -7);

// To'lovlar (oxirgi 7 kun)
$pay_chart = [];
for ($i=6; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $c = (float)(db()->fetch("SELECT COALESCE(SUM(amount),0) c FROM payments WHERE DATE(created_at)=? AND status='approved'", [$d])['c'] ?? 0);
    $pay_chart[] = ['d' => date('D', strtotime($d)), 'c' => $c];
}

// Tests sparkline (14 kun)
$tests_14d = [];
for ($i=13; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $c = (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts WHERE DATE(started_at)=?", [$d])['c'] ?? 0);
    $tests_14d[] = $c;
}

$pay_14d = [];
for ($i=13; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $c = (float)(db()->fetch("SELECT COALESCE(SUM(amount),0) c FROM payments WHERE DATE(created_at)=? AND status='approved'", [$d])['c'] ?? 0);
    $pay_14d[] = $c;
}
$users_14d_only = array_column($users_14d, 'c');

// Sparkline SVG generator
function sparkline_svg(array $values, int $w = 100, int $h = 32): string {
    $n = count($values);
    if ($n < 2) return '<svg class="sparkline" viewBox="0 0 '.$w.' '.$h.'"></svg>';
    $max = max($values) ?: 1;
    $min = min($values);
    $range = ($max - $min) ?: 1;
    $step = $w / ($n - 1);
    $points = [];
    foreach ($values as $i => $v) {
        $x = $i * $step;
        $y = $h - (($v - $min) / $range) * ($h - 4) - 2;
        $points[] = round($x, 1) . ',' . round($y, 1);
    }
    $path = 'M ' . implode(' L ', $points);
    $area = $path . ' L ' . round(($n-1) * $step, 1) . ',' . $h . ' L 0,' . $h . ' Z';
    return '<svg class="sparkline" viewBox="0 0 '.$w.' '.$h.'" preserveAspectRatio="none">'
         . '<path class="sparkline-area" d="'.$area.'"/>'
         . '<path class="sparkline-line" d="'.$path.'"/>'
         . '</svg>';
}

// So'nggi faoliyatlar
$logs = db()->fetchAll("SELECT l.*, u.first_name, u.last_name FROM logs l LEFT JOIN users u ON l.user_id=u.id ORDER BY l.created_at DESC LIMIT 8");
$recent_users = db()->fetchAll("SELECT * FROM users WHERE role='user' ORDER BY created_at DESC LIMIT 6");
$pending_payments = db()->fetchAll(
    "SELECT p.*, u.first_name, u.last_name, t.name_latin tname
     FROM payments p LEFT JOIN users u ON p.user_id=u.id
     LEFT JOIN tariffs t ON p.tariff_id=t.id
     WHERE p.status='pending' ORDER BY p.created_at DESC LIMIT 5");

// Activity icon mapping
function activity_icon(string $action): array {
    $map = [
        'user_added'      => ['user-plus', 'success'],
        'user_deleted'    => ['user-minus', 'danger'],
        'user_blocked'    => ['ban', 'danger'],
        'user_unblocked'  => ['check-circle', 'success'],
        'user_updated'    => ['edit', 'info'],
        'login_success'   => ['login', 'success'],
        'login_failed'    => ['x-circle', 'warning'],
        'payment_approved'=> ['check-circle', 'success'],
        'payment_rejected'=> ['x-circle', 'danger'],
        'payment_status_changed' => ['card', 'info'],
        'test_started'    => ['play', 'info'],
        'contact_form'    => ['mail', 'info'],
    ];
    return $map[$action] ?? ['activity', 'info'];
}

render_head('Admin Dashboard');
?>
<div class="layout">
<?php render_sidebar('admin','dashboard'); ?>
<main class="main">

  <!-- Modern page header -->
  <div class="page-header-modern">
    <div>
      <div class="page-eyebrow">
        <?= icon('chart', 12) ?>
        <?= date('l, d F Y') ?>
      </div>
      <h1>Admin Dashboard</h1>
      <div class="page-subtitle">
        <?= lang()==='uz_cyrillic' ? "Тизимингиз ҳозирда $active_users та фаол фойдаланувчи билан ишламоқда" : "Tizimingiz hozirda $active_users ta faol foydalanuvchi bilan ishlamoqda" ?>
      </div>
    </div>
    <div class="page-toolbar">
      <a href="/admin/sozlamalar.php" class="btn btn-light btn-sm"><?= icon('settings', 14) ?> <?= t('settings') ?></a>
      <a href="/admin/users.php" class="btn btn-primary btn-sm"><?= icon('users', 14) ?> <?= t('users') ?></a>
    </div>
  </div>

  <!-- Metric cards -->
  <div class="metric-grid mb-3">

    <div class="metric-card is-primary">
      <div class="metric-head">
        <div class="metric-icon"><?= icon('users', 18) ?></div>
        <span class="metric-trend <?= $users_trend>0?'up':($users_trend<0?'down':'neutral') ?>">
          <?= $users_trend>0?'↑':($users_trend<0?'↓':'·') ?> <?= abs($users_trend) ?>%
        </span>
      </div>
      <div class="metric-value"><?= number_format($total_users) ?></div>
      <div class="metric-label"><?= t('users') ?> · +<?= $users_this_week ?> <?= lang()==='uz_cyrillic' ? "ҳафта" : "hafta" ?></div>
      <div class="metric-sparkline"><?= sparkline_svg($users_14d_only) ?></div>
    </div>

    <div class="metric-card is-success">
      <div class="metric-head">
        <div class="metric-icon"><?= icon('check-circle', 18) ?></div>
        <span class="metric-trend up"><?= round($total_users ? $active_users/$total_users*100 : 0) ?>%</span>
      </div>
      <div class="metric-value"><?= number_format($active_users) ?></div>
      <div class="metric-label"><?= lang()==='uz_cyrillic' ? "Фаол фойдаланувчилар" : "Faol foydalanuvchilar" ?></div>
    </div>

    <div class="metric-card is-violet">
      <div class="metric-head">
        <div class="metric-icon"><?= icon('document', 18) ?></div>
        <span class="metric-trend <?= $tests_trend>0?'up':($tests_trend<0?'down':'neutral') ?>">
          <?= $tests_trend>0?'↑':($tests_trend<0?'↓':'·') ?> <?= abs($tests_trend) ?>%
        </span>
      </div>
      <div class="metric-value"><?= number_format($total_tests) ?></div>
      <div class="metric-label"><?= lang()==='uz_cyrillic' ? "Жами тест уринишлари" : "Jami test urinishlari" ?></div>
      <div class="metric-sparkline"><?= sparkline_svg($tests_14d) ?></div>
    </div>

    <div class="metric-card is-cyan">
      <div class="metric-head">
        <div class="metric-icon"><?= icon('help', 18) ?></div>
      </div>
      <div class="metric-value"><?= number_format($total_questions) ?></div>
      <div class="metric-label"><?= t('questions') ?></div>
    </div>

    <div class="metric-card is-pink">
      <div class="metric-head">
        <div class="metric-icon"><?= icon('card', 18) ?></div>
        <span class="metric-trend <?= $pay_trend>0?'up':($pay_trend<0?'down':'neutral') ?>">
          <?= $pay_trend>0?'↑':($pay_trend<0?'↓':'·') ?> <?= abs($pay_trend) ?>%
        </span>
      </div>
      <div class="metric-value"><?= money($total_payments) ?></div>
      <div class="metric-label"><?= lang()==='uz_cyrillic' ? "Жами тушум" : "Jami tushum" ?> · <?= t('soum') ?></div>
      <div class="metric-sparkline"><?= sparkline_svg($pay_14d) ?></div>
    </div>

    <div class="metric-card is-warning">
      <div class="metric-head">
        <div class="metric-icon"><?= icon('clock', 18) ?></div>
        <?php if ($pending_pays > 0): ?>
          <a href="/admin/tolovlar.php?status=pending" class="metric-trend" style="background:var(--warning-light);color:var(--warning-dark);text-decoration:none"><?= lang()==='uz_cyrillic' ? "Кўриш" : "Ko'rish" ?> →</a>
        <?php endif; ?>
      </div>
      <div class="metric-value"><?= $pending_pays ?></div>
      <div class="metric-label"><?= lang()==='uz_cyrillic' ? "Кутилаётган тўловлар" : "Kutilayotgan to'lovlar" ?></div>
    </div>

  </div>

  <!-- Charts row -->
  <div class="grid-2 mb-3" style="gap:16px">

    <div class="chart-card">
      <div class="chart-card-head">
        <div class="chart-card-title">
          <?= icon('users', 16) ?>
          <?= lang()==='uz_cyrillic' ? "Янги фойдаланувчилар" : "Yangi foydalanuvchilar" ?>
          <small>· 7 <?= lang()==='uz_cyrillic' ? "кун" : "kun" ?></small>
        </div>
        <span class="badge-soft info">+<?= array_sum(array_column($users_chart,'c')) ?></span>
      </div>
      <?php $maxU = max(array_map(fn($x)=>$x['c'], $users_chart)) ?: 1; ?>
      <div class="chart-bars">
        <div class="chart-yaxis">
          <span><?= $maxU ?></span>
          <span><?= round($maxU * 0.75) ?></span>
          <span><?= round($maxU * 0.5) ?></span>
          <span><?= round($maxU * 0.25) ?></span>
          <span>0</span>
        </div>
        <div class="chart-area">
          <div class="chart-bars-inner">
            <?php foreach ($users_chart as $i => $w): $h = ($w['c'] / $maxU) * 100; ?>
              <div class="chart-bar-modern" style="--bar-color:var(--primary);--bar-color-soft:var(--primary-300)">
                <div class="bar-tooltip"><?= $w['c'] ?></div>
                <div class="bar-fill" style="--h:<?= max(2, $h) ?>%;--delay:<?= $i*0.06 ?>s"></div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="chart-xaxis">
            <?php foreach ($users_chart as $w): ?>
              <span><?= $w['d'] ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="chart-card">
      <div class="chart-card-head">
        <div class="chart-card-title">
          <?= icon('card', 16) ?>
          <?= lang()==='uz_cyrillic' ? "Тушум" : "Tushum" ?>
          <small>· 7 <?= lang()==='uz_cyrillic' ? "кун" : "kun" ?></small>
        </div>
        <span class="badge-soft success"><?= money(array_sum(array_column($pay_chart,'c'))) ?> <?= t('soum') ?></span>
      </div>
      <?php $maxP = max(array_map(fn($x)=>$x['c'], $pay_chart)) ?: 1; ?>
      <div class="chart-bars">
        <div class="chart-yaxis">
          <span><?= round($maxP/1000) ?>k</span>
          <span><?= round($maxP*0.75/1000) ?>k</span>
          <span><?= round($maxP*0.5/1000) ?>k</span>
          <span><?= round($maxP*0.25/1000) ?>k</span>
          <span>0</span>
        </div>
        <div class="chart-area">
          <div class="chart-bars-inner">
            <?php foreach ($pay_chart as $i => $w): $h = ($w['c'] / $maxP) * 100; ?>
              <div class="chart-bar-modern" style="--bar-color:var(--accent-emerald);--bar-color-soft:#A7F3D0">
                <div class="bar-tooltip"><?= money($w['c']) ?></div>
                <div class="bar-fill" style="--h:<?= max(2, $h) ?>%;--delay:<?= $i*0.06 ?>s"></div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="chart-xaxis">
            <?php foreach ($pay_chart as $w): ?>
              <span><?= $w['d'] ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Pending payments alert -->
  <?php if (!empty($pending_payments)): ?>
  <div class="section-card mb-3">
    <div class="section-card-head">
      <div class="section-card-title">
        <?= icon('clock', 16) ?>
        <?= lang()==='uz_cyrillic' ? "Тасдиқлашни кутаётган тўловлар" : "Tasdiqlashni kutayotgan to'lovlar" ?>
        <span class="count-pill"><?= count($pending_payments) ?></span>
      </div>
      <a href="/admin/tolovlar.php?status=pending" class="chip is-active"><?= t('view_all') ?> →</a>
    </div>
    <div class="section-card-body flush">
      <?php foreach ($pending_payments as $p): ?>
        <div class="payment-card-row">
          <div class="pc-icon warning"><?= icon('clock', 18) ?></div>
          <div class="pc-info">
            <div class="pc-title"><?= e(($p['first_name'] ?? '—').' '.($p['last_name'] ?? '')) ?></div>
            <div class="pc-meta">
              <span><?= e($p['tname'] ?? '—') ?></span>
              <span class="activity-meta-dot"></span>
              <span class="data-cell-mono"><?= strtoupper(e($p['method'])) ?></span>
              <span class="activity-meta-dot"></span>
              <span><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></span>
            </div>
          </div>
          <div class="pc-amount"><?= money($p['amount']) ?> <small><?= t('soum') ?></small></div>
          <a href="/admin/tolovlar.php#p<?= $p['id'] ?>" class="btn btn-light btn-sm"><?= icon('arrow-right', 14) ?></a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Activity + recent users -->
  <div class="grid-2" style="gap:16px">

    <div class="section-card">
      <div class="section-card-head">
        <div class="section-card-title">
          <?= icon('logs', 16) ?>
          <?= lang()==='uz_cyrillic' ? "Сўнгги фаолият" : "So'nggi faoliyat" ?>
        </div>
        <a href="/admin/loglar.php" class="chip"><?= t('view_all') ?> →</a>
      </div>
      <div class="section-card-body">
        <?php if (empty($logs)): ?>
          <div class="empty-state-v2">
            <div class="empty-state-v2-icon"><?= icon('logs', 28) ?></div>
            <h3><?= lang()==='uz_cyrillic' ? "Ҳали фаолият йўқ" : "Hali faoliyat yo'q" ?></h3>
          </div>
        <?php else: ?>
          <div class="activity-feed">
            <?php foreach ($logs as $l):
              [$ai, $ac] = activity_icon($l['action']);
            ?>
            <div class="activity-item">
              <div class="activity-icon <?= $ac ?>"><?= icon($ai, 16) ?></div>
              <div class="activity-body">
                <div class="activity-title">
                  <?php if ($l['user_id']): ?>
                    <strong><?= e(($l['first_name'] ?? '—').' '.($l['last_name'] ?? '')) ?></strong>
                  <?php else: ?>
                    <strong><?= lang()==='uz_cyrillic' ? "Тизим" : "Tizim" ?></strong>
                  <?php endif; ?>
                  · <?= e($l['action']) ?>
                  <?php if (!empty($l['description'])): ?>
                    — <?= e(mb_substr($l['description'], 0, 64)) ?>
                  <?php endif; ?>
                </div>
                <div class="activity-meta">
                  <span><?= date('d.m H:i', strtotime($l['created_at'])) ?></span>
                  <?php if (!empty($l['ip_address'])): ?>
                    <span class="activity-meta-dot"></span>
                    <span><?= e($l['ip_address']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="section-card">
      <div class="section-card-head">
        <div class="section-card-title">
          <?= icon('user-plus', 16) ?>
          <?= lang()==='uz_cyrillic' ? "Янги фойдаланувчилар" : "Yangi foydalanuvchilar" ?>
        </div>
        <a href="/admin/users.php" class="chip"><?= t('view_all') ?> →</a>
      </div>
      <div class="section-card-body">
        <?php if (empty($recent_users)): ?>
          <div class="empty-state-v2">
            <div class="empty-state-v2-icon"><?= icon('user', 28) ?></div>
            <h3><?= lang()==='uz_cyrillic' ? "Ҳали фойдаланувчи йўқ" : "Hali foydalanuvchi yo'q" ?></h3>
          </div>
        <?php else: ?>
          <div class="activity-feed">
            <?php foreach ($recent_users as $u): ?>
            <div class="activity-item">
              <div class="data-cell-user-avatar" style="width:36px;height:36px">
                <?php if (!empty($u['avatar'])): ?>
                  <img src="<?= e($u['avatar']) ?>" alt="">
                <?php else: ?>
                  <?= mb_strtoupper(mb_substr($u['first_name'],0,1)) ?>
                <?php endif; ?>
              </div>
              <div class="activity-body">
                <div class="activity-title">
                  <strong><?= e($u['first_name'].' '.$u['last_name']) ?></strong>
                </div>
                <div class="activity-meta">
                  <span><?= e($u['email'] ?: $u['phone'] ?: '—') ?></span>
                  <span class="activity-meta-dot"></span>
                  <span><?= date('d.m.Y', strtotime($u['created_at'])) ?></span>
                  <?php if ($u['status']==='blocked'): ?>
                    <span class="activity-meta-dot"></span>
                    <span class="badge-soft danger"><?= e($u['status']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

</main>
</div>
</body></html>
