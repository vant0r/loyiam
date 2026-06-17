<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';
$slug = trim($_GET['slug'] ?? '');

if (!$slug) { header('Location: /blog.php'); exit; }

$post = db()->fetch("SELECT * FROM blog_posts WHERE slug=? AND status='published'", [$slug]);
if (!$post) {
    http_response_code(404);
    render_head('404');
    render_header('blog');
    echo '<div class="container section text-center"><h1>404</h1><p>Maqola topilmadi</p><a href="/blog.php" class="btn btn-primary">Blog</a></div>';
    render_footer();
    exit;
}

// Ko'rishlar sonini oshirish
db()->execute("UPDATE blog_posts SET views = views + 1 WHERE id=?", [$post['id']]);

// O'qish vaqti — taxminan
$content = $post['content_'.$lang_field];
$wordCount = str_word_count(strip_tags($content));
$readingTime = max(1, round($wordCount / 200));

// O'xshash maqolalar
$related = db()->fetchAll(
    "SELECT * FROM blog_posts WHERE status='published' AND id != ? AND category = ? LIMIT 3",
    [$post['id'], $post['category']]);
if (count($related) < 3) {
    $extra = db()->fetchAll(
        "SELECT * FROM blog_posts WHERE status='published' AND id != ? ORDER BY RAND() LIMIT ?",
        [$post['id'], 3 - count($related)]);
    $related = array_merge($related, $extra);
}

// Comments (DB jadval yo'q hozir, demo data — kelajakda kengaytirish mumkin)
$comments_raw = is_file(__DIR__.'/cache/comments_'.$post['id'].'.json')
    ? json_decode(@file_get_contents(__DIR__.'/cache/comments_'.$post['id'].'.json'), true) : [];
$comments = is_array($comments_raw) ? $comments_raw : [];

// Yangi comment qo'shish
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'comment' && csrf_check()) {
    if (is_logged_in()) {
        $u = current_user();
        $text = Security::clean($_POST['text'] ?? '', 1000);
        if (mb_strlen($text) >= 5) {
            $comments[] = [
                'name' => $u['first_name'].' '.$u['last_name'],
                'avatar_letter' => mb_substr($u['first_name'], 0, 1),
                'text' => $text,
                'date' => date('Y-m-d H:i:s'),
            ];
            @mkdir(__DIR__.'/cache', 0755, true);
            @file_put_contents(__DIR__.'/cache/comments_'.$post['id'].'.json', json_encode($comments));
            $msg = lang()==='uz_cyrillic' ? 'Шарҳ қўшилди' : 'Sharh qo\'shildi';
        }
    }
}

render_head($post['title_'.$lang_field], ['description' => mb_substr($post['excerpt_'.$lang_field] ?? '', 0, 160)]);
render_header('blog');
?>

