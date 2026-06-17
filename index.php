<?php
require_once __DIR__ . '/includes/functions.php';

$tariffs = db()->fetchAll("SELECT * FROM tariffs WHERE status='active' ORDER BY sort_order ASC");
$reviews = db()->fetchAll("SELECT * FROM reviews WHERE status='approved' ORDER BY created_at DESC LIMIT 8");
$latest_posts = db()->fetchAll("SELECT * FROM blog_posts WHERE status='published' ORDER BY created_at DESC LIMIT 3");
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

// Stat numbers
$total_users = (int)setting('hero_stats_users', '5000');
$total_questions = (int)setting('hero_stats_questions', '3000');
$success_rate = (int)setting('hero_stats_success', '98');

// Demo testimonials if empty
if (empty($reviews)) {
    $reviews = [
        ['name'=>'Akmal R.', 'text'=>'Juda zo\'r platforma! Birinchi marta imtihondan o\'tdim.', 'rating'=>5, 'created_at'=>date('Y-m-d')],
        ['name'=>'Dilnoza K.', 'text'=>'Savollar to\'liq va tushunarli. Tavsiya qilaman!', 'rating'=>5, 'created_at'=>date('Y-m-d')],
        ['name'=>'Bekzod U.', 'text'=>'Reyting tizimi qiziqarli. Zavqlanib o\'rganaman.', 'rating'=>5, 'created_at'=>date('Y-m-d')],
    ];
}

render_head();
render_header('home');
?>

<!-- ============== HERO ============== -->
<section class="hero hero-v2">
  <!-- Animated background -->
  <div class="hero-bg-shapes">
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>
    <div class="shape shape-3"></div>
  </div>

  <div class="container hero-grid">
    <div class="hero-content">
      <div class="hero-eyebrow">
        <span class="eyebrow-dot"></span>
        <span><?= lang()==='uz_cyrillic' ? 'Янги ўзбекона тажриба' : 'Yangi o\'zbekona tajriba' ?></span>
        <span class="eyebrow-tag">v2.0</span>
      </div>

      <h1 class="hero-title">
        <?= t('hero_title') ?>
      </h1>

      <p class="hero-subtitle"><?= t('hero_subtitle') ?></p>

      <div class="hero-actions">
        <a href="/register.php" class="btn-cta-primary">
          <span><?= t('hero_cta') ?></span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <line x1="5" y1="12" x2="19" y2="12"/>
            <polyline points="12 5 19 12 12 19"/>
          </svg>
        </a>
        <a href="#how" class="btn-cta-ghost">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
            <polygon points="5 3 19 12 5 21 5 3"/>
          </svg>
          <span><?= lang()==='uz_cyrillic' ? "Видеони кўриш" : "Videoni ko'rish" ?></span>
        </a>
      </div>

      <!-- Trust indicators -->
      <div class="hero-trust">
        <div class="trust-avatars">
          <span class="trust-avatar" style="background:#3B82F6">A</span>
          <span class="trust-avatar" style="background:#10B981">D</span>
          <span class="trust-avatar" style="background:#F59E0B">B</span>
          <span class="trust-avatar" style="background:#8B5CF6">M</span>
          <span class="trust-avatar" style="background:#EC4899">+</span>
        </div>
        <div class="trust-text">
          <div class="trust-stars">★★★★★ <span>4.9</span></div>
          <div class="trust-label"><?= number_format($total_users) ?>+ <?= lang()==='uz_cyrillic' ? "ўқувчи бизга ишонади" : "o'quvchi bizga ishonadi" ?></div>
        </div>
      </div>
    </div>

    <!-- Hero visual -->
    <div class="hero-visual">
      <?php $banner_url = setting('site_banner'); ?>
      <?php if ($banner_url && !str_ends_with($banner_url, 'banner.svg')): ?>
        <div class="hero-banner-card">
          <img src="<?= e($banner_url) ?>" alt="" loading="eager">
        </div>
      <?php else: ?>
        <!-- Floating mockup -->
        <div class="hero-mockup">
          <!-- Phone screen mock -->
          <div class="mockup-card mockup-main">
            <div class="mockup-header">
              <div class="mockup-dot red"></div>
              <div class="mockup-dot yellow"></div>
              <div class="mockup-dot green"></div>
              <div class="mockup-title">Test #1 · S5/20</div>
            </div>
            <div class="mockup-body">
              <div class="mockup-progress"><div class="mockup-progress-bar"></div></div>
              <div class="mockup-question">
                <div class="mockup-q-label">SAVOL</div>
                <div class="mockup-q-text"><?= lang()==='uz_cyrillic' ? "Светофорнинг яшил ранги нимани англатади?" : "Svetoforning yashil rangi nimani anglatadi?" ?></div>
              </div>
              <div class="mockup-answers">
                <div class="mockup-answer"><span class="mockup-letter">A</span> Diqqat</div>
                <div class="mockup-answer correct"><span class="mockup-letter">B</span> Harakat ruxsat</div>
                <div class="mockup-answer"><span class="mockup-letter">C</span> To'xtash</div>
                <div class="mockup-answer"><span class="mockup-letter">D</span> Ortga qaytish</div>
              </div>
            </div>
          </div>

          <!-- Floating cards around -->
          <div class="floating-card float-1">
            <div class="float-icon" style="background:#D1FAE5;color:#065F46">✓</div>
            <div>
              <div class="float-title"><?= lang()==='uz_cyrillic' ? "Тест муваффақиятли" : "Test muvaffaqiyatli" ?></div>
              <div class="float-meta">95% to'g'ri</div>
            </div>
          </div>

          <div class="floating-card float-2">
            <div class="float-icon" style="background:#DBEAFE;color:#1E40AF">📊</div>
            <div>
              <div class="float-title">+12 ball</div>
              <div class="float-meta">Bu hafta</div>
            </div>
          </div>

          <div class="floating-card float-3">
            <div class="float-icon" style="background:#FEF3C7;color:#92400E">🔥</div>
            <div>
              <div class="float-title">7 kun streak</div>
              <div class="float-meta">Davom eting!</div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Stats strip -->
  <div class="container hero-stats-v2">
    <div class="stat-pill">
      <div class="stat-num" data-count="<?= $total_users ?>">0</div>
      <div class="stat-label"><?= t('stats_users') ?></div>
    </div>
    <div class="stat-divider"></div>
    <div class="stat-pill">
      <div class="stat-num" data-count="<?= $total_questions ?>">0</div>
      <div class="stat-label"><?= t('stats_questions') ?></div>
    </div>
    <div class="stat-divider"></div>
    <div class="stat-pill">
      <div class="stat-num"><span data-count="<?= $success_rate ?>">0</span>%</div>
      <div class="stat-label"><?= t('stats_success') ?></div>
    </div>
    <div class="stat-divider"></div>
    <div class="stat-pill">
      <div class="stat-num">24/7</div>
      <div class="stat-label"><?= lang()==='uz_cyrillic' ? "Кириш мумкин" : "Kirish mumkin" ?></div>
    </div>
  </div>
