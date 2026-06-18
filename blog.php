<?php
/**
 * blog.php — STANDALONE blog listing
 */
require_once __DIR__ . '/includes/bootstrap.php';

$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';
$search   = trim($_GET['q'] ?? '');
$category = trim($_GET['cat'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 6;
$offset   = ($page - 1) * $perPage;

$where = "WHERE status='published'";
$params = [];
if ($search !== '') {
    $where .= " AND (title_$lang_field LIKE ? OR excerpt_$lang_field LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($category !== '') {
    $where .= " AND category = ?";
    $params[] = $category;
}

$total = (int)(db()->fetch("SELECT COUNT(*) c FROM blog_posts $where", $params)['c'] ?? 0);
$posts = db()->fetchAll("SELECT * FROM blog_posts $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);
$categories = db()->fetchAll("SELECT category, COUNT(*) c FROM blog_posts WHERE category IS NOT NULL AND category != '' AND status='published' GROUP BY category ORDER BY c DESC");
$totalPages = max(1, (int)ceil($total / $perPage));

$featured = $page === 1 && $search === '' && $category === ''
    ? db()->fetch("SELECT * FROM blog_posts WHERE status='published' ORDER BY views DESC, created_at DESC LIMIT 1")
    : null;

$site_name = setting('site_name', SITE_NAME);
?><!DOCTYPE html>
<html lang="<?= lang() === 'uz_cyrillic' ? 'uz-Cyrl' : 'uz' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#3B82F6">
<title><?= e(t('blog')) ?> — <?= e($site_name) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(setting('site_logo', '/assets/images/logo.svg')) ?>">
<style>
<?= base_css() ?>

/* ===== BLOG.PHP — content-focused design ===== */
.bl-header{background:rgba(255,255,255,.85);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:50}
.bl-nav{display:flex;align-items:center;justify-content:space-between;padding:14px 0;flex-wrap:wrap;gap:12px}
.bl-logo{display:inline-flex;align-items:center;gap:10px;font-weight:800;color:var(--text);text-decoration:none;font-size:15px}
.bl-logo .li{width:40px;height:40px;border-radius:11px;background:linear-gradient(135deg,var(--primary),#1E40AF);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px}
.bl-menu{display:flex;gap:18px;list-style:none}
.bl-menu a{color:var(--text-soft);font-size:14px;font-weight:500}
.bl-menu a:hover,.bl-menu a.active{color:var(--primary)}
.bl-actions{display:flex;align-items:center;gap:8px}
.bl-lang{display:inline-flex;background:var(--bg-mute);border-radius:8px;padding:3px;gap:2px}
.bl-lang a{padding:4px 10px;border-radius:5px;font-size:11px;font-weight:700;color:var(--text-soft)}
.bl-lang a.active{background:#fff;color:var(--primary)}
@media (max-width:880px){.bl-menu{display:none}}

.bl-hero{padding:60px 0 32px;background:linear-gradient(180deg,var(--bg-soft),#fff);text-align:center}
.bl-eyebrow{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--primary)}
.bl-hero h1{font-size:clamp(28px,5vw,48px);font-weight:900;letter-spacing:-.025em;margin:14px 0 12px;color:var(--text);line-height:1.05}
.bl-hero p{color:var(--text-soft);font-size:clamp(15px,1.4vw,17px);max-width:560px;margin:0 auto;line-height:1.6}

.bl-wrap{padding:32px 0 48px}

.featured{
  display:grid;grid-template-columns:1.2fr 1fr;gap:0;
  background:#fff;border:1px solid var(--border);border-radius:24px;overflow:hidden;
  margin-bottom:32px;text-decoration:none;color:inherit;
  transition:all .35s cubic-bezier(.22,1,.36,1);
}
.featured:hover{transform:translateY(-4px);box-shadow:0 24px 48px rgba(15,23,42,.1);border-color:var(--primary-200)}
.featured .img{aspect-ratio:16/10;background:linear-gradient(135deg,var(--primary),#8B5CF6,#EC4899);display:flex;align-items:center;justify-content:center;color:#fff;overflow:hidden;position:relative}
.featured .img img{width:100%;height:100%;object-fit:cover}
.featured .body{padding:32px;display:flex;flex-direction:column;justify-content:center}
.featured .badges{display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap}
.featured h2{font-size:24px;font-weight:800;letter-spacing:-.015em;line-height:1.3;margin-bottom:12px;color:var(--text)}
.featured p{color:var(--text-soft);font-size:14px;line-height:1.65;margin-bottom:14px}
.featured .meta{display:flex;align-items:center;gap:14px;font-size:13px;color:var(--text-mute)}
.featured .meta span{display:inline-flex;align-items:center;gap:5px}
@media (max-width:880px){.featured{grid-template-columns:1fr}.featured .body{padding:22px}}

/* Filter bar */
.filter-bar{background:#fff;border:1px solid var(--border);border-radius:16px;padding:14px;margin-bottom:24px;display:flex;gap:10px;flex-wrap:wrap;align-items:end}
.filter-bar .form-group{margin:0}
.filter-bar input,.filter-bar select{min-height:42px;padding:9px 12px;font-size:14px}
.search-input{position:relative}
.search-input .icn{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-mute);pointer-events:none}
.search-input input{padding-left:38px;min-width:220px}

.posts-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(280px,100%),1fr));gap:18px}
.post-card{
  display:block;background:#fff;border:1px solid var(--border);border-radius:18px;
  overflow:hidden;text-decoration:none;color:inherit;
  transition:all .3s cubic-bezier(.22,1,.36,1);
}
.post-card:hover{transform:translateY(-6px);box-shadow:0 16px 32px rgba(15,23,42,.08);border-color:var(--primary-200);color:inherit}
.post-card .img{aspect-ratio:16/10;background:linear-gradient(135deg,var(--primary-50),#fff);display:flex;align-items:center;justify-content:center;color:var(--primary);overflow:hidden;position:relative}
.post-card .img img{width:100%;height:100%;object-fit:cover;transition:transform .5s}
.post-card:hover .img img{transform:scale(1.06)}
.post-card .img .cat{position:absolute;top:12px;left:12px;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);color:var(--primary);font-size:10px;font-weight:700;padding:5px 11px;border-radius:100px;letter-spacing:.04em;text-transform:uppercase}
.post-card .body{padding:18px}
.post-card h3{font-size:16px;font-weight:700;line-height:1.4;letter-spacing:-.005em;margin-bottom:8px;color:var(--text);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.post-card p{color:var(--text-soft);font-size:13.5px;line-height:1.6;margin-bottom:14px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.post-card .meta{display:flex;justify-content:space-between;align-items:center;font-size:11.5px;color:var(--text-mute);padding-top:12px;border-top:1px solid var(--border)}
.post-card .meta-l{display:flex;gap:10px}
.post-card .meta span{display:inline-flex;align-items:center;gap:4px}

.empty{padding:48px 24px;text-align:center;background:linear-gradient(180deg,var(--bg-soft),transparent);border-radius:18px}
.empty-ico{width:72px;height:72px;border-radius:18px;background:#fff;border:1px solid var(--border);margin:0 auto 16px;display:flex;align-items:center;justify-content:center;color:var(--text-mute);box-shadow:var(--shadow-sm)}

.pagination{display:flex;gap:6px;justify-content:center;margin-top:32px;flex-wrap:wrap}
.pagination a,.pagination span{min-width:38px;height:38px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;background:#fff;border:1px solid var(--border);color:var(--text);font-weight:600;font-size:13px;padding:0 12px;transition:all .15s;text-decoration:none}
.pagination a:hover{border-color:var(--primary);color:var(--primary)}
.pagination .active{background:var(--primary);color:#fff;border-color:var(--primary)}

.bl-footer{background:#0F172A;color:#94A3B8;padding:24px 0;text-align:center;font-size:13px}
</style>
</head>
<body>

<header class="bl-header">
  <div class="container bl-nav">
    <a href="/" class="bl-logo"><span class="li">VP</span><span><?= e($site_name) ?></span></a>
    <ul class="bl-menu">
      <li><a href="/"><?= t('home') ?></a></li>
      <li><a href="/tariflar.php"><?= t('tariffs') ?></a></li>
      <li><a href="/blog.php" class="active"><?= t('blog') ?></a></li>
      <li><a href="/aloqa.php"><?= t('contact') ?></a></li>
    </ul>
    <div class="bl-actions">
      <div class="bl-lang">
        <a href="?setlang=uz_latin" class="<?= lang()==='uz_latin'?'active':'' ?>">Uz</a>
        <a href="?setlang=uz_cyrillic" class="<?= lang()==='uz_cyrillic'?'active':'' ?>">Кр</a>
      </div>
      <?php if (is_logged_in()): ?>
        <a href="/user/" class="btn btn-light btn-sm"><?= icon('user', 14) ?></a>
      <?php else: ?>
        <a href="/login.php" class="btn btn-primary btn-sm"><?= t('login') ?></a>
      <?php endif; ?>
    </div>
  </div>
</header>

<section class="bl-hero">
  <div class="container">
    <div class="bl-eyebrow"><?= icon('document', 12) ?> <?= t('blog') ?></div>
    <h1><?= lang()==='uz_cyrillic' ? "Фойдали мақолалар" : "Foydali maqolalar" ?></h1>
    <p><?= lang()==='uz_cyrillic' ? "Маслаҳатлар, янгиликлар ва қўлланмалар" : "Maslahatlar, yangiliklar va qo'llanmalar" ?></p>
  </div>
</section>

<section class="container bl-wrap">

  <?php if ($featured): ?>
  <a href="/blog-post.php?slug=<?= e($featured['slug']) ?>" class="featured">
    <div class="img">
      <?php if (!empty($featured['image'])): ?>
        <img src="<?= e($featured['image']) ?>" alt="">
      <?php else: ?>
        <?= icon('document', 80) ?>
      <?php endif; ?>
    </div>
    <div class="body">
      <div class="badges">
        <span class="badge badge-warning"><?= icon('star', 10) ?> <?= lang()==='uz_cyrillic' ? "Машҳур" : "Mashhur" ?></span>
        <?php if (!empty($featured['category'])): ?>
          <span class="badge" style="background:var(--bg-mute);color:var(--text-soft)"><?= e($featured['category']) ?></span>
        <?php endif; ?>
      </div>
      <h2><?= e($featured['title_'.$lang_field]) ?></h2>
      <p><?= e(mb_substr($featured['excerpt_'.$lang_field] ?? '', 0, 140)) ?>...</p>
      <div class="meta">
        <span><?= icon('calendar', 14) ?> <?= date('d.m.Y', strtotime($featured['created_at'])) ?></span>
        <span><?= icon('eye', 14) ?> <?= (int)$featured['views'] ?></span>
      </div>
    </div>
  </a>
  <?php endif; ?>

  <form method="get" class="filter-bar">
    <div class="form-group" style="flex:1;min-width:220px">
      <label class="form-label" style="font-size:12px;margin-bottom:4px"><?= t('search') ?></label>
      <div class="search-input">
        <span class="icn"><?= icon('search', 16) ?></span>
        <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="<?= lang()==='uz_cyrillic' ? "Қидириш..." : "Qidirish..." ?>">
      </div>
    </div>
    <div class="form-group" style="min-width:160px">
      <label class="form-label" style="font-size:12px;margin-bottom:4px"><?= lang()==='uz_cyrillic' ? "Категория" : "Kategoriya" ?></label>
      <select name="cat" class="form-control">
        <option value="">— <?= lang()==='uz_cyrillic' ? "Барчаси" : "Barchasi" ?></option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= e($c['category']) ?>" <?= $category===$c['category']?'selected':'' ?>>
            <?= e($c['category']) ?> (<?= $c['c'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary"><?= icon('search', 14) ?> <?= t('search') ?></button>
    <?php if ($search || $category): ?>
      <a href="/blog.php" class="btn btn-ghost"><?= icon('x', 14) ?></a>
    <?php endif; ?>
  </form>

  <?php if (empty($posts)): ?>
    <div class="empty">
      <div class="empty-ico"><?= icon('document', 32) ?></div>
      <h3 style="font-size:16px;margin-bottom:6px"><?= lang()==='uz_cyrillic' ? "Мақолалар топилмади" : "Maqolalar topilmadi" ?></h3>
      <p class="text-soft" style="margin-bottom:14px;font-size:13px"><?= lang()==='uz_cyrillic' ? "Қидирув шартларини ўзгартириб қайта уриниб кўринг" : "Qidiruv shartlarini o'zgartirib qayta urinib ko'ring" ?></p>
      <?php if ($search || $category): ?>
        <a href="/blog.php" class="btn btn-primary btn-sm"><?= icon('refresh', 14) ?> <?= lang()==='uz_cyrillic' ? "Барчасини кўриш" : "Barchasini ko'rish" ?></a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="posts-grid">
      <?php foreach ($posts as $p):
        $words = str_word_count(strip_tags($p['content_'.$lang_field] ?? ''));
        $rt = max(1, round($words / 200));
      ?>
      <a href="/blog-post.php?slug=<?= e($p['slug']) ?>" class="post-card">
        <div class="img">
          <?php if (!empty($p['image'])): ?>
            <img src="<?= e($p['image']) ?>" alt="">
          <?php else: ?>
            <?= icon('document', 36) ?>
          <?php endif; ?>
          <?php if (!empty($p['category'])): ?>
            <span class="cat"><?= e($p['category']) ?></span>
          <?php endif; ?>
        </div>
        <div class="body">
          <h3><?= e($p['title_'.$lang_field]) ?></h3>
          <p><?= e($p['excerpt_'.$lang_field]) ?></p>
          <div class="meta">
            <div class="meta-l">
              <span><?= icon('calendar', 12) ?> <?= date('d.m.Y', strtotime($p['created_at'])) ?></span>
              <span><?= icon('clock', 12) ?> <?= $rt ?> <?= t('min_read') ?></span>
            </div>
            <span><?= icon('eye', 12) ?> <?= (int)$p['views'] ?></span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>"><?= icon('chevron-left', 14) ?></a>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i == $page): ?><span class="active"><?= $i ?></span>
        <?php else: ?><a href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>"><?= icon('chevron-right', 14) ?></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>

</section>

<footer class="bl-footer">
  <div class="container">© <?= date('Y') ?> <?= e($site_name) ?>. <?= t('all_rights') ?>.</div>
</footer>

</body></html>
