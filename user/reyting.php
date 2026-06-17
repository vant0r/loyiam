<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$u = current_user();
$period = $_GET['period'] ?? 'all'; // all | month | week

$dateFilter = '';
if ($period === 'month') $dateFilter = "AND a.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
elseif ($period === 'week') $dateFilter = "AND a.started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";

$top = db()->fetchAll(
    "SELECT u.id, u.first_name, u.last_name, u.avatar,
            COUNT(a.id) attempts,
            COALESCE(AVG(a.score_percent),0) avg_score,
            COALESCE(SUM(a.correct_answers),0) total_correct
     FROM users u
     LEFT JOIN test_attempts a ON a.user_id = u.id AND a.status='completed' $dateFilter
     WHERE u.role='user' AND u.status='active'
     GROUP BY u.id
     HAVING attempts > 0
     ORDER BY avg_score DESC, total_correct DESC
     LIMIT 100"
);

// Mening o'rnim
$my_rank = 0;
foreach ($top as $i => $r) {
    if ($r['id'] == $u['id']) { $my_rank = $i+1; break; }
}
$my = db()->fetch(
    "SELECT COUNT(a.id) attempts, COALESCE(AVG(a.score_percent),0) avg_score, COALESCE(SUM(a.correct_answers),0) total_correct
     FROM test_attempts a WHERE a.user_id=? AND a.status='completed' " . str_replace('a.','',$dateFilter),
    [$u['id']]
);

$top3 = array_slice($top, 0, 3);
$rest = array_slice($top, 3);

