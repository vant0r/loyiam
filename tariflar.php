<?php
require_once __DIR__ . '/includes/functions.php';

$tariffs = db()->fetchAll("SELECT * FROM tariffs WHERE status='active' ORDER BY sort_order ASC");
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

$faqs = lang()==='uz_cyrillic' ? [
    ['Қандай қилиб тарифни танлайман?','Сиз ўзингизга мос тарифни танлаб, тўлов қилишингиз мумкин. Тўловдан сўнг тариф автоматик фаоллашади.'],
    ['Тўловни қандай амалга ошираман?','Click, Payme, ёки банк картаси орқали тўлайсиз. Скриншотни Telegram ботга юбориш ҳам мумкин.'],
    ['Бепул тариф нима?','Бепул тарифда сиз кунига 3 та тестни ечишингиз мумкин. Бу платформани синаб кўриш учун.'],
    ['Тарифни ўзгартириш мумкинми?','Ҳа, исталган вақтда тарифни юқори ёки пастга ўзгартиришингиз мумкин.'],
    ['Пулни қайтариш мумкинми?','Биринчи 3 кун ичида қониқарсиз бўлсангиз, пулни 100% қайтарамиз.'],
    ['Сертификат бериладими?','Premium тарифидаги фойдаланувчиларга курсни тугатиш сертификати берилади.'],
] : [
    ['Qanday qilib tarifni tanlayman?','Siz o\'zingizga mos tarifni tanlab, to\'lov qilishingiz mumkin. To\'lovdan so\'ng tarif avtomatik faollashadi.'],
    ['To\'lovni qanday amalga oshiraman?','Click, Payme, yoki bank kartasi orqali to\'laysiz. Skrinshotni Telegram botga yuborish ham mumkin.'],
    ['Bepul tarif nima?','Bepul tarifda siz kuniga 3 ta testni yechishingiz mumkin. Bu platformani sinab ko\'rish uchun.'],
    ['Tarifni o\'zgartirish mumkinmi?','Ha, istalgan vaqtda tarifni yuqori yoki pastga o\'zgartirishingiz mumkin.'],
    ['Pulni qaytarish mumkinmi?','Birinchi 3 kun ichida qoniqarsiz bo\'lsangiz, pulni 100% qaytaramiz.'],
    ['Sertifikat beriladimi?','Premium tarifidagi foydalanuvchilarga kursni tugatish sertifikati beriladi.'],
];

render_head(t('tariffs'));
render_header('tariffs');
?>

<section class="hero" style="padding:60px 0">
  <div class="container text-center">
    <h1 style="font-size:42px;font-weight:800;margin-bottom:14px;color:var(--text)"><?= t('tariffs') ?></h1>
    <p style="color:var(--text-soft);font-size:16px;max-width:600px;margin:0 auto"><?= lang()==='uz_cyrillic' ? 'Сизга мос тарифни танланг ва ҳозироқ ўрганишни бошланг' : 'Sizga mos tarifni tanlang va hoziroq o\'rganishni boshlang' ?></p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid-3">
      <?php foreach ($tariffs as $tariff):
        $features = explode('|', $tariff['features_'.$lang_field] ?? '');
      ?>
      <div class="card pricing-card <?= $tariff['is_popular']?'popular':'' ?>">
        <?php if ($tariff['is_popular']): ?>
          <div class="pricing-badge"><?= t('popular') ?></div>
        <?php endif; ?>
        <h3><?= e($tariff['name_'.$lang_field]) ?></h3>
        <p style="color:var(--text-soft);font-size:14px"><?= e($tariff['description_'.$lang_field]) ?></p>
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
        <a href="/register.php?tariff=<?= $tariff['id'] ?>" class="btn btn-primary btn-block"><?= t('choose_plan') ?></a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- TAQQOSLASH JADVALI -->
<section class="section section-soft">
  <div class="container">
    <h2 class="section-title"><?= lang()==='uz_cyrillic' ? 'Тарифларни таққослаш' : 'Tariflarni taqqoslash' ?></h2>
    <div class="table-wrap mt-3">
      <table>
        <thead>
          <tr>
            <th><?= lang()==='uz_cyrillic' ? 'Хусусият' : 'Xususiyat' ?></th>
            <?php foreach ($tariffs as $t): ?><th class="text-center"><?= e($t['name_'.$lang_field]) ?></th><?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= lang()==='uz_cyrillic' ? 'Нархи' : 'Narxi' ?></td>
            <?php foreach ($tariffs as $t): ?>
              <td class="text-center"><strong><?= $t['price']==0 ? t('free') : money($t['price']).' '.t('soum') ?></strong></td>
            <?php endforeach; ?>
          </tr>
          <tr>
            <td><?= lang()==='uz_cyrillic' ? 'Кунлик тестлар' : 'Kunlik testlar' ?></td>
            <?php foreach ($tariffs as $t): ?>
              <td class="text-center"><?= $t['tests_per_day']>=999 ? '∞' : $t['tests_per_day'] ?></td>
            <?php endforeach; ?>
          </tr>
          <tr>
            <td><?= lang()==='uz_cyrillic' ? 'Муддат (кун)' : 'Muddat (kun)' ?></td>
            <?php foreach ($tariffs as $t): ?><td class="text-center"><?= $t['duration_days'] ?></td><?php endforeach; ?>
          </tr>
          <tr>
            <td><?= lang()==='uz_cyrillic' ? 'Статистика' : 'Statistika' ?></td>
            <?php foreach ($tariffs as $i => $t): ?>
              <td class="text-center"><?= $i>=1 ? '<span class="badge badge-success">'.t('completed').'</span>' : '<span class="badge badge-mute">—</span>' ?></td>
            <?php endforeach; ?>
          </tr>
          <tr>
            <td><?= lang()==='uz_cyrillic' ? 'Видео дарслар' : 'Video darslar' ?></td>
            <?php foreach ($tariffs as $i => $t): ?>
              <td class="text-center"><?= $i>=2 ? '<span class="badge badge-success">'.t('completed').'</span>' : '<span class="badge badge-mute">—</span>' ?></td>
            <?php endforeach; ?>
          </tr>
          <tr>
            <td><?= lang()==='uz_cyrillic' ? 'Сертификат' : 'Sertifikat' ?></td>
            <?php foreach ($tariffs as $i => $t): ?>
              <td class="text-center"><?= $i>=2 ? '<span class="badge badge-success">'.t('completed').'</span>' : '<span class="badge badge-mute">—</span>' ?></td>
            <?php endforeach; ?>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="section">
  <div class="container" style="max-width:800px">
    <h2 class="section-title"><?= t('faq') ?></h2>
    <div class="mt-3">
      <?php foreach ($faqs as $f): ?>
      <div class="faq-item">
        <div class="faq-q"><span><?= e($f[0]) ?></span><i>▾</i></div>
        <div class="faq-a"><?= e($f[1]) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php render_footer(); ?>