</section>

<!-- ============== FEATURES ============== -->
<section class="section section-features">
  <div class="container">
    <div class="section-head reveal-on-scroll">
      <div class="eyebrow"><?= lang()==='uz_cyrillic' ? "Хусусиятлар" : "Xususiyatlar" ?></div>
      <h2 class="section-title-v2"><?= t('services') ?></h2>
      <p class="section-sub"><?= t('services_subtitle') ?></p>
    </div>

    <div class="features-grid">
      <div class="feature-card reveal-on-scroll" style="--accent:#3B82F6">
        <div class="feature-icon-wrap">
          <div class="feature-icon-bg"></div>
          <?= icon('document', 32) ?>
        </div>
        <h3><?= t('service_test') ?></h3>
        <p><?= t('service_test_d') ?></p>
        <a href="/register.php" class="feature-link">
          <?= lang()==='uz_cyrillic' ? "Бошлаш" : "Boshlash" ?>
          <?= icon('arrow-right', 14) ?>
        </a>
      </div>

      <div class="feature-card reveal-on-scroll" style="--accent:#10B981">
        <div class="feature-icon-wrap">
          <div class="feature-icon-bg"></div>
          <?= icon('chart', 32) ?>
        </div>
        <h3><?= t('service_stats') ?></h3>
        <p><?= t('service_stats_d') ?></p>
        <a href="/register.php" class="feature-link">
          <?= lang()==='uz_cyrillic' ? "Кўриш" : "Ko'rish" ?>
          <?= icon('arrow-right', 14) ?>
        </a>
      </div>

      <div class="feature-card reveal-on-scroll" style="--accent:#F59E0B">
        <div class="feature-icon-wrap">
          <div class="feature-icon-bg"></div>
          <?= icon('trophy', 32) ?>
        </div>
        <h3><?= t('service_result') ?></h3>
        <p><?= t('service_result_d') ?></p>
        <a href="/register.php" class="feature-link">
          <?= lang()==='uz_cyrillic' ? "Қўшилиш" : "Qo'shilish" ?>
          <?= icon('arrow-right', 14) ?>
        </a>
      </div>
    </div>
  </div>
</section>

<!-- ============== HOW IT WORKS ============== -->
<section class="section section-soft" id="how">
  <div class="container">
    <div class="section-head reveal-on-scroll">
      <div class="eyebrow"><?= lang()==='uz_cyrillic' ? "Жараён" : "Jarayon" ?></div>
      <h2 class="section-title-v2"><?= t('how_works') ?></h2>
      <p class="section-sub"><?= t('how_works_d') ?></p>
    </div>

    <div class="steps-timeline">
      <?php foreach ([1,2,3,4] as $i):
        $icons = ['user','gem','document','trophy'];
        $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444'];
      ?>
      <div class="step-item reveal-on-scroll" style="--step-color:<?= $colors[$i-1] ?>">
        <div class="step-bubble">
          <div class="step-bubble-num"><?= $i ?></div>
          <div class="step-bubble-icon"><?= icon($icons[$i-1], 22) ?></div>
        </div>
        <div class="step-content">
          <h3><?= t('step_'.$i) ?></h3>
          <p><?= t('step_'.$i.'_d') ?></p>
        </div>
        <?php if ($i < 4): ?>
          <div class="step-connector"></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============== TARIFLAR ============== -->
