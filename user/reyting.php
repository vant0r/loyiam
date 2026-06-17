<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$u = current_user();
$period = $_GET['period'] ?? 'all'; // all | month

$dateFilter = '';
if ($period === 'month') $dateFilter = "AND a.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

$top = db()->fetchAll(
    "SELECT u.id, u.first_name, u.last_name, u.avatar,
            COUNT(a.id) attempts,
            COALESCE(AVG(a.score_percent),0) avg_score,
            COALESCE(SUM(a.correct_answers),0) total_correct
     FROM users u
     LEFT JOIN test_attempts a ON a.user_id = u.id AND a.status='completed' $dateFilter
     WHERE u.role='user' AND u.status='active'
     GROUP BY u.id
     ORDER BY avg_score DESC, total_correct DESC
     LIMIT 100"
);

// Mening o'rnim
$my_rank = 1;
foreach ($top as $i => $r) {
    if ($r['id'] == $u['id']) { $my_rank = $i+1; break; }
}
$my = db()->fetch(
    "SELECT COUNT(a.id) attempts, COALESCE(AVG(a.score_percent),0) avg_score, COALESCE(SUM(a.correct_answers),0) total_correct
     FROM test_attempts a WHERE a.user_id=? AND a.status='completed' " . str_replace('a.','',$dateFilter),
    [$u['id']]
);

render_head(t('rating'));
?>
<div class="layout">
<?php render_sidebar('user', 'rating'); ?>
<main class="main">
  <div class="page-header">
    <div class="page-title">🏆 <?= t('rating') ?></div>
    <div class="lang-switch">
      <a href="?period=all" class="<?= $period==='all'?'active':'' ?>"><?= lang()==='uz_cyrillic' ? 'Умумий' : 'Umumiy' ?></a>
      <a href="?period=month" class="<?= $period==='month'?'active':'' ?>"><?= lang()==='uz_cyrillic' ? 'Ойлик' : 'Oylik' ?></a>
    </div>
  </div>

  <!-- Mening o'rnim -->
  <div class="card mb-3" style="background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;border:none">
    <div class="flex items-center justify-between flex-wrap gap-2">
      <div class="flex items-center" style="gap:18px">
        <div style="width:64px;height:64px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800">#<?= $my_rank ?></div>
        <div>
          <div style="font-size:13px;opacity:.85"><?= lang()==='uz_cyrillic' ? 'Сизнинг ўрнингиз' : 'Sizning o\'rningiz' ?></div>
          <div style="font-size:22px;font-weight:800"><?= e($u['first_name'].' '.$u['last_name']) ?></div>
        </div>
      </div>
      <div class="flex" style="gap:30px">
        <div><div style="font-size:13px;opacity:.85"><?= lang()==='uz_cyrillic' ? 'Тестлар' : 'Testlar' ?></div><div style="font-size:24px;font-weight:800"><?= (int)$my['attempts'] ?></div></div>
        <div><div style="font-size:13px;opacity:.85"><?= lang()==='uz_cyrillic' ? 'Ўртача' : 'O\'rtacha' ?></div><div style="font-size:24px;font-weight:800"><?= round($my['avg_score'],1) ?>%</div></div>
        <div><div style="font-size:13px;opacity:.85"><?= lang()==='uz_cyrillic' ? 'Тўғри' : 'To\'g\'ri' ?></div><div style="font-size:24px;font-weight:800"><?= (int)$my['total_correct'] ?></div></div>
      </div>
    </div>
  </div>

  <!-- Top -->
  <div class="card" style="padding:0">
    <div style="padding:20px 24px;border-bottom:1px solid var(--border)">
      <h3 style="font-size:18px;font-weight:700"><?= lang()==='uz_cyrillic' ? 'Топ 100 ўқувчи' : 'Top 100 o\'quvchi' ?></h3>
    </div>
    <div class="table-wrap" style="border:none;box-shadow:none">
      <table>
        <thead><tr><th>#</th><th><?= lang()==='uz_cyrillic' ? 'Фойдаланувчи' : 'Foydalanuvchi' ?></th><th><?= lang()==='uz_cyrillic' ? 'Тестлар' : 'Testlar' ?></th><th><?= lang()==='uz_cyrillic' ? 'Тўғри' : 'To\'g\'ri' ?></th><th><?= lang()==='uz_cyrillic' ? 'Ўртача %' : 'O\'rtacha %' ?></th></tr></thead>
        <tbody>
          <?php foreach ($top as $i => $r):
            $rank = $i+1;
            $isMe = $r['id'] == $u['id'];
            $medal = $rank===1?'🥇':($rank===2?'🥈':($rank===3?'🥉':''));
          ?>
          <tr style="<?= $isMe ? 'background:var(--primary-light)' : '' ?>">
            <td style="font-weight:700;font-size:16px"><?= $medal ?: '#'.$rank ?></td>
            <td>
              <div class="flex items-center" style="gap:10px">
                <div class="review-avatar" style="width:36px;height:36px;font-size:14px"><?= mb_substr($r['first_name'],0,1) ?></div>
                <div><strong><?= e($r['first_name'].' '.$r['last_name']) ?></strong> <?= $isMe ? '<span class="badge badge-info" style="margin-left:6px">'.(lang()==='uz_cyrillic' ? 'Сиз' : 'Siz').'</span>' : '' ?></div>
              </div>
            </td>
            <td><?= $r['attempts'] ?></td>
            <td><?= $r['total_correct'] ?></td>
            <td><strong style="color:var(--primary)"><?= round($r['avg_score'],1) ?>%</strong></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($top)): ?>
            <tr><td colspan="5" class="text-center" style="padding:40px;color:var(--text-soft)"><?= lang()==='uz_cyrillic' ? 'Маълумотлар йўқ' : 'Ma\'lumotlar yo\'q' ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>
</body></html>
