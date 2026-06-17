<?php
require_once __DIR__ . '/includes/functions.php';

$tariffs = db()->fetchAll("SELECT * FROM tariffs WHERE status='active' ORDER BY sort_order ASC");
$reviews = db()->fetchAll("SELECT * FROM reviews WHERE status='approved' ORDER BY created_at DESC LIMIT 8");
$latest_posts = db()->fetchAll("SELECT * FROM blog_posts WHERE status='published' ORDER BY created_at DESC LIMIT 3");
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

render_head();
render_header('home');
?>

<!-- ============== HERO ============== -->
<section class="hero">
  <div class="container hero-grid">
    <div class="fade-up">
      <div class="eyebrow mb-2">
        <?= icon('zap', 14) ?>
        <span><?= lang()==='uz_cyrillic' ? '#1 Автомактаб платформаси' : '#1 Avtomaktab platformasi' ?></span>
      </div>
      <h1><?= t('hero_title') ?></h1>
      <p class="lead"><?= t('hero_subtitle') ?></p>
      <div class="flex gap-3 flex-wrap">
        <a href="/register.php" class="btn btn-primary btn-xl">
          <?= t('hero_cta') ?> <?= icon('arrow-right', 18) ?>
        </a>
        <a href="#how" class="btn btn-outline btn-xl">
          <?= icon('play', 16) ?> <?= lang()==='uz_cyrillic' ? "Қандай ишлайди" : "Qanday ishlaydi" ?>
        </a>
      </div>
      <div class="hero-stats">
        <div class="stat-box">
          <div class="stat-num" data-count="<?= (int)setting('hero_stats_users','5000') ?>">0</div>
          <div class="stat-label"><?= t('stats_users') ?></div>
        </div>
        <div class="stat-box">
          <div class="stat-num" data-count="<?= (int)setting('hero_stats_questions','3000') ?>">0</div>
          <div class="stat-label"><?= t('stats_questions') ?></div>
        </div>
        <div class="stat-box">
          <div class="stat-num"><span data-count="<?= (int)setting('hero_stats_success','98') ?>">0</span>%</div>
          <div class="stat-label"><?= t('stats_success') ?></div>
        </div>
      </div>
    </div>

    <!-- Banner — faqat desktop/tablet'da -->
    <div class="hero-banner-wrap fade-up">
      <?php $banner_url = setting('site_banner'); ?>
      <?php if ($banner_url && $banner_url !== '/assets/images/banner.svg'): ?>
        <div class="hero-banner">
          <img src="<?= e($banner_url) ?>" alt="" loading="eager">
          <div class="hero-banner-shine"></div>
        </div>
      <?php else: ?>
        <div class="hero-image">🚗</div>
      <?php endif; ?>
    </div>
  </div>
</section>

<style>
.hero-banner-wrap{display:flex;justify-content:center;align-items:center}
.hero-banner{position:relative;border-radius:32px;overflow:hidden;box-shadow:var(--shadow-primary-lg);
  transform:rotate(-1.5deg);transition:transform .5s var(--ease-out);width:100%;max-width:500px;aspect-ratio:1}
.hero-banner:hover{transform:rotate(0) scale(1.02)}
.hero-banner img{width:100%;height:100%;object-fit:cover;display:block}
.hero-banner-shine{position:absolute;top:0;left:-100%;width:60%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.3),transparent);
  animation:shine 3s ease-in-out infinite;animation-delay:1s}
@keyframes shine{0%,100%{left:-60%}50%{left:120%}}

/* Banner faqat desktop/tablet'da */
@media(max-width:720px){
  .hero-banner-wrap{display:none !important}
  .hero{padding:48px 0 32px}
  .hero-grid{grid-template-columns:1fr;text-align:center}
  .hero-grid .fade-up > .flex{justify-content:center}
}
</style>

<!-- ============== TRUSTED BY (logo wall - placeholder) ============== -->
<section style="padding:32px 0;background:#fff;border-bottom:1px solid var(--border)">
  <div class="container">
    <div class="text-center text-soft mb-2" style="font-size:13px;font-weight:600;letter-spacing:.1em;text-transform:uppercase">
      <?= lang()==='uz_cyrillic' ? "5000+ ўқувчи бизга ишонади" : "5000+ o'quvchi bizga ishonadi" ?>
    </div>
    <div class="flex gap-4 flex-wrap justify-center" style="opacity:.5;font-size:24px;font-weight:800;color:var(--text-soft);padding:14px 0">
      <span>YO'L · POLIZIYA</span>
      <span>·</span>
      <span>BVB</span>
      <span>·</span>
      <span>FERGANA</span>
      <span>·</span>
      <span>YAYPAN AVTO</span>
    </div>
  </div>