<section class="section" id="tariflar">
  <div class="container">
    <div class="section-head reveal-on-scroll">
      <div class="eyebrow"><?= t('tariffs') ?></div>
      <h2 class="section-title-v2"><?= lang()==='uz_cyrillic' ? "Сизга мос нархлар" : "Sizga mos narxlar" ?></h2>
      <p class="section-sub"><?= lang()==='uz_cyrillic' ? "Бошланг бепул, керак бўлганда юқори тарифга ўтинг" : "Boshlang bepul, kerak bo'lganda yuqori tarifga o'ting" ?></p>
    </div>

    <div class="pricing-grid">
      <?php foreach ($tariffs as $idx => $tariff):
        $features = explode('|', $tariff['features_'.$lang_field] ?? '');
      ?>
      <div class="pricing-v2 <?= $tariff['is_popular']?'is-popular':'' ?> reveal-on-scroll" style="--idx:<?= $idx ?>">
        <?php if ($tariff['is_popular']): ?>
          <div class="pricing-glow"></div>
          <div class="pricing-popular-badge">
            ⭐ <?= t('popular') ?>
          </div>
        <?php endif; ?>

        <div class="pricing-header">
          <h3><?= e($tariff['name_'.$lang_field]) ?></h3>
          <p><?= e($tariff['description_'.$lang_field]) ?></p>
        </div>

        <div class="pricing-price-v2">
          <?php if ($tariff['price'] == 0): ?>
            <span class="price-free"><?= t('free') ?></span>
          <?php else: ?>
            <span class="price-currency">UZS</span>
            <span class="price-value"><?= money($tariff['price']) ?></span>
            <span class="price-period">/<?= lang()==='uz_cyrillic' ? "ой" : "oy" ?></span>
          <?php endif; ?>
        </div>

        <ul class="pricing-features-v2">
          <?php foreach ($features as $f): if (trim($f)): ?>
            <li>
              <span class="feature-check">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
              </span>
              <span><?= e(trim($f)) ?></span>
            </li>
          <?php endif; endforeach; ?>
        </ul>

        <a href="/tariflar.php"
           class="pricing-cta <?= $tariff['is_popular']?'is-primary':'is-outline' ?>">
          <?= t('choose_plan') ?>
          <?= icon('arrow-right', 16) ?>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============== REVIEWS CAROUSEL ============== -->
<section class="section section-soft">
  <div class="container">
    <div class="section-head reveal-on-scroll">
      <div class="eyebrow"><?= t('reviews_title') ?></div>
      <h2 class="section-title-v2"><?= lang()==='uz_cyrillic' ? "Сиз каби ўқувчилар" : "Siz kabi o'quvchilar" ?></h2>
      <p class="section-sub"><?= t('reviews_d') ?></p>
    </div>

    <div class="testimonials-wrap">
      <button class="testimonial-arrow prev" onclick="scrollTestimonials(-1)" aria-label="Previous">
        <?= icon('chevron-left', 20) ?>
      </button>

      <div class="testimonials-track" id="testimonialsTrack">
        <?php foreach ($reviews as $r): ?>
        <div class="testimonial-card">
          <div class="testimonial-quote">"</div>
          <div class="testimonial-stars">
            <?php for ($i = 0; $i < (int)$r['rating']; $i++): ?>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="#FBBF24" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <?php endfor; ?>
          </div>
          <p class="testimonial-text"><?= e($r['text']) ?></p>
          <div class="testimonial-author">
            <div class="testimonial-avatar"><?= mb_substr(e($r['name']), 0, 1) ?></div>
            <div>
              <div class="testimonial-name"><?= e($r['name']) ?></div>
              <div class="testimonial-meta"><?= date('d.m.Y', strtotime($r['created_at'])) ?></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <button class="testimonial-arrow next" onclick="scrollTestimonials(1)" aria-label="Next">
        <?= icon('chevron-right', 20) ?>
      </button>
    </div>

    <!-- Dot indicators -->
    <div class="testimonials-dots" id="testimonialsDots"></div>
  </div>
</section>

<!-- ============== BLOG ============== -->
<?php if (!empty($latest_posts)): ?>
<section class="section">
  <div class="container">
    <div class="flex justify-between items-end mb-4 flex-wrap gap-2 reveal-on-scroll">
      <div>
        <div class="eyebrow"><?= t('blog') ?></div>
        <h2 class="section-title-v2" style="margin-top:6px"><?= lang()==='uz_cyrillic' ? "Сўнгги мақолалар" : "So'nggi maqolalar" ?></h2>
      </div>
      <a href="/blog.php" class="btn-link"><?= t('view_all') ?> <?= icon('arrow-right', 14) ?></a>
    </div>

    <div class="blog-grid-v2">
      <?php foreach ($latest_posts as $idx => $p): ?>
      <a href="/blog-post.php?slug=<?= e($p['slug']) ?>" class="blog-card-v2 reveal-on-scroll" style="--idx:<?= $idx ?>">
        <div class="blog-card-image">
          <?php if (!empty($p['image'])): ?>
            <img src="<?= e($p['image']) ?>" alt="" loading="lazy">
          <?php else: ?>
            <div class="blog-card-placeholder">📰</div>
          <?php endif; ?>
          <?php if (!empty($p['category'])): ?>
            <span class="blog-card-category"><?= e($p['category']) ?></span>
          <?php endif; ?>
        </div>
        <div class="blog-card-content">
          <h3><?= e($p['title_'.$lang_field]) ?></h3>
          <p><?= e(mb_substr($p['excerpt_'.$lang_field], 0, 100)) ?>...</p>
          <div class="blog-card-meta">
            <span><?= icon('calendar', 12) ?> <?= date('d.m.Y', strtotime($p['created_at'])) ?></span>
            <span class="blog-card-arrow"><?= icon('arrow-right', 14) ?></span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============== CTA ============== -->
<section class="cta-section">
  <div class="container">
    <div class="cta-card reveal-on-scroll">
      <div class="cta-bg-pattern"></div>
      <div class="cta-content">
        <div class="cta-eyebrow">
          <span class="dot-pulse"></span>
          <?= lang()==='uz_cyrillic' ? "Бугуноқ бошланг" : "Bugunoq boshlang" ?>
        </div>
        <h2 class="cta-title"><?= t('cta_title') ?></h2>
        <p class="cta-subtitle"><?= t('cta_d') ?></p>
        <div class="cta-actions">
          <a href="/register.php" class="btn-cta-white">
            <?= t('hero_cta') ?>
            <?= icon('arrow-right', 18) ?>
          </a>
          <a href="/tariflar.php" class="btn-cta-outline-white">
            <?= t('tariffs') ?>
          </a>
        </div>

        <div class="cta-features">
          <span><?= icon('check-circle', 16) ?> <?= lang()==='uz_cyrillic' ? "Кредит карта талаб қилинмайди" : "Kredit karta talab qilinmaydi" ?></span>
          <span><?= icon('check-circle', 16) ?> <?= lang()==='uz_cyrillic' ? "Дарҳол кириш" : "Darhol kirish" ?></span>
          <span><?= icon('check-circle', 16) ?> <?= lang()==='uz_cyrillic' ? "24/7 қўллаб-қувватлаш" : "24/7 qo'llab-quvvatlash" ?></span>
        </div>
      </div>
    </div>
  </div>
