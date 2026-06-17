<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$u = current_user();
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

// Statistika
$stat_total   = (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts WHERE user_id=? AND status='completed'", [$u['id']])['c'] ?? 0);
$stat_correct = (int)(db()->fetch("SELECT COALESCE(SUM(correct_answers),0) c FROM test_attempts WHERE user_id=?", [$u['id']])['c'] ?? 0);
$stat_total_q = (int)(db()->fetch("SELECT COALESCE(SUM(total_questions),0) c FROM test_attempts WHERE user_id=?", [$u['id']])['c'] ?? 0);
$stat_percent = $stat_total_q ? round($stat_correct / $stat_total_q * 100, 1) : 0;
$stat_rank    = (int)(db()->fetch("SELECT COUNT(*)+1 c FROM (SELECT user_id, AVG(score_percent) avg_p FROM test_attempts WHERE status='completed' GROUP BY user_id HAVING avg_p > (SELECT AVG(score_percent) FROM test_attempts WHERE user_id=? AND status='completed')) t", [$u['id']])['c'] ?? 0);

// So'nggi 5 ta test
$recent = db()->fetchAll("SELECT a.*, t.title_$lang_field title FROM test_attempts a LEFT JOIN tickets t ON a.ticket_id=t.id WHERE a.user_id=? ORDER BY a.started_at DESC LIMIT 5", [$u['id']]);

// Haftalik progress (oxirgi 7 kun)
$weekly = [];
for ($i=6; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $c = (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts WHERE user_id=? AND DATE(started_at)=?", [$u['id'], $d])['c'] ?? 0);
    $weekly[] = ['d' => date('d.m', strtotime($d)), 'c' => $c];
}

render_head(t('dashboard'));
?>
<div class="layout">
<?php render_sidebar('user', 'dashboard'); ?>
<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title"><?= lang()==='uz_cyrillic' ? 'Хуш келибсиз' : 'Xush kelibsiz' ?>, <?= e($u['first_name']) ?> 👋</div>
      <div style="color:var(--text-soft);font-size:14px"><?= lang()==='uz_cyrillic' ? 'Бугунги фаолиятингиз' : 'Bugungi faoliyatingiz' ?></div>
    </div>
    <a href="/user/testlar.php" class="btn btn-primary"><?= t('start_test') ?> →</a>
  </div>

  <!-- Statistik kartlar -->
  <div class="grid-4 mb-3">
    <div class="stat-card">
      <div class="icon">📝</div>
      <div class="value"><?= $stat_total ?></div>
      <div class="label"><?= lang()==='uz_cyrillic' ? 'Жами тестлар' : 'Jami testlar' ?></div>
    </div>
    <div class="stat-card">
      <div class="icon" style="background:#D1FAE5;color:#065F46">✓</div>
      <div class="value"><?= $stat_correct ?></div>
      <div class="label"><?= lang()==='uz_cyrillic' ? 'Тўғри жавоблар' : 'To\'g\'ri javoblar' ?></div>
    </div>
    <div class="stat-card">
      <div class="icon" style="background:#FEF3C7;color:#92400E">%</div>
      <div class="value"><?= $stat_percent ?>%</div>
      <div class="label"><?= lang()==='uz_cyrillic' ? 'Муваффақият фоизи' : 'Muvaffaqiyat foizi' ?></div>
    </div>
    <div class="stat-card">
      <div class="icon" style="background:#FCE7F3;color:#9F1239">🏆</div>
      <div class="value">#<?= $stat_rank ?></div>
      <div class="label"><?= t('rating') ?></div>
    </div>
  </div>

  <!-- Grafik (haftalik) -->
  <div class="card mb-3">
    <h3 style="margin-bottom:18px;font-size:18px;font-weight:700"><?= lang()==='uz_cyrillic' ? 'Ҳафталик прогресс' : 'Haftalik progress' ?></h3>
    <?php
      $maxC = max(array_map(fn($w) => $w['c'], $weekly)) ?: 1;
    ?>
    <div style="display:flex;align-items:flex-end;gap:10px;height:200px;padding:0 10px">
      <?php foreach ($weekly as $w):
        $h = max(8, ($w['c'] / $maxC) * 180);
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px">
        <div style="font-size:12px;font-weight:600;color:var(--primary)"><?= $w['c'] ?></div>
        <div style="width:100%;background:linear-gradient(180deg,var(--primary),var(--primary-light));border-radius:8px 8px 0 0;height:<?= $h ?>px;transition:height .5s"></div>
        <div style="font-size:11px;color:var(--text-soft)"><?= $w['d'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- So'nggi testlar -->
  <div class="card">
    <h3 style="margin-bottom:14px;font-size:18px;font-weight:700"><?= lang()==='uz_cyrillic' ? "Сўнгги тестлар" : "So'nggi testlar" ?></h3>
    <?php if (empty($recent)): ?>
      <div class="text-center" style="padding:40px;color:var(--text-soft)">
        <?= lang()==='uz_cyrillic' ? 'Ҳали тестлар йўқ. Биринчи тестингизни бошланг!' : 'Hali testlar yo\'q. Birinchi testingizni boshlang!' ?>
      </div>
    <?php else: ?>
    <div class="table-wrap" style="border:none;box-shadow:none">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th><?= lang()==='uz_cyrillic' ? 'Билет' : 'Bilet' ?></th>
            <th><?= t('date') ?></th>
            <th><?= lang()==='uz_cyrillic' ? 'Натижа' : 'Natija' ?></th>
            <th><?= t('status') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $i => $r): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><?= e($r['title'] ?? ($lang_field==='cyrillic'?'Тест':'Test')) ?></td>
            <td><?= date('d.m.Y H:i', strtotime($r['started_at'])) ?></td>
            <td><strong><?= $r['correct_answers'] ?>/<?= $r['total_questions'] ?></strong> · <?= $r['score_percent'] ?>%</td>
            <td>
              <?php if ($r['status']==='completed'): ?>
                <span class="badge badge-success"><?= t('completed') ?></span>
              <?php elseif ($r['status']==='in_progress'): ?>
                <span class="badge badge-warning"><?= t('in_progress') ?></span>
              <?php else: ?>
                <span class="badge badge-mute"><?= e($r['status']) ?></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</main>
</div>
</body></html>