</section>

<!-- ============== XIZMATLAR ============== -->
<section class="section">
  <div class="container">
    <div class="eyebrow text-center"><?= lang()==='uz_cyrillic' ? "Хизматлар" : "Xizmatlar" ?></div>
    <h2 class="section-title"><?= t('services') ?></h2>
    <p class="section-subtitle"><?= t('services_subtitle') ?></p>
    <div class="grid-3 stagger">
      <div class="card card-hover service-card">
        <div class="icon-circle"><?= icon('document', 32) ?></div>
        <h3><?= t('service_test') ?></h3>
        <p><?= t('service_test_d') ?></p>
      </div>
      <div class="card card-hover service-card">
        <div class="icon-circle"><?= icon('chart', 32) ?></div>
        <h3><?= t('service_stats') ?></h3>
        <p><?= t('service_stats_d') ?></p>
      </div>
      <div class="card card-hover service-card">
        <div class="icon-circle"><?= icon('trophy', 32) ?></div>
        <h3><?= t('service_result') ?></h3>
        <p><?= t('service_result_d') ?></p>
      </div>
    </div>
  </div>
</section>

<!-- ============== QANDAY ISHLAYDI ============== -->
<section class="section section-soft" id="how">
  <div class="container">
    <div class="eyebrow text-center"><?= lang()==='uz_cyrillic' ? "Жараён" : "Jarayon" ?></div>
    <h2 class="section-title"><?= t('how_works') ?></h2>
    <p class="section-subtitle"><?= t('how_works_d') ?></p>
    <div class="grid-4 stagger">
      <?php foreach ([1,2,3,4] as $i): ?>
      <div class="card card-hover step-card">
        <div class="step-num"><?= $i ?></div>
        <h3 style="font-size:17px;font-weight:700;margin-bottom:6px;margin-top:8px"><?= t('step_'.$i) ?></h3>
        <p style="color:var(--text-soft);font-size:14px"><?= t('step_'.$i.'_d') ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============== VIDEO BO'LIM ============== -->
<section class="section">
  <div class="container">
    <div class="eyebrow text-center">Video</div>
    <h2 class="section-title"><?= lang()==='uz_cyrillic' ? "Видео дарслар" : "Video darslar" ?></h2>
    <p class="section-subtitle"><?= lang()==='uz_cyrillic' ? "Тажрибали ўқитувчиларимиздан фойдали маслаҳатлар" : "Tajribali o'qituvchilarimizdan foydali maslahatlar" ?></p>
    <div class="grid-3 stagger">
      <?php
      $videos = [
        ['t'=>['Yo\'l belgilarini yodlash', 'Йўл белгиларини ёдлаш'], 'd'=>'12:30', 'cat'=>'Dars'],
        ['t'=>['Imtihondan o\'tish maslahati', 'Имтиҳондан ўтиш маслаҳати'],'d'=>'08:45','cat'=>'Tavsiya'],
        ['t'=>['Eng ko\'p uchraydigan xatolar', 'Энг кўп учрайдиган хатолар'],'d'=>'15:20','cat'=>'Dars'],
      ];
      foreach ($videos as $v):
      ?>
      <div class="card card-hover" style="padding:0;overflow:hidden;cursor:pointer" onclick="alert('Video tez orada qo\'shiladi')">
        <div style="aspect-ratio:16/9;background:linear-gradient(135deg,var(--primary),var(--secondary));position:relative;display:flex;align-items:center;justify-content:center">
          <div style="width:64px;height:64px;border-radius:50%;background:rgba(255,255,255,.95);display:flex;align-items:center;justify-content:center;color:var(--primary)">
            <?= icon('play', 28) ?>
          </div>
          <span class="badge badge-mute" style="position:absolute;bottom:10px;right:10px;background:rgba(0,0,0,.6);color:#fff;border:none"><?= $v['d'] ?></span>
        </div>
        <div style="padding:18px">
          <span class="badge badge-info"><?= $v['cat'] ?></span>
          <h4 style="font-size:16px;font-weight:700;margin-top:10px"><?= e(lang()==='uz_cyrillic' ? $v['t'][1] : $v['t'][0]) ?></h4>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============== TARIFLAR ============== -->
