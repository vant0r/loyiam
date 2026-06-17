<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

// Statistikalar
$total_users    = (int)(db()->fetch("SELECT COUNT(*) c FROM users WHERE role='user'")['c'] ?? 0);
$active_users   = (int)(db()->fetch("SELECT COUNT(*) c FROM users WHERE role='user' AND status='active'")['c'] ?? 0);
$total_tests    = (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts")['c'] ?? 0);
$total_questions= (int)(db()->fetch("SELECT COUNT(*) c FROM questions")['c'] ?? 0);
$total_payments = (int)(db()->fetch("SELECT COALESCE(SUM(amount),0) c FROM payments WHERE status='approved'")['c'] ?? 0);
$pending_pays   = (int)(db()->fetch("SELECT COUNT(*) c FROM payments WHERE status='pending'")['c'] ?? 0);

// Foydalanuvchilar (oxirgi 7 kun)
$users_chart = [];
for ($i=6; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $c = (int)(db()->fetch("SELECT COUNT(*) c FROM users WHERE DATE(created_at)=?", [$d])['c'] ?? 0);
    $users_chart[] = ['d' => date('d.m', strtotime($d)), 'c' => $c];
}
// To'lovlar (oxirgi 7 kun)
$pay_chart = [];
for ($i=6; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $c = (float)(db()->fetch("SELECT COALESCE(SUM(amount),0) c FROM payments WHERE DATE(created_at)=? AND status='approved'", [$d])['c'] ?? 0);
    $pay_chart[] = ['d' => date('d.m', strtotime($d)), 'c' => $c];
}

// So'nggi faoliyatlar
$logs = db()->fetchAll("SELECT l.*, u.first_name, u.last_name FROM logs l LEFT JOIN users u ON l.user_id=u.id ORDER BY l.created_at DESC LIMIT 8");
$recent_users = db()->fetchAll("SELECT * FROM users WHERE role='user' ORDER BY created_at DESC LIMIT 5");

render_head('Admin Dashboard');
?>
<div class="layout">
<?php render_sidebar('admin','dashboard'); ?>
<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">📊 Admin Dashboard</div>
      <div style="color:var(--text-soft);font-size:14px"><?= date('l, d F Y') ?></div>
    </div>
  </div>

  <!-- Stats -->
  <div class="grid-3 mb-3">
    <div class="stat-card"><div class="icon">👥</div><div class="value"><?= $total_users ?></div><div class="label"><?= t('users') ?></div></div>
    <div class="stat-card"><div class="icon" style="background:#D1FAE5;color:#065F46">✓</div><div class="value"><?= $active_users ?></div><div class="label"><?= lang()==='uz_cyrillic' ? 'Фаол' : 'Faol' ?></div></div>
    <div class="stat-card"><div class="icon" style="background:#FEF3C7;color:#92400E">📝</div><div class="value"><?= $total_tests ?></div><div class="label"><?= lang()==='uz_cyrillic' ? 'Тестлар' : 'Testlar' ?></div></div>
    <div class="stat-card"><div class="icon" style="background:#DBEAFE;color:#1E40AF">❓</div><div class="value"><?= $total_questions ?></div><div class="label"><?= t('questions') ?></div></div>
    <div class="stat-card"><div class="icon" style="background:#FCE7F3;color:#9F1239">💰</div><div class="value" style="font-size:22px"><?= money($total_payments) ?></div><div class="label"><?= lang()==='uz_cyrillic' ? 'Жами тушум' : 'Jami tushum' ?> (<?= t('soum') ?>)</div></div>
    <div class="stat-card"><div class="icon" style="background:#FEE2E2;color:#991B1B">⏳</div><div class="value"><?= $pending_pays ?></div><div class="label"><?= lang()==='uz_cyrillic' ? 'Кутилаётган тўловлар' : 'Kutilayotgan to\'lovlar' ?></div></div>
  </div>

  <!-- 2 ta grafik -->
  <div class="grid-2 mb-3">
    <div class="card">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:14px"><?= lang()==='uz_cyrillic' ? 'Янги фойдаланувчилар (7 кун)' : 'Yangi foydalanuvchilar (7 kun)' ?></h3>
      <?php $maxU = max(array_map(fn($x)=>$x['c'], $users_chart)) ?: 1; ?>
      <div style="display:flex;align-items:flex-end;gap:8px;height:160px">
        <?php foreach ($users_chart as $w): $h=max(8,$w['c']/$maxU*140); ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px">
            <div style="font-size:11px;font-weight:600;color:var(--primary)"><?= $w['c'] ?></div>
            <div style="width:100%;background:linear-gradient(180deg,var(--primary),var(--primary-light));border-radius:6px 6px 0 0;height:<?= $h ?>px"></div>
            <div style="font-size:10px;color:var(--text-soft)"><?= $w['d'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:14px"><?= lang()==='uz_cyrillic' ? 'Тушум (7 кун)' : 'Tushum (7 kun)' ?></h3>
      <?php $maxP = max(array_map(fn($x)=>$x['c'], $pay_chart)) ?: 1; ?>
      <div style="display:flex;align-items:flex-end;gap:8px;height:160px">
        <?php foreach ($pay_chart as $w): $h=max(8,$w['c']/$maxP*140); ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px">
            <div style="font-size:10px;font-weight:600;color:var(--success)"><?= $w['c']>0 ? round($w['c']/1000).'k' : 0 ?></div>
            <div style="width:100%;background:linear-gradient(180deg,var(--success),#A7F3D0);border-radius:6px 6px 0 0;height:<?= $h ?>px"></div>
            <div style="font-size:10px;color:var(--text-soft)"><?= $w['d'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- So'nggi faoliyat va yangi userlar -->
  <div class="grid-2">
    <div class="card">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:14px"><?= lang()==='uz_cyrillic' ? 'Сўнгги фаолият' : 'So\'nggi faoliyat' ?></h3>
      <?php foreach ($logs as $l): ?>
      <div style="padding:10px 0;border-bottom:1px solid var(--border)">
        <div style="font-size:13px"><?= e(($l['first_name'] ?? '—').' '.($l['last_name'] ?? '')) ?> · <strong><?= e($l['action']) ?></strong></div>
        <div style="font-size:12px;color:var(--text-mute)"><?= date('d.m.Y H:i', strtotime($l['created_at'])) ?> · <?= e($l['ip_address']) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($logs)): ?><div class="text-center" style="padding:20px;color:var(--text-soft)">Loglar yo'q</div><?php endif; ?>
    </div>

    <div class="card">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:14px"><?= lang()==='uz_cyrillic' ? 'Янги фойдаланувчилар' : 'Yangi foydalanuvchilar' ?></h3>
      <?php foreach ($recent_users as $u): ?>
      <div style="padding:10px 0;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px">
        <div class="review-avatar" style="width:38px;height:38px;font-size:14px;flex-shrink:0"><?= mb_substr($u['first_name'],0,1) ?></div>
        <div style="flex:1">
          <div style="font-size:14px;font-weight:600"><?= e($u['first_name'].' '.$u['last_name']) ?></div>
          <div style="font-size:12px;color:var(--text-mute)"><?= e($u['email'] ?? $u['phone']) ?></div>
        </div>
        <div style="font-size:12px;color:var(--text-mute)"><?= date('d.m', strtotime($u['created_at'])) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($recent_users)): ?><div class="text-center" style="padding:20px;color:var(--text-soft)">Foydalanuvchilar yo'q</div><?php endif; ?>
    </div>
  </div>
</main>
</div>
</body></html>
