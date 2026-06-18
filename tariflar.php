<?php
/**
 * tariflar.php — STANDALONE pricing page
 */
require_once __DIR__ . '/includes/bootstrap.php';

$tariffs = db()->fetchAll("SELECT * FROM tariffs WHERE status='active' ORDER BY sort_order ASC");
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';
$site_name = setting('site_name', SITE_NAME);

$faqs = lang()==='uz_cyrillic' ? [
    ['Қандай қилиб тарифни танлайман?','Сиз ўзингизга мос тарифни танлаб, тўлов қилишингиз мумкин. Тўловдан сўнг тариф автоматик фаоллашади.'],
    ['Тўловни қандай амалга ошираман?','Click, Payme, ёки банк картаси орқали тўлайсиз. Скриншотни Telegram ботга юбориш ҳам мумкин.'],
    ['Бепул тариф нима?','Бепул тарифда сиз кунига 3 та тестни ечишингиз мумкин.'],
    ['Тарифни ўзгартириш мумкинми?','Ҳа, исталган вақтда тарифни юқори ёки пастга ўзгартиришингиз мумкин.'],
    ['Пулни қайтариш мумкинми?','Биринчи 3 кун ичида қониқарсиз бўлсангиз, пулни 100% қайтарамиз.'],
    ['Сертификат бериладими?','Premium тарифидаги фойдаланувчиларга сертификат берилади.'],
] : [
    ['Qanday qilib tarifni tanlayman?','Siz o\'zingizga mos tarifni tanlab, to\'lov qilishingiz mumkin. To\'lovdan so\'ng tarif avtomatik faollashadi.'],
    ['To\'lovni qanday amalga oshiraman?','Click, Payme, yoki bank kartasi orqali to\'laysiz. Skrinshotni Telegram botga yuborish ham mumkin.'],
    ['Bepul tarif nima?','Bepul tarifda siz kuniga 3 ta testni yechishingiz mumkin.'],
    ['Tarifni o\'zgartirish mumkinmi?','Ha, istalgan vaqtda tarifni yuqori yoki pastga o\'zgartirishingiz mumkin.'],
    ['Pulni qaytarish mumkinmi?','Birinchi 3 kun ichida qoniqarsiz bo\'lsangiz, pulni 100% qaytaramiz.'],
    ['Sertifikat beriladimi?','Premium tarifidagi foydalanuvchilarga sertifikat beriladi.'],
];
?><!DOCTYPE html>
<html lang="<?= lang() === 'uz_cyrillic' ? 'uz-Cyrl' : 'uz' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#3B82F6">
<title><?= e(t('tariffs')) ?> — <?= e($site_name) ?></title>
<meta name="description" content="<?= e(setting('seo_description')) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(setting('site_logo', '/assets/images/logo.svg')) ?>">
<style>
<?= base_css() ?>