<section class="section section-soft" id="tariflar">
  <div class="container">
    <div class="eyebrow text-center"><?= t('tariffs') ?></div>
    <h2 class="section-title"><?= lang()==='uz_cyrillic' ? "Сизга мос нархлар" : "Sizga mos narxlar" ?></h2>
    <p class="section-subtitle"><?= lang()==='uz_cyrillic' ? "Бошланг бепул, керак бўлганда юқори тарифга ўтинг" : "Boshlang bepul, kerak bo'lganda yuqori tarifga o'ting" ?></p>
    <div class="grid-3">
      <?php foreach ($tariffs as $tariff):
        $features = explode('|', $tariff['features_'.$lang_field] ?? '');
      ?>
      <div class="card pricing-card <?= $tariff['is_popular']?'popular':'' ?> fade-up">
        <?php if ($tariff['is_popular']): ?>
          <div class="pricing-badge"><?= t('popular') ?></div>
        <?php endif; ?>
        <h3><?= e($tariff['name_'.$lang_field]) ?></h3>
        <p class="pricing-desc"><?= e($tariff['description_'.$lang_field]) ?></p>
        <div class="pricing-price">
          <?php if ($tariff['price'] == 0): ?>
            <?= t('free') ?>
          <?php else: ?>
            <?= money($tariff['price']) ?> <small><?= t('soum') ?> <?= t('per_month') ?></small>
          <?php endif; ?>
        </div>
        <ul class="pricing-features">
          <?php foreach ($features as $f): if (trim($f)) echo '<li>'.e(trim($f)).'</li>'; endforeach; ?>
        </ul>
        <a href="/tariflar.php" class="btn <?= $tariff['is_popular']?'btn-primary':'btn-outline' ?> btn-block btn-lg"><?= t('choose_plan') ?></a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============== SHARHLAR (carousel) ============== -->
<section class="section">
  <div class="container">
    <div class="eyebrow text-center"><?= t('reviews_title') ?></div>
    <h2 class="section-title"><?= lang()==='uz_cyrillic' ? "Сиз каби ўқувчилар нима дейишади" : "Siz kabi o'quvchilar nima deyishadi" ?></h2>
    <p class="section-subtitle"><?= t('reviews_d') ?></p>

    <div class="reviews-carousel" id="reviewsCarousel" style="position:relative">
      <div class="carousel-track" style="display:flex;gap:24px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;padding:8px 0;scrollbar-width:none;-ms-overflow-style:none">
        <?php foreach ($reviews as $r): ?>
        <div class="review-card" style="flex:0 0 calc(33.33% - 16px);scroll-snap-align:start;min-width:300px">
          <div class="review-stars">
            <?php for ($i=0; $i<(int)$r['rating']; $i++) echo icon('star', 16); ?>
          </div>
          <p class="review-text"><?= e($r['text']) ?></p>
          <div class="review-author">
            <div class="review-avatar"><?= mb_substr(e($r['name']), 0, 1) ?></div>
            <div>
              <div style="font-weight:600;font-size:14px"><?= e($r['name']) ?></div>
              <div style="font-size:12px;color:var(--text-mute)"><?= date('d.m.Y', strtotime($r['created_at'])) ?></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-icon btn-light" onclick="scrollReviews(-1)" style="position:absolute;left:-18px;top:50%;transform:translateY(-50%);box-shadow:var(--shadow-md);background:#fff" aria-label="Previous">
        <?= icon('chevron-left', 18) ?>
      </button>
      <button class="btn btn-icon btn-light" onclick="scrollReviews(1)" style="position:absolute;right:-18px;top:50%;transform:translateY(-50%);box-shadow:var(--shadow-md);background:#fff" aria-label="Next">
        <?= icon('chevron-right', 18) ?>
      </button>
    </div>
  </div>
</section>