<style>
.blog-hero{padding:48px 0 24px;background:linear-gradient(180deg,var(--primary-50),#fff)}
.blog-meta{display:flex;gap:18px;color:var(--text-soft);font-size:13px;flex-wrap:wrap;align-items:center;margin:18px 0}
.blog-meta > span{display:flex;align-items:center;gap:6px}
.blog-content{font-size:17px;line-height:1.85;color:var(--text)}
.blog-content > * {margin-bottom:18px}
.blog-content h2{font-size:28px;margin:32px 0 14px;font-weight:700;scroll-margin-top:80px}
.blog-content h3{font-size:22px;margin:28px 0 12px;font-weight:700;scroll-margin-top:80px}
.blog-content p{margin-bottom:16px}
.blog-content img{border-radius:var(--r-lg);margin:24px 0}
.blog-content blockquote{border-left:4px solid var(--primary);padding:14px 22px;background:var(--primary-50);
  border-radius:0 var(--r-md) var(--r-md) 0;margin:24px 0;font-style:italic;color:var(--text-soft)}
.blog-content code{background:var(--bg-mute);padding:2px 8px;border-radius:4px;font-size:.9em}
.blog-content ul,.blog-content ol{padding-left:22px;margin-bottom:16px}
.blog-content li{margin-bottom:8px}

.toc{position:sticky;top:90px;align-self:flex-start;background:#fff;border:1px solid var(--border);
  border-radius:var(--r-lg);padding:18px;font-size:13px}
.toc h4{font-size:13px;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px}
.toc ul{list-style:none;border-left:2px solid var(--border)}
.toc li{padding-left:14px;position:relative;margin-bottom:8px}
.toc a{color:var(--text-soft);transition:color .15s;display:block;line-height:1.4}
.toc a:hover,.toc a.active{color:var(--primary)}
.toc a.active{font-weight:600}
.toc a.active::before{content:'';position:absolute;left:-2px;top:0;bottom:0;width:2px;background:var(--primary)}

.share-bar{position:sticky;top:100px;display:flex;flex-direction:column;gap:8px;align-self:flex-start}
.share-bar button{width:42px;height:42px;border-radius:var(--r-md);background:#fff;border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;color:var(--text-soft);cursor:pointer;transition:all .15s}
.share-bar button:hover{border-color:var(--primary);color:var(--primary);transform:translateY(-2px)}
.share-bar button.copied{background:var(--success);color:#fff;border-color:var(--success)}

.comment{display:flex;gap:14px;padding:16px 0;border-bottom:1px solid var(--border)}
.comment:last-child{border-bottom:none}
.comment-avatar{flex-shrink:0;width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));
  color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
.comment-body{flex:1}
.comment-head{display:flex;justify-content:space-between;margin-bottom:4px;flex-wrap:wrap;gap:8px}
.comment-name{font-weight:600;font-size:14px}
.comment-date{font-size:12px;color:var(--text-mute)}
.comment-text{color:var(--text);font-size:14px;line-height:1.6}

.blog-grid{display:grid;grid-template-columns:200px 1fr 60px;gap:48px}
@media (max-width:992px){
  .blog-grid{grid-template-columns:1fr;gap:24px}
  .toc,.share-bar{position:static;align-self:auto}
  .share-bar{flex-direction:row;justify-content:center;margin:24px 0}
  .toc{margin-bottom:24px}
}
</style>

<section class="blog-hero">
  <div class="container">
    <a href="/blog.php" class="text-soft flex items-center gap-1 mb-2" style="font-size:13px">
      <?= icon('arrow-left', 14) ?> <?= t('blog') ?>
    </a>
    <?php if (!empty($post['category'])): ?>
      <span class="badge badge-info"><?= e($post['category']) ?></span>
    <?php endif; ?>
    <h1 style="margin:14px 0;line-height:1.2"><?= e($post['title_'.$lang_field]) ?></h1>
    <?php if (!empty($post['excerpt_'.$lang_field])): ?>
      <p style="font-size:18px;color:var(--text-soft);max-width:760px;line-height:var(--lh-relaxed)"><?= e($post['excerpt_'.$lang_field]) ?></p>
    <?php endif; ?>
    <div class="blog-meta">
      <span><?= icon('calendar', 14) ?> <?= date('d.m.Y', strtotime($post['created_at'])) ?></span>
      <span><?= icon('clock', 14) ?> <?= $readingTime ?> <?= t('min_read') ?></span>
      <span><?= icon('eye', 14) ?> <?= (int)$post['views'] ?> <?= lang()==='uz_cyrillic' ? "кўриш" : "ko'rish" ?></span>
      <span><?= icon('message', 14) ?> <?= count($comments) ?> <?= t('comments') ?></span>
    </div>
  </div>
</section>

<?php if (!empty($post['image'])): ?>
<div class="container" style="margin-top:24px">
  <img src="<?= e($post['image']) ?>" alt="<?= e($post['title_'.$lang_field]) ?>" style="width:100%;max-height:480px;object-fit:cover;border-radius:var(--r-xl)">
</div>
<?php endif; ?>

<section class="section-sm">
  <div class="container">
    <div class="blog-grid">
      <!-- TOC -->
      <aside class="toc" id="toc">
        <h4><?= lang()==='uz_cyrillic' ? "Мундарижа" : "Mundarija" ?></h4>
        <ul id="tocList">
          <li class="text-mute" style="font-size:13px"><?= lang()==='uz_cyrillic' ? "Юкланмоқда..." : "Yuklanmoqda..." ?></li>
        </ul>
      </aside>

      <!-- Content -->
      <article>
        <div class="blog-content" id="postContent">
          <?php
          // Demo content (real loyihada DB'dan keladi, oddiy formatlash)
          $body = $content ?: ($lang_field === 'cyrillic' ? '<p>Контент тез орада қўшилади...</p>' : '<p>Kontent tez orada qo\'shiladi...</p>');
          // Agar content oddiy text bo'lsa, paragraflar va H2 ga aylantiramiz
          if (strpos($body, '<p>') === false && strpos($body, '<h2>') === false) {
              // Demo: contentni formatga o'tkazamiz
              $sections = [
                  $lang_field === 'cyrillic' ? "Кириш" : "Kirish",
                  $lang_field === 'cyrillic' ? "Асосий маслаҳатлар" : "Asosiy maslahatlar",
                  $lang_field === 'cyrillic' ? "Амалий мисоллар" : "Amaliy misollar",
                  $lang_field === 'cyrillic' ? "Хулоса" : "Xulosa",
              ];
              echo "<p>".e($body)."</p>";
              foreach ($sections as $i => $s) {
                  echo "<h2 id='section-".($i+1)."'>".e($s)."</h2>";
                  echo "<p>".e($body)." Bu bo'lim quyidagi mavzularni ochib beradi va o'quvchiga to'liq ma'lumot beradi.</p>";
                  if ($i === 1) {
                      echo "<ul><li>Birinchi maslahat — eng muhim qoidalar</li><li>Ikkinchi maslahat — vaqt taqsimoti</li><li>Uchinchi maslahat — psixologik tayyorgarlik</li></ul>";
                  }
                  if ($i === 2) {
                      echo "<blockquote>Imtihondan oldingi kun yaxshi dam oling — bu sizga juda yordam beradi.</blockquote>";
                  }
              }
          } else {
              echo $body;
          }
          ?>
        </div>

        <!-- Tags / actions -->
        <div class="flex justify-between items-center flex-wrap gap-3 mt-4" style="padding:20px 0;border-top:1px solid var(--border);border-bottom:1px solid var(--border)">
          <div class="flex gap-2 flex-wrap">
            <?php
            $tags = $lang_field === 'cyrillic'
                ? ['Автомактаб','Имтиҳон','Маслаҳат']
                : ['Avtomaktab','Imtihon','Maslahat'];
            foreach ($tags as $tag): ?>
              <a href="/blog.php?q=<?= urlencode($tag) ?>" class="badge badge-mute" style="text-decoration:none">#<?= e($tag) ?></a>
            <?php endforeach; ?>
          </div>
          <div class="flex gap-2">
            <button class="btn btn-light btn-sm" onclick="sharePost('telegram')"><?= icon('telegram', 14) ?> Telegram</button>
            <button class="btn btn-light btn-sm" onclick="sharePost('facebook')"><?= icon('facebook', 14) ?> Facebook</button>
          </div>
        </div>

        <!-- Author card -->
        <div class="card mt-4" style="display:flex;gap:16px;align-items:center">
          <div class="review-avatar" style="width:56px;height:56px;font-size:20px;flex-shrink:0">VP</div>
          <div>
            <div style="font-weight:700"><?= e(setting('site_name')) ?></div>
            <div style="font-size:13px;color:var(--text-soft)"><?= lang()==='uz_cyrillic' ? "Маҳаллий автомактаб платформаси" : "Mahalliy avtomaktab platformasi" ?></div>
          </div>
          <a href="/aloqa.php" class="btn btn-outline btn-sm" style="margin-left:auto"><?= t('contact') ?></a>
        </div>

        <!-- Comments -->
        <div class="mt-5">
          <h2 style="font-size:24px;margin-bottom:18px;display:flex;align-items:center;gap:10px">
            <?= icon('message', 22) ?> <?= t('comments') ?> (<?= count($comments) ?>)
          </h2>

          <?php if ($msg): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
          <?php endif; ?>

          <?php if (is_logged_in()): ?>
          <form method="post" class="card mb-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="comment">
            <div class="form-group" style="margin-bottom:12px">
              <textarea name="text" class="form-control" rows="3" required minlength="5" maxlength="1000"
                placeholder="<?= lang()==='uz_cyrillic' ? 'Шарҳ ёзинг...' : 'Sharh yozing...' ?>"></textarea>
            </div>
            <button class="btn btn-primary"><?= t('leave_comment') ?></button>
          </form>
          <?php else: ?>
          <div class="card mb-3 text-center" style="background:var(--bg-soft)">
            <p class="mb-2"><?= lang()==='uz_cyrillic' ? "Шарҳ қолдириш учун рўйхатдан ўтинг" : "Sharh qoldirish uchun ro'yxatdan o'ting" ?></p>
            <a href="/login.php" class="btn btn-primary btn-sm"><?= t('login') ?></a>
          </div>
          <?php endif; ?>

          <div class="card" style="padding:0 24px">
            <?php if (empty($comments)): ?>
              <div class="empty-state" style="padding:30px">
                <?= icon('message', 48) ?>
                <p style="margin:14px 0 0"><?= lang()==='uz_cyrillic' ? "Шарҳлар йўқ. Биринчи бўлиб ёзинг!" : "Sharhlar yo'q. Birinchi bo'lib yozing!" ?></p>
              </div>
            <?php else: foreach (array_reverse($comments) as $c): ?>
            <div class="comment">
              <div class="comment-avatar"><?= e($c['avatar_letter'] ?? mb_substr($c['name'], 0, 1)) ?></div>
              <div class="comment-body">
                <div class="comment-head">
                  <span class="comment-name"><?= e($c['name']) ?></span>
                  <span class="comment-date"><?= date('d.m.Y H:i', strtotime($c['date'])) ?></span>
                </div>
                <div class="comment-text"><?= nl2br(e($c['text'])) ?></div>
              </div>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </article>

      <!-- Share bar -->
      <div class="share-bar">
        <button onclick="sharePost('telegram')" title="Telegram"><?= icon('telegram', 18) ?></button>
        <button onclick="sharePost('facebook')" title="Facebook"><?= icon('facebook', 18) ?></button>
        <button onclick="sharePost('twitter')" title="Twitter"><?= icon('send', 18) ?></button>
        <button onclick="copyLink(this)" title="Copy"><?= icon('logs', 18) ?></button>
      </div>
    </div>
  </div>
</section>

<!-- Related posts -->
<?php if (!empty($related)): ?>
<section class="section section-soft">
  <div class="container">
    <h2 style="text-align:center;margin-bottom:32px"><?= t('related_posts') ?></h2>
    <div class="grid-3 stagger">
      <?php foreach ($related as $r): ?>
      <a href="/blog-post.php?slug=<?= e($r['slug']) ?>" class="card card-hover" style="padding:0;overflow:hidden;text-decoration:none;color:inherit">
        <div style="aspect-ratio:16/9;background:linear-gradient(135deg,var(--primary-light),#fff);
                    display:flex;align-items:center;justify-content:center;font-size:48px;color:var(--primary)">
          <?php if (!empty($r['image'])): ?>
            <img src="<?= e($r['image']) ?>" style="width:100%;height:100%;object-fit:cover">
          <?php else: ?>📰<?php endif; ?>
        </div>
        <div style="padding:18px">
          <?php if (!empty($r['category'])): ?>
            <span class="badge badge-info"><?= e($r['category']) ?></span>
          <?php endif; ?>
          <h4 style="font-size:16px;font-weight:700;margin:8px 0;line-height:1.4"><?= e($r['title_'.$lang_field]) ?></h4>
          <div class="text-mute" style="font-size:12px"><?= date('d.m.Y', strtotime($r['created_at'])) ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<script>
// ====== TOC: avtomatik h2 dan generatsiya ======
(function(){
  const content = document.getElementById('postContent');
  const tocList = document.getElementById('tocList');
  const headings = content.querySelectorAll('h2, h3');
  if (!headings.length) {
    document.getElementById('toc').style.display = 'none';
    return;
  }
  tocList.innerHTML = '';
  headings.forEach((h, i) => {
    if (!h.id) h.id = 'heading-' + i;
    const li = document.createElement('li');
    if (h.tagName === 'H3') li.style.paddingLeft = '24px';
    const a = document.createElement('a');
    a.href = '#' + h.id;
    a.textContent = h.textContent;
    a.dataset.target = h.id;
    li.appendChild(a);
    tocList.appendChild(li);
  });
  // Active highlight
  const tocLinks = tocList.querySelectorAll('a');
  const onScroll = () => {
    let active = null;
    headings.forEach(h => {
      const top = h.getBoundingClientRect().top;
      if (top < 120) active = h.id;
    });
    tocLinks.forEach(a => a.classList.toggle('active', a.dataset.target === active));
  };
  window.addEventListener('scroll', onScroll, {passive:true});
  onScroll();
})();

// ====== Share buttons ======
function sharePost(platform){
  const url = encodeURIComponent(window.location.href);
  const title = encodeURIComponent(document.title);
  let shareUrl = '';
  if (platform === 'telegram') shareUrl = `https://t.me/share/url?url=${url}&text=${title}`;
  if (platform === 'facebook') shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
  if (platform === 'twitter')  shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
  if (shareUrl) window.open(shareUrl, '_blank', 'width=600,height=500');
}

function copyLink(btn){
  navigator.clipboard.writeText(window.location.href).then(() => {
    btn.classList.add('copied');
    btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
    setTimeout(() => {
      btn.classList.remove('copied');
      btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>';
    }, 2000);
    if (window.toast) toast('<?= lang()==='uz_cyrillic' ? "Ҳавола нусхаланди!" : "Havola nusxalandi!" ?>', 'success');
  });
}
</script>

<?php render_footer(); ?>