/* ===== TARIFLAR.PHP — premium pricing design ===== */
.tr-header{background:rgba(255,255,255,.85);backdrop-filter:saturate(180%) blur(20px);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:50}
.tr-nav{display:flex;align-items:center;justify-content:space-between;padding:14px 0;flex-wrap:wrap;gap:12px}
.tr-logo{display:inline-flex;align-items:center;gap:10px;font-weight:800;color:var(--text);text-decoration:none;font-size:15px}
.tr-logo .li{width:40px;height:40px;border-radius:11px;background:linear-gradient(135deg,var(--primary),#1E40AF);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px}
.tr-menu{display:flex;gap:18px;list-style:none}
.tr-menu a{color:var(--text-soft);font-size:14px;font-weight:500}
.tr-menu a:hover,.tr-menu a.active{color:var(--primary)}
.tr-actions{display:flex;align-items:center;gap:8px}
.tr-lang{display:inline-flex;background:var(--bg-mute);border-radius:8px;padding:3px;gap:2px}
.tr-lang a{padding:4px 10px;border-radius:5px;font-size:11px;font-weight:700;color:var(--text-soft)}
.tr-lang a.active{background:#fff;color:var(--primary)}
@media (max-width:880px){.tr-menu{display:none}}

.tr-hero{padding:64px 0 32px;background:linear-gradient(180deg,#EFF6FF 0%,#fff 100%);text-align:center;position:relative;overflow:hidden}
.tr-hero::before{content:'';position:absolute;top:-30%;left:50%;transform:translateX(-50%);width:600px;height:600px;background:radial-gradient(circle,rgba(59,130,246,.15),transparent 70%);border-radius:50%;pointer-events:none}
.tr-hero .container{position:relative;z-index:1}
.tr-eyebrow{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--primary);background:#fff;padding:6px 14px;border-radius:100px;border:1px solid var(--primary-200);box-shadow:var(--shadow-sm)}
.tr-hero h1{font-size:clamp(30px,5vw,52px);font-weight:900;letter-spacing:-.025em;margin:18px 0 12px;color:var(--text);line-height:1.05}
.tr-hero h1 .grad{background:linear-gradient(135deg,var(--primary),#1E40AF);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.tr-hero p{color:var(--text-soft);font-size:clamp(15px,1.4vw,18px);max-width:560px;margin:0 auto;line-height:1.6}

.tr-toggle{display:inline-flex;background:#fff;border:1px solid var(--border);border-radius:100px;padding:4px;margin-top:24px;gap:2px}
.tr-toggle button{padding:8px 18px;border-radius:100px;font-size:12px;font-weight:700;color:var(--text-soft);background:transparent;border:none;cursor:pointer}
.tr-toggle button.active{background:var(--primary);color:#fff}

.pricing-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(280px,100%),1fr));gap:18px;padding:32px 0}
.pricing-card{
  position:relative;background:#fff;border:1px solid var(--border);border-radius:24px;
  padding:32px 26px 28px;display:flex;flex-direction:column;
  transition:all .35s cubic-bezier(.22,1,.36,1)
}
.pricing-card:hover{transform:translateY(-6px);box-shadow:0 24px 48px rgba(15,23,42,.08);border-color:var(--primary-200)}
.pricing-card.popular{
  border:2px solid var(--primary);
  box-shadow:0 24px 56px rgba(59,130,246,.18);
  position:relative;
}
.pricing-card.popular::before{
  content:'';position:absolute;inset:-2px;border-radius:24px;
  background:linear-gradient(135deg,var(--primary),#8B5CF6,#EC4899);
  z-index:-1;opacity:0;transition:opacity .3s;
}
.pricing-card.popular:hover::before{opacity:1}
.pricing-badge{position:absolute;top:-13px;left:50%;transform:translateX(-50%);
  background:linear-gradient(135deg,#3B82F6,#1E40AF);color:#fff;
  padding:6px 16px;border-radius:100px;font-size:11px;font-weight:800;letter-spacing:.05em;
  text-transform:uppercase;box-shadow:0 8px 20px rgba(59,130,246,.4);white-space:nowrap}
.pricing-card h3{font-size:22px;font-weight:800;letter-spacing:-.015em;margin-bottom:6px;text-align:center}
.pricing-card .desc{font-size:13px;color:var(--text-soft);text-align:center;margin-bottom:18px;min-height:38px}
.pricing-price{
  text-align:center;padding:18px 0;margin-bottom:18px;
  border-top:1px solid var(--border);border-bottom:1px solid var(--border);
}
.pricing-price .val{
  font-size:42px;font-weight:900;line-height:1;color:var(--primary);
  background:linear-gradient(135deg,#3B82F6,#1E40AF);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.pricing-price .val.free{background:linear-gradient(135deg,#10B981,#059669);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;font-size:32px}
.pricing-price small{font-size:12px;color:var(--text-soft);font-weight:500;display:block;margin-top:4px}
.features{list-style:none;margin:0 0 22px;padding:0;display:flex;flex-direction:column;gap:10px;flex:1}
.features li{display:flex;align-items:flex-start;gap:10px;font-size:13.5px;color:var(--text-soft);line-height:1.5}
.features .chk{flex-shrink:0;width:20px;height:20px;border-radius:50%;background:#D1FAE5;color:#065F46;display:flex;align-items:center;justify-content:center;margin-top:1px}
.pricing-cta{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px 20px;border-radius:12px;font-weight:700;font-size:14px;text-decoration:none;transition:all .25s}
.pricing-cta.primary{background:linear-gradient(135deg,#3B82F6,#1E40AF);color:#fff;box-shadow:0 8px 20px rgba(59,130,246,.3)}
.pricing-cta.primary:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(59,130,246,.4);color:#fff}
.pricing-cta.outline{background:#fff;color:var(--primary);border:1.5px solid var(--border)}
.pricing-cta.outline:hover{border-color:var(--primary);background:var(--primary-50);transform:translateY(-2px)}

/* FAQ */
.faq-wrap{max-width:760px;margin:0 auto;padding:48px 0}
.faq-wrap h2{font-size:clamp(24px,3.5vw,36px);font-weight:800;letter-spacing:-.02em;text-align:center;margin-bottom:32px}
.faq-item{background:#fff;border:1px solid var(--border);border-radius:14px;margin-bottom:10px;overflow:hidden;transition:border-color .2s}
.faq-item.open{border-color:var(--primary);box-shadow:var(--shadow-sm)}
.faq-q{padding:18px 20px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;font-weight:600;font-size:14.5px;color:var(--text);gap:12px}
.faq-q:hover{background:var(--bg-soft)}
.faq-q .arrow{flex-shrink:0;color:var(--text-mute);transition:transform .25s}
.faq-item.open .faq-q .arrow{transform:rotate(180deg);color:var(--primary)}
.faq-a{padding:0 20px;max-height:0;overflow:hidden;transition:max-height .3s,padding .25s;color:var(--text-soft);font-size:13.5px;line-height:1.6}
.faq-item.open .faq-a{padding:0 20px 18px;max-height:300px}

.tr-footer{background:#0F172A;color:#94A3B8;padding:24px 0;text-align:center;font-size:13px}

@media (max-width:640px){
  .tr-hero{padding:40px 0 24px}
  .pricing-card{padding:26px 20px 22px}
  .pricing-price .val{font-size:36px}
  .pricing-card.popular{transform:none}
  .pricing-card.popular:hover{transform:translateY(-4px)}
}
</style>
</head>
<body>

<header class="tr-header">
  <div class="container tr-nav">
    <a href="/" class="tr-logo"><span class="li">VP</span><span><?= e($site_name) ?></span></a>
    <ul class="tr-menu">
      <li><a href="/"><?= t('home') ?></a></li>
      <li><a href="/tariflar.php" class="active"><?= t('tariffs') ?></a></li>
      <li><a href="/blog.php"><?= t('blog') ?></a></li>
      <li><a href="/aloqa.php"><?= t('contact') ?></a></li>
    </ul>
    <div class="tr-actions">
      <div class="tr-lang">
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

<section class="tr-hero">
  <div class="container">
    <div class="tr-eyebrow"><?= icon('gem', 12) ?> <?= lang()==='uz_cyrillic' ? "Тарифлар" : "Tariflar" ?></div>
    <h1><?= lang()==='uz_cyrillic' ? "Имтиёзли" : "Imtiyozli" ?> <span class="grad"><?= lang()==='uz_cyrillic' ? "тарифлар" : "tariflar" ?></span></h1>
    <p><?= lang()==='uz_cyrillic' ? 'Сизга мос тарифни танланг ва ҳозироқ ўрганишни бошланг' : 'Sizga mos tarifni tanlang va hoziroq o\'rganishni boshlang' ?></p>
  </div>
</section>

<section>
  <div class="container">
    <div class="pricing-grid">
      <?php foreach ($tariffs as $tariff):
        $features = array_filter(array_map('trim', explode('|', $tariff['features_'.$lang_field] ?? '')));
        $isFree = (float)$tariff['price'] <= 0;
        $isPopular = !empty($tariff['is_popular']);
      ?>
      <div class="pricing-card <?= $isPopular?'popular':'' ?>">
        <?php if ($isPopular): ?>
          <div class="pricing-badge">⭐ <?= t('popular') ?></div>
        <?php endif; ?>
        <h3><?= e($tariff['name_'.$lang_field]) ?></h3>
        <p class="desc"><?= e($tariff['description_'.$lang_field]) ?></p>
        <div class="pricing-price">
          <?php if ($isFree): ?>
            <div class="val free"><?= t('free') ?></div>
          <?php else: ?>
            <div class="val"><?= money($tariff['price']) ?></div>
            <small><?= t('soum') ?> · <?= (int)$tariff['duration_days'] ?> <?= lang()==='uz_cyrillic' ? "кун" : "kun" ?></small>
          <?php endif; ?>
        </div>
        <ul class="features">
          <?php foreach ($features as $f): ?>
            <li><span class="chk"><?= icon('check', 12) ?></span><span><?= e($f) ?></span></li>
          <?php endforeach; ?>
        </ul>
        <a href="<?= is_logged_in() ? '/user/tariflar.php' : '/register.php?tariff='.$tariff['id'] ?>" class="pricing-cta <?= $isPopular?'primary':'outline' ?>">
          <?= t('choose_plan') ?> <?= icon('arrow-right', 14) ?>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="container">
  <div class="faq-wrap">
    <h2><?= lang()==='uz_cyrillic' ? "Тез-тез сўраладиган саволлар" : "Tez-tez so'raladigan savollar" ?></h2>
    <?php foreach ($faqs as $i => $f): ?>
      <div class="faq-item">
        <div class="faq-q" onclick="this.parentElement.classList.toggle('open')">
          <span><?= e($f[0]) ?></span>
          <span class="arrow"><?= icon('chevron-down', 18) ?></span>
        </div>
        <div class="faq-a"><?= e($f[1]) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<footer class="tr-footer">
  <div class="container">© <?= date('Y') ?> <?= e($site_name) ?>. <?= t('all_rights') ?>.</div>
</footer>

</body></html>