render_head(t('rating'));
?>
<div class="layout">
<?php render_sidebar('user', 'rating'); ?>
<main class="main">

  <!-- Modern page header -->
  <div class="page-header-modern">
    <div>
      <div class="page-eyebrow"><?= icon('trophy', 12) ?> <?= lang()==='uz_cyrillic' ? "Энг кучлилар" : "Eng kuchlilar" ?></div>
      <h1><?= t('rating') ?></h1>
      <div class="page-subtitle">
        <?= lang()==='uz_cyrillic' ? "Бошқа ўқувчилар билан рақобатлашинг ва энг яхши натижани кўрсатинг" : "Boshqa o'quvchilar bilan raqobatlashing va eng yaxshi natijani ko'rsating" ?>
      </div>
    </div>
    <div class="segment">
      <a href="?period=week"  class="<?= $period==='week'?'active':'' ?>"><?= lang()==='uz_cyrillic' ? "Ҳафта" : "Hafta" ?></a>
      <a href="?period=month" class="<?= $period==='month'?'active':'' ?>"><?= lang()==='uz_cyrillic' ? "Ой" : "Oy" ?></a>
      <a href="?period=all"   class="<?= $period==='all'?'active':'' ?>"><?= lang()==='uz_cyrillic' ? "Барчаси" : "Barchasi" ?></a>
    </div>
  </div>

  <!-- My rank card -->
  <div class="profile-banner mb-3">
    <div class="profile-banner-inner">
      <div class="profile-avatar-wrap">
        <?php if (!empty($u['avatar'])): ?>
          <img src="<?= e($u['avatar']) ?>" alt="" class="profile-avatar-img">
        <?php else: ?>
          <div class="profile-avatar-letter"><?= mb_substr($u['first_name'],0,1) ?></div>
        <?php endif; ?>
      </div>
      <div class="profile-meta">
        <div style="font-size:13px;opacity:.85;font-weight:600;text-transform:uppercase;letter-spacing:.05em">
          <?= lang()==='uz_cyrillic' ? "Сизнинг ўрнингиз" : "Sizning o'rningiz" ?>
        </div>
        <h2>
          <?= $my_rank > 0 ? "#$my_rank" : '—' ?>
          <span style="opacity:.6;font-weight:600;font-size:18px">·</span>
          <?= e($u['first_name'].' '.$u['last_name']) ?>
        </h2>
        <div class="profile-quickstats">
          <div><strong><?= (int)$my['attempts'] ?></strong> <span class="text-soft"><?= lang()==='uz_cyrillic' ? "тест" : "test" ?></span></div>
          <div><strong><?= round($my['avg_score'],1) ?>%</strong> <span class="text-soft"><?= lang()==='uz_cyrillic' ? "ўртача" : "o'rtacha" ?></span></div>
          <div><strong><?= (int)$my['total_correct'] ?></strong> <span class="text-soft"><?= lang()==='uz_cyrillic' ? "тўғри" : "to'g'ri" ?></span></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Top 3 podium -->
  <?php if (count($top3) >= 3): ?>
  <div class="podium">
    <?php
      // Order: silver(2), gold(1), bronze(3) in podium
      $podium_order = [1 => 'silver', 0 => 'gold', 2 => 'bronze'];
      foreach ($podium_order as $idx => $cls):
        if (!isset($top3[$idx])) continue;
        $r = $top3[$idx];
        $rank = $idx + 1;
        $isMe = $r['id'] == $u['id'];
    ?>
      <div class="podium-card <?= $cls ?> <?= $isMe ? 'is-me' : '' ?>">
        <div class="podium-rank"><?= $rank ?></div>
        <div class="podium-avatar">
          <?php if (!empty($r['avatar'])): ?>
            <img src="<?= e($r['avatar']) ?>" alt="">
          <?php else: ?>
            <?= mb_strtoupper(mb_substr($r['first_name'],0,1)) ?>
          <?php endif; ?>
        </div>
        <div class="podium-name"><?= e($r['first_name']) ?>
          <?php if ($isMe): ?><span class="badge-soft info"><?= lang()==='uz_cyrillic' ? "Сиз" : "Siz" ?></span><?php endif; ?>
        </div>
        <div class="podium-score">
          <span><?= $r['attempts'] ?> <?= lang()==='uz_cyrillic' ? "тест" : "test" ?></span>
          <span class="activity-meta-dot"></span>
          <strong><?= round($r['avg_score'],1) ?>%</strong>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Leaderboard -->
  <div class="section-card">
    <div class="section-card-head">
      <div class="section-card-title">
        <?= icon('users', 16) ?>
        <?= lang()==='uz_cyrillic' ? "Барча ўқувчилар" : "Barcha o'quvchilar" ?>
        <span class="count-pill"><?= count($top) ?></span>
      </div>
    </div>
    <div class="section-card-body flush">
      <?php if (empty($rest) && empty($top3)): ?>
        <div class="empty-state-v2">
          <div class="empty-state-v2-icon"><?= icon('trophy', 32) ?></div>
          <h3><?= lang()==='uz_cyrillic' ? "Ҳозирча натижалар йўқ" : "Hozircha natijalar yo'q" ?></h3>
          <p><?= lang()==='uz_cyrillic' ? "Биринчи бўлинг — ҳозироқ тестни бошланг" : "Birinchi bo'ling — hozir testni boshlang" ?></p>
          <a href="/user/testlar.php" class="btn btn-primary"><?= icon('play', 14) ?> <?= t('start_test') ?></a>
        </div>
      <?php else:
        // Display top3 inline if podium not shown (less than 3 entries)
        $display = count($top3) >= 3 ? $rest : $top;
        $offset = count($top3) >= 3 ? 4 : 1;
        $maxScore = !empty($top) ? $top[0]['avg_score'] : 100;
        foreach ($display as $i => $r):
          $rank = $i + $offset;
          $isMe = $r['id'] == $u['id'];
          $isTop = $rank <= 3;
          $progress = $maxScore ? min(100, $r['avg_score'] / $maxScore * 100) : 0;
      ?>
        <div class="lb-row <?= $isMe?'is-me':'' ?>">
          <div class="lb-rank <?= $isTop?'is-top':'' ?>"><?= $rank ?></div>
          <div class="lb-avatar">
            <?php if (!empty($r['avatar'])): ?>
              <img src="<?= e($r['avatar']) ?>" alt="">
            <?php else: ?>
              <?= mb_strtoupper(mb_substr($r['first_name'],0,1)) ?>
            <?php endif; ?>
          </div>
          <div class="lb-name">
            <?= e($r['first_name'].' '.$r['last_name']) ?>
            <?php if ($isMe): ?><span class="badge-soft info" style="margin-left:6px"><?= lang()==='uz_cyrillic' ? "Сиз" : "Siz" ?></span><?php endif; ?>
            <small><?= $r['attempts'] ?> <?= lang()==='uz_cyrillic' ? "тест" : "test" ?> · <?= $r['total_correct'] ?> <?= lang()==='uz_cyrillic' ? "тўғри" : "to'g'ri" ?></small>
          </div>
          <div class="lb-progress">
            <div class="lb-progress-bar" style="width:<?= round($progress) ?>%"></div>
          </div>
          <div class="lb-stat">
            <?= round($r['avg_score'],1) ?>%
            <small><?= lang()==='uz_cyrillic' ? "ўртача" : "o'rtacha" ?></small>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

</main>
</div>
</body></html>
