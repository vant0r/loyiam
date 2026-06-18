<?php
/**
 * user/reyting.php — STANDALONE leaderboard with podium
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$u = current_user();
$period = $_GET['period'] ?? 'all';
$dateFilter = '';
if ($period === 'month') $dateFilter = "AND a.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
elseif ($period === 'week') $dateFilter = "AND a.started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";

$top = db()->fetchAll(
    "SELECT u.id, u.first_name, u.last_name, u.avatar,
            COUNT(a.id) attempts, COALESCE(AVG(a.score_percent),0) avg_score,
            COALESCE(SUM(a.correct_answers),0) total_correct
     FROM users u LEFT JOIN test_attempts a ON a.user_id = u.id AND a.status='completed' $dateFilter
     WHERE u.role='user' AND u.status='active'
     GROUP BY u.id HAVING attempts > 0
     ORDER BY avg_score DESC, total_correct DESC LIMIT 100");

$my_rank = 0;
foreach ($top as $i => $r) if ($r['id'] == $u['id']) { $my_rank = $i+1; break; }

$my = db()->fetch(
    "SELECT COUNT(a.id) attempts, COALESCE(AVG(a.score_percent),0) avg_score, COALESCE(SUM(a.correct_answers),0) total_correct
     FROM test_attempts a WHERE a.user_id=? AND a.status='completed' " . str_replace('a.','',$dateFilter), [$u['id']]);

$top3 = array_slice($top, 0, 3);
$rest = array_slice($top, 3);
$site_name = setting('site_name', SITE_NAME);
?><!DOCTYPE html>
<html lang="<?= lang() === 'uz_cyrillic' ? 'uz-Cyrl' : 'uz' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#3B82F6">
<title><?= e(t('rating')) ?> — <?= e($site_name) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(setting('site_logo', '/assets/images/logo.svg')) ?>">
<style>
<?= base_css() ?>
<?= panel_css() ?>

/* === USER/REYTING.PHP custom === */
.segment{display:inline-flex;background:var(--bg-mute);padding:3px;border-radius:10px;gap:2px}
.segment a{padding:7px 14px;border-radius:7px;font-size:13px;font-weight:600;color:var(--text-soft);text-decoration:none;transition:all .15s}
.segment a.active{background:#fff;color:var(--text);box-shadow:0 1px 2px rgba(15,23,42,.08)}

.profile-banner{
  position:relative;background:linear-gradient(135deg,#4F46E5 0%,#3B82F6 50%,#06B6D4 100%);
  border-radius:20px;padding:24px;color:#fff;overflow:hidden;
  box-shadow:0 16px 40px rgba(79,70,229,.3);margin-bottom:18px;
}
.profile-banner::before{content:'';position:absolute;top:-30%;right:-10%;width:280px;height:280px;background:radial-gradient(circle,rgba(255,255,255,.18),transparent 70%);border-radius:50%}
.pb-inner{position:relative;z-index:1;display:flex;align-items:center;gap:18px;flex-wrap:wrap}
.pb-avatar{width:80px;height:80px;border-radius:50%;border:3px solid rgba(255,255,255,.3);flex-shrink:0;overflow:hidden;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center}
.pb-avatar img{width:100%;height:100%;object-fit:cover}
.pb-letter{font-size:36px;font-weight:900}
.pb-meta{flex:1;min-width:200px}
.pb-eyebrow{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;opacity:.85}
.pb-title{color:#fff;font-size:22px;font-weight:800;margin:6px 0 12px;letter-spacing:-.01em}
.pb-stats{display:flex;gap:18px;flex-wrap:wrap;padding-top:12px;border-top:1px solid rgba(255,255,255,.2)}
.pb-stats > div{display:flex;flex-direction:column}
.pb-stats strong{font-size:18px;font-weight:800}
.pb-stats span{font-size:11.5px;opacity:.8}

.podium{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:24px 16px 18px;padding-top:18px}
.podium-card{position:relative;background:#fff;border-radius:14px;border:1px solid #EEF1F5;padding:22px 14px 16px;text-align:center}
.podium-rank{position:absolute;top:-14px;left:50%;transform:translateX(-50%);width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;color:#fff;border:3px solid #fff;box-shadow:0 4px 14px rgba(0,0,0,.15)}
.podium-card.gold{border-color:#FCD34D;background:linear-gradient(180deg,#FFFBEB 0%,#fff 50%);transform:translateY(-10px)}
.podium-card.gold .podium-rank{background:linear-gradient(135deg,#FBBF24,#D97706)}
.podium-card.silver{border-color:#CBD5E1;background:linear-gradient(180deg,#F8FAFC 0%,#fff 50%)}
.podium-card.silver .podium-rank{background:linear-gradient(135deg,#94A3B8,#475569)}
.podium-card.bronze{border-color:#FDBA74;background:linear-gradient(180deg,#FFF7ED 0%,#fff 50%)}
.podium-card.bronze .podium-rank{background:linear-gradient(135deg,#FB923C,#9A3412)}
.podium-avatar{width:56px;height:56px;border-radius:50%;margin:6px auto 10px;background:linear-gradient(135deg,#3B82F6,#1E40AF);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:20px;overflow:hidden;border:3px solid #fff;box-shadow:0 4px 14px rgba(0,0,0,.08)}
.podium-avatar img{width:100%;height:100%;object-fit:cover}
.podium-name{font-weight:700;font-size:14px;margin-bottom:4px}
.podium-score{font-size:11px;color:var(--text-mute);display:flex;align-items:center;justify-content:center;gap:6px}
.podium-score strong{color:var(--text);font-size:12.5px}

.section-card{background:#fff;border:1px solid #EEF1F5;border-radius:14px;overflow:hidden}
.section-card-head{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #EEF1F5;background:#FAFBFC}
.section-card-title{font-size:13.5px;font-weight:700;display:flex;align-items:center;gap:8px}
.count-pill{display:inline-flex;padding:2px 8px;border-radius:100px;background:var(--bg-mute);color:var(--text-soft);font-size:11px;font-weight:600}

.lb-row{display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid #EEF1F5;transition:background .15s}
.lb-row:last-child{border-bottom:none}
.lb-row:hover{background:#FAFBFC}
.lb-row.is-me{background:#EFF6FF}
.lb-rank{width:32px;height:32px;border-radius:9px;background:var(--bg-mute);color:var(--text-soft);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12.5px;flex-shrink:0;font-variant-numeric:tabular-nums}
.lb-rank.is-top{background:linear-gradient(135deg,#FBBF24,#D97706);color:#fff}
.lb-avatar{width:36px;height:36px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,#3B82F6,#1E40AF);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;overflow:hidden}
.lb-avatar img{width:100%;height:100%;object-fit:cover}
.lb-name{flex:1;min-width:0;font-weight:600;font-size:13.5px}
.lb-name small{display:block;font-weight:500;font-size:11px;color:var(--text-mute);margin-top:2px}
.lb-stat{font-variant-numeric:tabular-nums;font-weight:700;font-size:13.5px;text-align:right;min-width:64px}
.lb-stat small{display:block;font-size:10.5px;color:var(--text-mute);margin-top:2px;font-weight:500}

@media (max-width:640px){
  .podium{grid-template-columns:1fr;gap:8px;margin:14px 8px 14px}
  .podium-card{display:flex;align-items:center;gap:14px;padding:14px;text-align:left}
  .podium-card.gold{transform:none}
  .podium-rank{position:static;transform:none;margin:0;flex-shrink:0}
  .podium-avatar{margin:0;width:42px;height:42px;font-size:15px}
  .podium-score{justify-content:flex-start}
}
</style>
</head>
<body>
<div class="layout">
<?= panel_sidebar('user', 'rating') ?>
<main class="main">

<div class="page-header-modern">
  <div>
    <div class="page-eyebrow"><?= icon('trophy', 12) ?> <?= lang()==='uz_cyrillic' ? "Энг кучлилар" : "Eng kuchlilar" ?></div>
    <h1><?= t('rating') ?></h1>
    <div class="page-subtitle"><?= lang()==='uz_cyrillic' ? "Бошқа ўқувчилар билан рақобатлашинг" : "Boshqa o'quvchilar bilan raqobatlashing" ?></div>
  </div>
  <div class="segment">
    <a href="?period=week" class="<?= $period==='week'?'active':'' ?>"><?= lang()==='uz_cyrillic' ? "Ҳафта" : "Hafta" ?></a>
    <a href="?period=month" class="<?= $period==='month'?'active':'' ?>"><?= lang()==='uz_cyrillic' ? "Ой" : "Oy" ?></a>
    <a href="?period=all" class="<?= $period==='all'?'active':'' ?>"><?= lang()==='uz_cyrillic' ? "Барчаси" : "Barchasi" ?></a>
  </div>
</div>

<!-- My rank -->
<div class="profile-banner">
  <div class="pb-inner">
    <div class="pb-avatar">
      <?php if (!empty($u['avatar'])): ?><img src="<?= e($u['avatar']) ?>" alt=""><?php else: ?><span class="pb-letter"><?= mb_substr($u['first_name'],0,1) ?></span><?php endif; ?>
    </div>
    <div class="pb-meta">
      <div class="pb-eyebrow"><?= lang()==='uz_cyrillic' ? "Сизнинг ўрнингиз" : "Sizning o'rningiz" ?></div>
      <div class="pb-title"><?= $my_rank > 0 ? "#$my_rank" : '—' ?> · <?= e($u['first_name'].' '.$u['last_name']) ?></div>
      <div class="pb-stats">
        <div><strong><?= (int)$my['attempts'] ?></strong><span><?= lang()==='uz_cyrillic' ? "тест" : "test" ?></span></div>
        <div><strong><?= round($my['avg_score'],1) ?>%</strong><span><?= lang()==='uz_cyrillic' ? "ўртача" : "o'rtacha" ?></span></div>
        <div><strong><?= (int)$my['total_correct'] ?></strong><span><?= lang()==='uz_cyrillic' ? "тўғри" : "to'g'ri" ?></span></div>
      </div>
    </div>
  </div>
</div>

<!-- Top 3 podium -->
<?php if (count($top3) >= 3): ?>
<div class="podium">
  <?php foreach ([1=>'silver', 0=>'gold', 2=>'bronze'] as $idx => $cls):
    if (!isset($top3[$idx])) continue;
    $r = $top3[$idx]; $rank = $idx + 1; $isMe = $r['id'] == $u['id'];
  ?>
  <div class="podium-card <?= $cls ?>">
    <div class="podium-rank"><?= $rank ?></div>
    <div class="podium-avatar">
      <?php if (!empty($r['avatar'])): ?><img src="<?= e($r['avatar']) ?>" alt=""><?php else: ?><?= mb_strtoupper(mb_substr($r['first_name'],0,1)) ?><?php endif; ?>
    </div>
    <div>
      <div class="podium-name"><?= e($r['first_name']) ?><?php if ($isMe): ?> <span class="badge badge-info" style="font-size:9px"><?= lang()==='uz_cyrillic' ? "Сиз" : "Siz" ?></span><?php endif; ?></div>
      <div class="podium-score"><span><?= $r['attempts'] ?> <?= lang()==='uz_cyrillic' ? "тест" : "test" ?></span> · <strong><?= round($r['avg_score'],1) ?>%</strong></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Leaderboard list -->
<div class="section-card">
  <div class="section-card-head">
    <div class="section-card-title"><?= icon('users', 16) ?> <?= lang()==='uz_cyrillic' ? "Барча ўқувчилар" : "Barcha o'quvchilar" ?> <span class="count-pill"><?= count($top) ?></span></div>
  </div>
  <?php if (empty($top)): ?>
    <div style="padding:36px 20px;text-align:center"><?= icon('trophy', 36) ?><h3 style="font-size:15px;margin:10px 0"><?= lang()==='uz_cyrillic' ? "Натижалар йўқ" : "Natijalar yo'q" ?></h3><a href="/user/testlar.php" class="btn btn-primary btn-sm"><?= icon('play', 14) ?> <?= t('start_test') ?></a></div>
  <?php else:
    $display = count($top3) >= 3 ? $rest : $top;
    $offset = count($top3) >= 3 ? 4 : 1;
    foreach ($display as $i => $r):
      $rank = $i + $offset; $isMe = $r['id'] == $u['id']; $isTop = $rank <= 3;
  ?>
    <div class="lb-row <?= $isMe?'is-me':'' ?>">
      <div class="lb-rank <?= $isTop?'is-top':'' ?>"><?= $rank ?></div>
      <div class="lb-avatar"><?php if (!empty($r['avatar'])): ?><img src="<?= e($r['avatar']) ?>" alt=""><?php else: ?><?= mb_strtoupper(mb_substr($r['first_name'],0,1)) ?><?php endif; ?></div>
      <div class="lb-name">
        <?= e($r['first_name'].' '.$r['last_name']) ?><?php if ($isMe): ?> <span class="badge badge-info" style="font-size:9px;margin-left:4px"><?= lang()==='uz_cyrillic' ? "Сиз" : "Siz" ?></span><?php endif; ?>
        <small><?= $r['attempts'] ?> <?= lang()==='uz_cyrillic' ? "тест" : "test" ?> · <?= $r['total_correct'] ?> <?= lang()==='uz_cyrillic' ? "тўғри" : "to'g'ri" ?></small>
      </div>
      <div class="lb-stat"><?= round($r['avg_score'],1) ?>%<small><?= lang()==='uz_cyrillic' ? "ўртача" : "o'rtacha" ?></small></div>
    </div>
  <?php endforeach; endif; ?>
</div>

</main>
</div>
<script><?= panel_js() ?></script>
</body></html>
