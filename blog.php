<?php
require_once __DIR__ . '/includes/functions.php';

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

// Featured post (eng so'nggi)
$featured = $page === 1 && $search === '' && $category === ''
    ? db()->fetch("SELECT * FROM blog_posts WHERE status='published' ORDER BY views DESC, created_at DESC LIMIT 1")
    : null;

render_head(t('blog'));
render_header('blog');
?>

<section style="padding:60px 0 32px;background:linear-gradient(180deg, var(--bg-soft), #fff)">
  <div class="container text-center" style="position:relative;z-index:1;max-width:720px">
    <div class="eyebrow"><?= icon('document', 12) ?> <?= t('blog') ?></div>
    <h1 style="margin:14px 0;font-size:clamp(28px, 4vw, 44px);font-weight:800;letter-spacing:-.02em;line-height:1.15"><?= lang()==='uz_cyrillic' ? "Фойдали мақолалар" : "Foydali maqolalar" ?></h1>
    <p style="color:var(--text-soft);font-size:clamp(15px, 1.4vw, 17px);line-height:1.65;max-width:600px;margin:0 auto"><?= lang()==='uz_cyrillic' ? "Маслаҳатлар, янгиликлар ва қўлланмалар" : "Maslahatlar, yangiliklar va qo'llanmalar" ?></p>
  </div>
</section>

<section class="section-sm">
  <div class="container">

    <!-- Featured post -->
    <?php if ($featured): ?>
    <a href="/blog-post.php?slug=<?= e($featured['slug']) ?>" class="card card-hover mb-4" style="display:grid;grid-template-columns:1.2fr 1fr;gap:0;padding:0;overflow:hidden;text-decoration:none;color:inherit;border-radius:20px">
      <div style="aspect-ratio:16/10;background:linear-gradient(135deg,var(--primary),var(--accent-violet),var(--accent-pink));display:flex;align-items:center;justify-content:center;color:#fff;position:relative;overflow:hidden">
        <?php if (!empty($featured['image'])): ?>
          <img src="<?= e($featured['image']) ?>" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?>
          <?= icon('document', 80) ?>
        <?php endif; ?>
      </div>
      <div style="padding:32px;display:flex;flex-direction:column;justify-content:center">
        <div class="flex gap-2 mb-2 items-center">
          <span class="badge-soft warning"><?= icon('star', 10) ?> <?= lang()==='uz_cyrillic' ? "Машҳур" : "Mashhur" ?></span>
          <?php if (!empty($featured['category'])): ?>
            <span class="badge-soft mute"><?= e($featured['category']) ?></span>
          <?php endif; ?>
        </div>
        <h2 style="font-size:24px;line-height:1.3;margin-bottom:12px;font-weight:800;letter-spacing:-.015em"><?= e($featured['title_'.$lang_field]) ?></h2>
        <p style="color:var(--text-soft);font-size:14px;line-height:1.65;margin-bottom:14px"><?= e(mb_substr($featured['excerpt_'.$lang_field] ?? '', 0, 140)) ?>...</p>
        <div class="flex items-center gap-3" style="font-size:13px;color:var(--text-mute)">
          <span><?= icon('calendar', 14) ?> <?= date('d.m.Y', strtotime($featured['created_at'])) ?></span>
          <span class="activity-meta-dot"></span>
          <span><?= icon('eye', 14) ?> <?= (int)$featured['views'] ?></span>
        </div>
      </div>
    </a>
    <?php endif; ?>

    <!-- Search & filter -->
    <form method="get" class="card mb-4" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
      <div class="form-group flex-1" style="margin-bottom:0;min-width:200px">
        <label class="form-label"><?= t('search') ?></label>
        <div class="input-group">
          <span class="input-icon"><?= icon('search', 16) ?></span>
          <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="<?= lang()==='uz_cyrillic' ? 'Қидириш...' : 'Qidirish...' ?>">
        </div>
      </div>
      <div class="form-group" style="margin-bottom:0;min-width:180px">
        <label class="form-label"><?= t('category') ?></label>
        <select name="cat" class="form-control">
          <option value=""><?= t('all') ?></option>
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

    <!-- Posts -->
    <?php if (empty($posts)): ?>
      <div class="empty-state-v2">
        <div class="empty-state-v2-icon"><?= icon('document', 32) ?></div>
        <h3><?= lang()==='uz_cyrillic' ? "Мақолалар топилмади" : "Maqolalar topilmadi" ?></h3>
        <p><?= lang()==='uz_cyrillic' ? "Қидирув шартларини ўзгартириб қайта уриниб кўринг" : "Qidiruv shartlarini o'zgartirib qayta urinib ko'ring" ?></p>
        <?php if ($search || $category): ?>
          <a href="/blog.php" class="btn btn-primary btn-sm"><?= icon('refresh', 14) ?> <?= lang()==='uz_cyrillic' ? "Барчасини кўриш" : "Barchasini ko'rish" ?></a>
        <?php endif; ?>
      </div>
    <?php else: ?>
    <div class="grid-3 stagger">
      <?php foreach ($posts as $p):
        $words = str_word_count(strip_tags($p['content_'.$lang_field] ?? ''));
        $rt = max(1, round($words / 200));
      ?>
      <a href="/blog-post.php?slug=<?= e($p['slug']) ?>" class="card card-hover" style="padding:0;overflow:hidden;text-decoration:none;color:inherit;display:block;border-radius:16px">
        <div style="aspect-ratio:16/9;background:linear-gradient(135deg,var(--primary-light),#fff);display:flex;align-items:center;justify-content:center;color:var(--primary);position:relative;overflow:hidden">
          <?php if (!empty($p['image'])): ?>
            <img src="<?= e($p['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
          <?php else: ?>
            <?= icon('document', 48) ?>
          <?php endif; ?>
          <?php if (!empty($p['category'])): ?>
            <span class="badge-soft info" style="position:absolute;top:12px;left:12px;background:rgba(255,255,255,.95);color:var(--primary);backdrop-filter:blur(8px)"><?= e($p['category']) ?></span>
          <?php endif; ?>
        </div>
        <div style="padding:20px">
          <h3 style="font-size:16px;font-weight:700;margin-bottom:8px;line-height:1.4;letter-spacing:-.005em;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= e($p['title_'.$lang_field]) ?></h3>
          <p class="text-soft" style="font-size:13.5px;line-height:1.6;margin-bottom:14px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
            <?= e($p['excerpt_'.$lang_field]) ?>
          </p>
          <div class="flex justify-between items-center" style="font-size:11.5px;color:var(--text-mute)">
            <div class="flex gap-2 items-center">
              <span><?= icon('calendar', 12) ?> <?= date('d.m.Y', strtotime($p['created_at'])) ?></span>
              <span class="activity-meta-dot"></span>
              <span><?= icon('clock', 12) ?> <?= $rt ?> <?= t('min_read') ?></span>
            </div>
            <span><?= icon('eye', 12) ?> <?= (int)$p['views'] ?></span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>"><?= icon('chevron-left', 16) ?></a>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i == $page): ?>
          <span class="active"><?= $i ?></span>
        <?php else: ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>"><?= icon('chevron-right', 16) ?></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php render_footer(); ?>