<!-- ============== BLOG'DAN ============== -->
<?php if (!empty($latest_posts)): ?>
<section class="section section-soft">
  <div class="container">
    <div class="flex justify-between items-center mb-3 flex-wrap gap-3">
      <div>
        <div class="eyebrow"><?= t('blog') ?></div>
        <h2 style="margin-top:8px"><?= lang()==='uz_cyrillic' ? "Сўнгги мақолалар" : "So'nggi maqolalar" ?></h2>
      </div>
      <a href="/blog.php" class="btn btn-outline">
        <?= t('view_all') ?> <?= icon('arrow-right', 14) ?>
      </a>
    </div>
    <div class="grid-3 stagger">
      <?php foreach ($latest_posts as $p): ?>
      <article class="card card-hover" style="padding:0;overflow:hidden">
        <div style="aspect-ratio:16/9;background:linear-gradient(135deg,var(--primary-light),#fff);
                    display:flex;align-items:center;justify-content:center;font-size:48px;color:var(--primary)">
          <?php if (!empty($p['image'])): ?>
            <img src="<?= e($p['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
          <?php else: ?>📰<?php endif; ?>
        </div>
        <div style="padding:20px">
          <?php if (!empty($p['category'])): ?>
            <span class="badge badge-info"><?= e($p['category']) ?></span>
          <?php endif; ?>
          <h3 style="font-size:17px;font-weight:700;margin:10px 0;line-height:1.4"><?= e($p['title_'.$lang_field]) ?></h3>
          <p style="color:var(--text-soft);font-size:14px;margin-bottom:14px"><?= e(mb_substr($p['excerpt_'.$lang_field], 0, 100)) ?>...</p>
          <a href="/blog-post.php?slug=<?= e($p['slug']) ?>" class="text-primary font-semibold flex items-center gap-1" style="font-size:14px">
            <?= t('read_more') ?> <?= icon('arrow-right', 14) ?>
          </a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============== CTA ============== -->
<section class="section" style="background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);color:#fff;position:relative;overflow:hidden">
  <div style="position:absolute;inset:0;background:url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='60' height='60' viewBox='0 0 60 60'><circle fill='rgba(255,255,255,.05)' cx='30' cy='30' r='2'/></svg>\")"></div>
  <div class="container text-center" style="position:relative">
    <h2 style="font-size:42px;font-weight:800;margin-bottom:16px;color:#fff"><?= t('cta_title') ?></h2>
    <p style="opacity:.95;margin-bottom:32px;font-size:18px;max-width:560px;margin-left:auto;margin-right:auto"><?= t('cta_d') ?></p>
    <div class="flex justify-center gap-3 flex-wrap">
      <a href="/register.php" class="btn btn-xl" style="background:#fff;color:var(--primary)">
        <?= t('hero_cta') ?> <?= icon('arrow-right', 18) ?>
      </a>
      <a href="/tariflar.php" class="btn btn-xl btn-outline" style="border-color:rgba(255,255,255,.4);color:#fff">
        <?= t('tariffs') ?>
      </a>
    </div>
  </div>
</section>

<!-- Sticky CTA mobile -->
<div id="stickyCta" style="display:none;position:fixed;bottom:16px;left:16px;right:16px;z-index:90;
  background:#fff;border-radius:14px;padding:12px 14px;box-shadow:0 -8px 30px rgba(15,23,42,.15);
  border:1px solid var(--border);align-items:center;gap:10px;animation:fadeUp .4s">
  <div style="flex:1">
    <div style="font-weight:700;font-size:13px"><?= lang()==='uz_cyrillic' ? "Бепул бошланг" : "Bepul boshlang" ?></div>
    <div style="font-size:11px;color:var(--text-soft)"><?= lang()==='uz_cyrillic' ? "Кредит карта талаб қилинмайди" : "Kredit karta talab qilinmaydi" ?></div>
  </div>
  <a href="/register.php" class="btn btn-primary btn-sm"><?= t('register') ?></a>
  <button onclick="this.parentElement.style.display='none'" style="background:none;border:none;color:var(--text-mute);font-size:18px">&times;</button>
</div>

<script>
// Reviews carousel
function scrollReviews(dir){
  const track = document.querySelector('#reviewsCarousel .carousel-track');
  if (!track) return;
  const card = track.querySelector('.review-card');
  if (!card) return;
  const cardWidth = card.offsetWidth + 24;
  track.scrollBy({left: dir * cardWidth, behavior: 'smooth'});
}
// Auto-scroll
let autoScrollTimer = setInterval(() => scrollReviews(1), 5000);
const carouselTrack = document.querySelector('#reviewsCarousel .carousel-track');
if (carouselTrack) {
  carouselTrack.addEventListener('mouseenter', () => clearInterval(autoScrollTimer));
  // Hide scrollbar
  const style = document.createElement('style');
  style.textContent = '.carousel-track::-webkit-scrollbar{display:none}';
  document.head.appendChild(style);
}

// Sticky CTA — mobile (faqat skroll qilingandan keyin)
if (window.innerWidth <= 720) {
  let shown = false;
  window.addEventListener('scroll', () => {
    if (!shown && window.scrollY > 600) {
      document.getElementById('stickyCta').style.display = 'flex';
      shown = true;
    }
  });
}
</script>

<?php
render_footer();