</section>

<style>
/* ============================================================
   HERO V2 — Premium dizayn
   ============================================================ */
.hero-v2{position:relative;padding:80px 0 0;background:#fff;overflow:hidden;min-height:auto}
.hero-v2::before{display:none}
.hero-v2::after{display:none}

.hero-bg-shapes{position:absolute;inset:0;overflow:hidden;pointer-events:none;z-index:0}
.shape{position:absolute;border-radius:50%;filter:blur(80px);opacity:.6;animation:floatShape 20s ease-in-out infinite}
.shape-1{width:600px;height:600px;background:radial-gradient(circle,#DBEAFE,transparent);top:-200px;right:-100px;animation-duration:25s}
.shape-2{width:400px;height:400px;background:radial-gradient(circle,#E0E7FF,transparent);bottom:-100px;left:-50px;animation-duration:18s;animation-direction:reverse}
.shape-3{width:300px;height:300px;background:radial-gradient(circle,#FCE7F3,transparent);top:30%;left:50%;animation-duration:22s}
@keyframes floatShape{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(40px,-40px) scale(1.1)}}

.hero-grid{display:grid;grid-template-columns:1.05fr 1fr;gap:60px;align-items:center;position:relative;z-index:1;padding:24px 20px 64px}
.hero-content{animation:slideUpFade .9s var(--ease-soft) both}
@keyframes slideUpFade{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}

.hero-eyebrow{display:inline-flex;align-items:center;gap:8px;padding:6px 14px;background:linear-gradient(135deg,#EFF6FF,#DBEAFE);
  border:1px solid rgba(59,130,246,.2);border-radius:100px;font-size:13px;font-weight:600;color:var(--primary-700);margin-bottom:24px}
.eyebrow-dot{width:6px;height:6px;border-radius:50%;background:var(--success);box-shadow:0 0 0 3px rgba(16,185,129,.2);
  animation:dotPulse 2s ease-in-out infinite}
@keyframes dotPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.3)}}
.eyebrow-tag{padding:2px 8px;background:#fff;border-radius:8px;color:var(--primary);font-size:11px;font-weight:700}

.hero-title{font-size:clamp(32px, 5vw, 56px);font-weight:900;line-height:1.05;letter-spacing:-.025em;margin-bottom:20px;color:var(--text)}

.hero-subtitle{font-size:clamp(15px, 1.4vw, 18px);color:var(--text-soft);line-height:1.65;margin-bottom:32px;max-width:520px}

.hero-actions{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:36px}

.btn-cta-primary{display:inline-flex;align-items:center;gap:10px;padding:16px 28px;
  background:linear-gradient(135deg,#3B82F6,#2563EB);color:#fff;border-radius:14px;font-weight:700;font-size:15px;
  text-decoration:none;transition:all .3s var(--ease-soft);box-shadow:0 12px 28px rgba(59,130,246,.35);position:relative;overflow:hidden}
.btn-cta-primary::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.3),transparent);transition:left .6s}
.btn-cta-primary:hover{transform:translateY(-3px);box-shadow:0 20px 40px rgba(59,130,246,.45);color:#fff}
.btn-cta-primary:hover::before{left:100%}
.btn-cta-primary svg{transition:transform .25s var(--ease-back)}
.btn-cta-primary:hover svg{transform:translateX(4px)}

.btn-cta-ghost{display:inline-flex;align-items:center;gap:10px;padding:16px 24px;
  background:#fff;color:var(--text);border:1.5px solid var(--border);border-radius:14px;font-weight:600;font-size:15px;
  text-decoration:none;transition:all .25s var(--ease-soft)}
.btn-cta-ghost:hover{border-color:var(--primary);color:var(--primary);transform:translateY(-2px);box-shadow:var(--shadow-sm)}
.btn-cta-ghost svg{color:var(--primary)}

/* Trust */
.hero-trust{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.trust-avatars{display:flex}
.trust-avatar{width:36px;height:36px;border-radius:50%;border:2.5px solid #fff;background:var(--primary);
  color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;
  margin-left:-8px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.trust-avatar:first-child{margin-left:0}
.trust-stars{font-size:14px;font-weight:700;color:#FBBF24;letter-spacing:1px}
.trust-stars span{color:var(--text);margin-left:6px}
.trust-label{font-size:13px;color:var(--text-soft)}

/* Hero visual / mockup */
.hero-visual{position:relative;animation:slideUpFade 1s var(--ease-soft) .15s both}

.hero-banner-card{aspect-ratio:1;border-radius:32px;overflow:hidden;box-shadow:var(--shadow-primary-lg);
  transform:rotate(-2deg);transition:transform .5s var(--ease-soft)}
.hero-banner-card:hover{transform:rotate(0)}
.hero-banner-card img{width:100%;height:100%;object-fit:cover;display:block}

.hero-mockup{position:relative;aspect-ratio:1;max-width:520px;margin:0 auto}
.mockup-card{position:absolute;background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(15,23,42,.12),0 0 0 1px rgba(15,23,42,.04);
  overflow:hidden;animation:floatY 6s ease-in-out infinite}
.mockup-main{inset:5% 8%;z-index:2;animation-duration:8s}
.mockup-header{padding:14px 16px;background:linear-gradient(180deg,#F8FAFC,#fff);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:6px}
.mockup-dot{width:11px;height:11px;border-radius:50%}
.mockup-dot.red{background:#EF4444}
.mockup-dot.yellow{background:#F59E0B}
.mockup-dot.green{background:#10B981}
.mockup-title{margin-left:auto;font-size:11px;font-weight:600;color:var(--text-soft)}
.mockup-body{padding:18px}
.mockup-progress{height:5px;background:var(--bg-mute);border-radius:3px;overflow:hidden;margin-bottom:18px}
.mockup-progress-bar{height:100%;width:25%;background:linear-gradient(90deg,#3B82F6,#10B981);border-radius:3px;
  animation:progressGrow 2s ease-out forwards}
@keyframes progressGrow{from{width:0}to{width:25%}}
.mockup-q-label{font-size:10px;font-weight:800;color:var(--primary);letter-spacing:.1em;margin-bottom:6px}
.mockup-q-text{font-size:14px;font-weight:600;line-height:1.4;color:var(--text);margin-bottom:14px}
.mockup-answers{display:flex;flex-direction:column;gap:6px}
.mockup-answer{display:flex;align-items:center;gap:8px;padding:9px 12px;background:var(--bg-soft);border-radius:8px;font-size:12px;color:var(--text);border:1.5px solid transparent;transition:all .3s}
.mockup-answer.correct{background:#D1FAE5;border-color:var(--success);color:var(--success-dark);font-weight:600;animation:answerPulse 3s ease-in-out infinite}
@keyframes answerPulse{0%,90%,100%{box-shadow:0 0 0 0 rgba(16,185,129,0)}45%{box-shadow:0 0 0 8px rgba(16,185,129,.15)}}
.mockup-letter{flex-shrink:0;width:20px;height:20px;border-radius:50%;background:#fff;border:1.5px solid var(--border);
  display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:var(--text-soft)}
.mockup-answer.correct .mockup-letter{background:var(--success);border-color:var(--success);color:#fff}

/* Floating cards */
.floating-card{position:absolute;display:flex;align-items:center;gap:10px;padding:12px 16px;
  background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(15,23,42,.1),0 0 0 1px rgba(15,23,42,.04);
  z-index:3;animation:floatY 5s ease-in-out infinite}
.float-1{top:0;right:-5%;animation-duration:7s}
.float-2{bottom:15%;left:-5%;animation-duration:6s;animation-delay:.5s}
.float-3{top:35%;right:-8%;animation-duration:8s;animation-delay:1s}
.float-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0}
.float-title{font-size:12px;font-weight:700;color:var(--text);line-height:1.2}
.float-meta{font-size:10px;color:var(--text-soft);margin-top:2px}

/* Hero stats strip */
.hero-stats-v2{display:flex;align-items:center;justify-content:space-around;padding:32px 24px;
  background:#fff;border-radius:24px;box-shadow:0 12px 40px rgba(15,23,42,.08);
  margin:0 auto -50px;max-width:1100px;position:relative;z-index:5;flex-wrap:wrap;gap:14px}
.stat-pill{text-align:center;flex:1;min-width:120px}
.stat-pill .stat-num{font-size:36px;font-weight:900;line-height:1;
  background:linear-gradient(135deg,#3B82F6,#1E40AF);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.stat-pill .stat-label{font-size:12px;color:var(--text-soft);margin-top:6px;font-weight:600;text-transform:uppercase;letter-spacing:.04em}
.stat-divider{width:1px;height:48px;background:var(--border)}

@media (max-width:1024px){
  .hero-grid{grid-template-columns:1fr;gap:32px;text-align:center;padding:24px 20px 100px}
  .hero-actions{justify-content:center}
  .hero-trust{justify-content:center}
  .hero-subtitle{margin-left:auto;margin-right:auto}
  .hero-mockup{max-width:380px}
  .floating-card{display:none}
  .stat-divider{display:none}
}
@media (max-width:600px){
  .hero-stats-v2{padding:20px 16px;gap:8px}
  .stat-pill{min-width:80px}
  .stat-pill .stat-num{font-size:24px}
  .stat-pill .stat-label{font-size:10px}
  .hero-actions{flex-direction:column;gap:10px}
  .btn-cta-primary,.btn-cta-ghost{width:100%;justify-content:center}
}

/* ============================================================
   SECTION HEADERS
   ============================================================ */
.section{padding:120px 0 80px}
.section-soft{background:linear-gradient(180deg,#F8FAFC,#fff)}
.section-head{text-align:center;margin-bottom:60px;max-width:680px;margin-left:auto;margin-right:auto}
.section-title-v2{font-size:clamp(28px, 4vw, 42px);font-weight:800;letter-spacing:-.02em;line-height:1.15;margin:8px 0 14px;color:var(--text)}
.section-sub{font-size:16px;color:var(--text-soft);line-height:1.65}
.eyebrow{display:inline-block;font-size:13px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:.08em}

/* ============================================================
   FEATURES GRID
   ============================================================ */
.features-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.feature-card{position:relative;background:#fff;border-radius:24px;padding:36px 28px;
  border:1px solid var(--border);transition:all .4s var(--ease-soft);overflow:hidden}
.feature-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--accent);
  transform:scaleX(0);transform-origin:left;transition:transform .4s var(--ease-soft)}
.feature-card:hover{transform:translateY(-8px);box-shadow:0 24px 48px rgba(15,23,42,.08);border-color:transparent}
.feature-card:hover::before{transform:scaleX(1)}

.feature-icon-wrap{position:relative;width:64px;height:64px;border-radius:16px;display:flex;align-items:center;justify-content:center;
  margin-bottom:20px;color:var(--accent);transition:transform .4s var(--ease-back)}
.feature-icon-bg{position:absolute;inset:0;background:var(--accent);opacity:.1;border-radius:16px;
  transition:transform .4s var(--ease-soft)}
.feature-card:hover .feature-icon-wrap{transform:rotate(-8deg) scale(1.1)}
.feature-card:hover .feature-icon-bg{transform:scale(.85)}
.feature-icon-wrap svg{position:relative;z-index:1}

.feature-card h3{font-size:20px;font-weight:700;margin-bottom:8px;color:var(--text)}
.feature-card p{color:var(--text-soft);font-size:14px;line-height:1.65;margin-bottom:18px}
.feature-link{display:inline-flex;align-items:center;gap:6px;color:var(--accent);font-weight:600;font-size:14px;text-decoration:none;
  transition:gap .25s ease}
.feature-link:hover{gap:10px}

@media (max-width:768px){.features-grid{grid-template-columns:1fr;gap:16px}.feature-card{padding:28px 22px}}

/* ============================================================
   STEPS TIMELINE
   ============================================================ */
.steps-timeline{display:grid;grid-template-columns:repeat(4,1fr);gap:0;position:relative;max-width:1100px;margin:0 auto}
.step-item{position:relative;text-align:center;padding:0 20px}
.step-bubble{position:relative;width:80px;height:80px;border-radius:50%;background:#fff;
  display:flex;align-items:center;justify-content:center;margin:0 auto 18px;
  box-shadow:0 12px 28px rgba(15,23,42,.08);border:3px solid var(--step-color);transition:all .4s var(--ease-back);z-index:2}
.step-item:hover .step-bubble{transform:scale(1.1) rotate(5deg);box-shadow:0 16px 36px rgba(15,23,42,.12)}
.step-bubble-num{position:absolute;top:-6px;right:-6px;width:24px;height:24px;border-radius:50%;
  background:var(--step-color);color:#fff;font-weight:800;font-size:12px;
  display:flex;align-items:center;justify-content:center;border:2px solid #fff}
.step-bubble-icon{color:var(--step-color)}
.step-item h3{font-size:16px;font-weight:700;margin-bottom:6px;color:var(--text)}
.step-item p{font-size:13px;color:var(--text-soft);line-height:1.6}

.step-connector{position:absolute;top:40px;left:60%;width:80%;height:2px;
  background:linear-gradient(90deg,var(--step-color),transparent);z-index:1;display:none}
@media (min-width:768px){.step-connector{display:block}}

@media (max-width:768px){.steps-timeline{grid-template-columns:1fr;gap:24px}.step-connector{display:none}}

/* ============================================================
   PRICING V2
   ============================================================ */
.pricing-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;align-items:start}
.pricing-v2{position:relative;background:#fff;border-radius:24px;padding:36px 28px 32px;
  border:1px solid var(--border);transition:all .5s var(--ease-soft);display:flex;flex-direction:column}
.pricing-v2:hover{transform:translateY(-8px);box-shadow:0 28px 60px rgba(15,23,42,.1);border-color:var(--primary-200)}
.pricing-v2.is-popular{border:2px solid var(--primary);transform:scale(1.04);
  box-shadow:0 24px 56px rgba(59,130,246,.15)}
.pricing-v2.is-popular:hover{transform:scale(1.04) translateY(-8px)}

.pricing-glow{position:absolute;inset:-20px;background:radial-gradient(circle at center,rgba(59,130,246,.2),transparent 70%);
  z-index:-1;opacity:0;transition:opacity .5s;border-radius:32px;filter:blur(30px)}
.pricing-v2.is-popular .pricing-glow,.pricing-v2:hover .pricing-glow{opacity:1}

.pricing-popular-badge{position:absolute;top:-14px;left:50%;transform:translateX(-50%);
  background:linear-gradient(135deg,#3B82F6,#1E40AF);color:#fff;padding:6px 16px;border-radius:100px;
  font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;
  box-shadow:0 8px 20px rgba(59,130,246,.4);white-space:nowrap}

.pricing-header{text-align:center;margin-bottom:24px}
.pricing-header h3{font-size:24px;font-weight:800;margin-bottom:8px}
.pricing-header p{font-size:13px;color:var(--text-soft);min-height:38px}

.pricing-price-v2{text-align:center;padding:20px 0;border-top:1px solid var(--border);border-bottom:1px solid var(--border);margin-bottom:24px}
.price-currency{font-size:14px;color:var(--text-soft);font-weight:600;margin-right:4px}
.price-value{font-size:42px;font-weight:900;color:var(--primary);line-height:1;
  background:linear-gradient(135deg,#3B82F6,#1E40AF);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.price-period{font-size:14px;color:var(--text-soft);margin-left:4px}
.price-free{font-size:32px;font-weight:900;color:var(--success);
  background:linear-gradient(135deg,#10B981,#059669);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}

.pricing-features-v2{list-style:none;margin:0 0 24px;padding:0;flex:1;display:flex;flex-direction:column;gap:10px}
.pricing-features-v2 li{display:flex;align-items:flex-start;gap:10px;font-size:14px;color:var(--text-soft);line-height:1.5}
.feature-check{flex-shrink:0;width:20px;height:20px;border-radius:50%;background:var(--success-light);color:var(--success-dark);
  display:flex;align-items:center;justify-content:center;margin-top:1px}

.pricing-cta{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px 20px;
  border-radius:12px;font-weight:700;font-size:14px;text-decoration:none;transition:all .25s var(--ease-soft)}
.pricing-cta.is-primary{background:linear-gradient(135deg,#3B82F6,#2563EB);color:#fff;box-shadow:0 8px 20px rgba(59,130,246,.3)}
.pricing-cta.is-primary:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(59,130,246,.4);color:#fff}
.pricing-cta.is-outline{background:#fff;color:var(--primary);border:1.5px solid var(--border)}
.pricing-cta.is-outline:hover{border-color:var(--primary);background:var(--primary-50);transform:translateY(-2px)}
.pricing-cta svg{transition:transform .2s}
.pricing-cta:hover svg{transform:translateX(3px)}

@media (max-width:1024px){
  .pricing-grid{grid-template-columns:1fr;gap:20px;max-width:480px;margin:0 auto}
  .pricing-v2.is-popular{transform:none}
  .pricing-v2.is-popular:hover{transform:translateY(-8px)}
}

/* ============================================================
   TESTIMONIALS CAROUSEL
   ============================================================ */
.testimonials-wrap{position:relative;padding:0 60px}
.testimonials-track{display:flex;gap:24px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;
  padding:20px 4px;scrollbar-width:none;-ms-overflow-style:none}
.testimonials-track::-webkit-scrollbar{display:none}

.testimonial-card{flex:0 0 calc((100% - 48px) / 3);scroll-snap-align:start;background:#fff;border-radius:20px;padding:32px 26px;
  border:1px solid var(--border);transition:all .4s var(--ease-soft);position:relative}
.testimonial-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-md);border-color:var(--primary-200)}

.testimonial-quote{position:absolute;top:16px;right:24px;font-size:80px;color:var(--primary);opacity:.08;font-family:Georgia,serif;line-height:1;font-weight:900}
.testimonial-stars{display:flex;gap:2px;margin-bottom:14px}
.testimonial-text{font-size:15px;line-height:1.7;color:var(--text);margin-bottom:24px;min-height:80px;position:relative;z-index:1}

.testimonial-author{display:flex;align-items:center;gap:12px}
.testimonial-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#3B82F6,#1E40AF);
  color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;flex-shrink:0;
  box-shadow:0 4px 12px rgba(59,130,246,.3)}
.testimonial-name{font-weight:700;font-size:14px;color:var(--text)}
.testimonial-meta{font-size:12px;color:var(--text-mute);margin-top:2px}

.testimonial-arrow{position:absolute;top:50%;transform:translateY(-50%);width:48px;height:48px;border-radius:50%;
  background:#fff;border:1px solid var(--border);box-shadow:var(--shadow-md);
  display:flex;align-items:center;justify-content:center;color:var(--text);cursor:pointer;
  transition:all .25s var(--ease-soft);z-index:2}
.testimonial-arrow:hover{background:var(--primary);color:#fff;border-color:var(--primary);transform:translateY(-50%) scale(1.1)}
.testimonial-arrow.prev{left:0}
.testimonial-arrow.next{right:0}

.testimonials-dots{display:flex;justify-content:center;gap:8px;margin-top:24px}
.t-dot{width:8px;height:8px;border-radius:50%;background:var(--border);cursor:pointer;transition:all .25s}
.t-dot.active{width:32px;border-radius:4px;background:var(--primary)}

@media (max-width:1024px){
  .testimonial-card{flex:0 0 calc((100% - 24px) / 2)}
}
@media (max-width:640px){
  .testimonials-wrap{padding:0 16px}
  .testimonial-card{flex:0 0 calc(100% - 8px)}
  .testimonial-arrow{display:none}
}

/* ============================================================
   BLOG GRID V2
   ============================================================ */
.blog-grid-v2{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.blog-card-v2{display:block;background:#fff;border-radius:20px;overflow:hidden;border:1px solid var(--border);
  text-decoration:none;color:inherit;transition:all .4s var(--ease-soft)}
.blog-card-v2:hover{transform:translateY(-6px);box-shadow:var(--shadow-md);border-color:var(--primary-200)}
.blog-card-image{aspect-ratio:16/10;position:relative;overflow:hidden;background:linear-gradient(135deg,var(--primary-light),#fff)}
.blog-card-image img{width:100%;height:100%;object-fit:cover;transition:transform .6s var(--ease-soft)}
.blog-card-v2:hover .blog-card-image img{transform:scale(1.08)}
.blog-card-placeholder{display:flex;align-items:center;justify-content:center;height:100%;font-size:60px;color:var(--primary);opacity:.5}
.blog-card-category{position:absolute;top:14px;left:14px;background:rgba(255,255,255,.95);backdrop-filter:blur(10px);
  color:var(--primary);font-size:11px;font-weight:700;padding:5px 11px;border-radius:100px;letter-spacing:.04em;text-transform:uppercase}
.blog-card-content{padding:22px}
.blog-card-content h3{font-size:17px;font-weight:700;line-height:1.4;margin-bottom:8px;color:var(--text);
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.blog-card-content p{font-size:13px;color:var(--text-soft);line-height:1.6;margin-bottom:14px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.blog-card-meta{display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--text-mute);padding-top:12px;border-top:1px solid var(--border)}
.blog-card-meta span{display:inline-flex;align-items:center;gap:4px}
.blog-card-arrow{color:var(--primary);transition:transform .25s ease}
.blog-card-v2:hover .blog-card-arrow{transform:translateX(4px)}

.btn-link{display:inline-flex;align-items:center;gap:6px;color:var(--primary);font-weight:600;font-size:14px;text-decoration:none;
  transition:gap .25s ease}
.btn-link:hover{gap:10px;color:var(--primary-dark)}

@media (max-width:768px){.blog-grid-v2{grid-template-columns:1fr;gap:18px}}

/* ============================================================
   CTA SECTION
   ============================================================ */
.cta-section{padding:60px 0 100px}
.cta-card{position:relative;background:linear-gradient(135deg,#3B82F6 0%,#1E40AF 100%);
  border-radius:32px;padding:80px 60px;color:#fff;overflow:hidden;text-align:center;
  box-shadow:0 24px 60px rgba(59,130,246,.3)}
.cta-bg-pattern{position:absolute;inset:0;background:
  radial-gradient(circle at 20% 50%, rgba(255,255,255,.15), transparent 40%),
  radial-gradient(circle at 80% 30%, rgba(168,85,247,.2), transparent 40%);
  z-index:0}
.cta-content{position:relative;z-index:1;max-width:680px;margin:0 auto}

.cta-eyebrow{display:inline-flex;align-items:center;gap:8px;padding:6px 16px;
  background:rgba(255,255,255,.15);backdrop-filter:blur(10px);border-radius:100px;font-size:13px;font-weight:600;margin-bottom:20px}
.dot-pulse{width:6px;height:6px;border-radius:50%;background:#10B981;
  box-shadow:0 0 0 4px rgba(16,185,129,.4);animation:dotPulse 2s ease-in-out infinite}

.cta-title{font-size:clamp(28px, 4vw, 42px);font-weight:900;color:#fff;line-height:1.15;margin-bottom:14px}
.cta-subtitle{font-size:17px;opacity:.92;margin-bottom:36px;line-height:1.6;color:#fff}

.cta-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-bottom:32px}

.btn-cta-white{display:inline-flex;align-items:center;gap:10px;padding:16px 32px;
  background:#fff;color:var(--primary);border-radius:14px;font-weight:700;font-size:15px;text-decoration:none;
  transition:all .25s var(--ease-soft);box-shadow:0 8px 20px rgba(0,0,0,.15)}
.btn-cta-white:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(0,0,0,.25);color:var(--primary)}
.btn-cta-white svg{transition:transform .25s}
.btn-cta-white:hover svg{transform:translateX(3px)}

.btn-cta-outline-white{display:inline-flex;align-items:center;padding:16px 28px;background:rgba(255,255,255,.15);backdrop-filter:blur(10px);
  color:#fff;border:1.5px solid rgba(255,255,255,.4);border-radius:14px;font-weight:600;font-size:15px;text-decoration:none;transition:all .25s}
.btn-cta-outline-white:hover{background:rgba(255,255,255,.25);border-color:#fff;color:#fff}

.cta-features{display:flex;justify-content:center;gap:24px;flex-wrap:wrap;font-size:13px;opacity:.85}
.cta-features span{display:inline-flex;align-items:center;gap:6px}

@media (max-width:768px){
  .cta-card{padding:48px 24px;border-radius:24px}
  .cta-actions{flex-direction:column;gap:10px}
  .btn-cta-white,.btn-cta-outline-white{width:100%;justify-content:center}
  .cta-features{flex-direction:column;gap:10px}
}
</style>

<script>
// ============== TESTIMONIALS CAROUSEL ==============
(function(){
  const track = document.getElementById('testimonialsTrack');
  const dotsWrap = document.getElementById('testimonialsDots');
  if (!track) return;

  const cards = track.querySelectorAll('.testimonial-card');
  let currentSlide = 0;
  let perView = 3;

  function calculatePerView(){
    if (window.innerWidth < 640) perView = 1;
    else if (window.innerWidth < 1024) perView = 2;
    else perView = 3;
  }
  calculatePerView();

  const totalSlides = Math.max(1, cards.length - perView + 1);

  // Dots
  function buildDots(){
    if (!dotsWrap) return;
    dotsWrap.innerHTML = '';
    for (let i = 0; i < totalSlides; i++) {
      const dot = document.createElement('div');
      dot.className = 't-dot' + (i === 0 ? ' active' : '');
      dot.onclick = () => scrollToIdx(i);
      dotsWrap.appendChild(dot);
    }
  }
  buildDots();

  function scrollToIdx(idx){
    if (!cards[idx]) return;
    const target = cards[idx].offsetLeft - track.offsetLeft;
    track.scrollTo({left: target, behavior: 'smooth'});
    currentSlide = idx;
    updateDots();
  }

  function updateDots(){
    dotsWrap?.querySelectorAll('.t-dot').forEach((d, i) => {
      d.classList.toggle('active', i === currentSlide);
    });
  }

  window.scrollTestimonials = function(dir){
    let next = currentSlide + dir;
    if (next < 0) next = totalSlides - 1;
    if (next >= totalSlides) next = 0;
    scrollToIdx(next);
  };

  // Auto-scroll
  let autoScroll = setInterval(() => scrollTestimonials(1), 5000);

  track.addEventListener('mouseenter', () => clearInterval(autoScroll));
  track.addEventListener('mouseleave', () => {
    clearInterval(autoScroll);
    autoScroll = setInterval(() => scrollTestimonials(1), 5000);
  });

  // Update on scroll (manual)
  let scrollTimeout;
  track.addEventListener('scroll', () => {
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(() => {
      const cardWidth = cards[0]?.offsetWidth + 24;
      const newIdx = Math.round(track.scrollLeft / cardWidth);
      if (newIdx !== currentSlide && newIdx >= 0 && newIdx < totalSlides) {
        currentSlide = newIdx;
        updateDots();
      }
    }, 150);
  });

  // Resize
  window.addEventListener('resize', () => {
    calculatePerView();
    buildDots();
    currentSlide = 0;
  });
})();
</script>

<?php
render_footer();
