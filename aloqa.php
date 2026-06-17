<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

$success = false; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = t('csrf_invalid');
    } else {
        // Rate limit (10 ta xabar / soat)
        $rl = Security::rate_limit('contact_' . client_ip(), 10, 3600);
        if (!$rl['allowed']) {
            $error = t('too_many_attempts');
        } else {
            $name    = Security::clean($_POST['name'] ?? '', 150);
            $email   = Security::clean($_POST['email'] ?? '', 150);
            $phone   = Security::clean($_POST['phone'] ?? '', 30);
            $message = Security::clean($_POST['message'] ?? '', 2000);

            if (!$name || !$message) {
                $error = t('message_required');
            } elseif ($email && !Security::valid_email($email)) {
                $error = t('invalid_email');
            } else {
                $ok = db()->execute(
                    "INSERT INTO contact_messages (name,email,phone,message) VALUES (?,?,?,?)",
                    [$name, $email ?: null, $phone ?: null, $message]
                );
                if ($ok) {
                    $success = true;
                    audit('contact_form', "From: $name <$email>", 'info');
                } else {
                    $error = lang()==='uz_cyrillic' ? "Хабар юборишда хатолик" : "Xabar yuborishda xatolik";
                }
            }
        }
    }
}

render_head(t('contact'));
render_header('contact');
?>
<section style="padding:48px 0 32px;background:linear-gradient(180deg, var(--bg-soft), #fff)">
  <div class="container">
    <div style="max-width:680px;margin:0 auto;text-align:center">
      <div class="eyebrow"><?= icon('message', 12) ?> <?= lang()==='uz_cyrillic' ? "Биз билан боғланинг" : "Biz bilan bog'laning" ?></div>
      <h1 style="font-size:clamp(28px, 4vw, 44px);font-weight:800;letter-spacing:-.02em;margin:14px 0;color:var(--text)"><?= t('contact') ?></h1>
      <p style="color:var(--text-soft);font-size:clamp(15px, 1.4vw, 17px);line-height:1.6">
        <?= lang()==='uz_cyrillic' ? "Саволларингиз бўлса бизга ёзинг — биз доим тайёрмиз" : "Savollaringiz bo'lsa bizga yozing — biz doim tayyormiz" ?>
      </p>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div style="display:grid;grid-template-columns:1fr 1.2fr;gap:24px;align-items:start">

      <!-- Contact info -->
      <div style="display:flex;flex-direction:column;gap:14px">
        <h2 style="font-size:22px;font-weight:800;letter-spacing:-.015em;margin-bottom:6px"><?= lang()==='uz_cyrillic' ? "Алоқа маълумотлари" : "Aloqa ma'lumotlari" ?></h2>

        <div class="card" style="display:flex;gap:14px;align-items:flex-start">
          <div class="metric-icon" style="background:var(--primary-light);color:var(--primary)"><?= icon('map-pin', 20) ?></div>
          <div style="flex:1">
            <strong><?= t('address') ?></strong>
            <p class="text-soft" style="font-size:13.5px;margin-top:3px"><?= e(lang()==='uz_cyrillic' ? setting('site_address_cyrillic') : setting('site_address')) ?></p>
          </div>
        </div>

        <div class="card" style="display:flex;gap:14px;align-items:flex-start">
          <div class="metric-icon" style="background:var(--accent-emerald-light);color:var(--success-dark)"><?= icon('phone', 20) ?></div>
          <div style="flex:1">
            <strong><?= t('phone') ?></strong>
            <p style="margin-top:3px;font-size:14px"><a href="tel:<?= e(setting('site_phone')) ?>"><?= e(setting('site_phone')) ?></a></p>
          </div>
        </div>

        <div class="card" style="display:flex;gap:14px;align-items:flex-start">
          <div class="metric-icon" style="background:var(--accent-violet-light);color:var(--accent-violet-dark)"><?= icon('mail', 20) ?></div>
          <div style="flex:1">
            <strong>Email</strong>
            <p style="margin-top:3px;font-size:14px"><a href="mailto:<?= e(setting('site_email')) ?>"><?= e(setting('site_email')) ?></a></p>
          </div>
        </div>

        <div class="card" style="display:flex;gap:14px;align-items:flex-start">
          <div class="metric-icon" style="background:var(--accent-amber-light);color:var(--warning-dark)"><?= icon('clock', 20) ?></div>
          <div style="flex:1">
            <strong><?= t('work_time') ?></strong>
            <p class="text-soft" style="font-size:13.5px;margin-top:3px"><?= e(setting('working_hours')) ?></p>
          </div>
        </div>

        <!-- Social cards -->
        <div class="card" style="background:linear-gradient(135deg, var(--primary-50), #fff);border-color:var(--primary-200)">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
            <div class="metric-icon" style="background:#fff;color:var(--primary);width:32px;height:32px;border-radius:8px"><?= icon('sparkles', 16) ?></div>
            <strong style="font-size:14px"><?= lang()==='uz_cyrillic' ? "Ижтимоий тармоқлар" : "Ijtimoiy tarmoqlar" ?></strong>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="<?= e(setting('telegram_url','#')) ?>" target="_blank" class="btn btn-light btn-sm"><?= icon('telegram', 14) ?> Telegram</a>
            <a href="<?= e(setting('instagram_url','#')) ?>" target="_blank" class="btn btn-light btn-sm"><?= icon('instagram', 14) ?> Instagram</a>
            <a href="<?= e(setting('youtube_url','#')) ?>" target="_blank" class="btn btn-light btn-sm"><?= icon('youtube', 14) ?> YouTube</a>
            <a href="<?= e(setting('facebook_url','#')) ?>" target="_blank" class="btn btn-light btn-sm"><?= icon('facebook', 14) ?> Facebook</a>
          </div>
        </div>
      </div>

      <!-- Form -->
      <div>
        <div class="card" style="padding:28px">
          <div style="margin-bottom:20px">
            <h2 style="font-size:22px;font-weight:800;letter-spacing:-.015em;margin-bottom:6px"><?= t('write_us') ?></h2>
            <p class="text-soft" style="font-size:14px"><?= lang()==='uz_cyrillic' ? "Биз тез орада сизга жавоб берамиз" : "Biz tez orada sizga javob beramiz" ?></p>
          </div>

          <?php if ($success): ?>
            <div class="alert alert-success">
              <?= icon('check-circle', 18) ?>
              <div>
                <strong><?= lang()==='uz_cyrillic' ? "Раҳмат!" : "Rahmat!" ?></strong>
                <div style="font-size:13px"><?= lang()==='uz_cyrillic' ? "Хабарингиз муваффақиятли юборилди" : "Xabaringiz muvaffaqiyatli yuborildi" ?></div>
              </div>
            </div>
          <?php endif; ?>
          <?php if ($error): ?>
            <div class="alert alert-danger"><?= icon('x-circle', 18) ?> <?= e($error) ?></div>
          <?php endif; ?>

          <form method="post">
            <?= csrf_field() ?>
            <div class="form-group">
              <label class="form-label"><?= t('name') ?> <span style="color:var(--danger)">*</span></label>
              <div class="input-group">
                <span class="input-icon"><?= icon('user', 16) ?></span>
                <input type="text" name="name" class="form-control" required placeholder="<?= lang()==='uz_cyrillic' ? "Исмингиз" : "Ismingiz" ?>">
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div class="form-group">
                <label class="form-label">Email</label>
                <div class="input-group">
                  <span class="input-icon"><?= icon('mail', 16) ?></span>
                  <input type="email" name="email" class="form-control" placeholder="email@example.com">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label"><?= t('phone') ?></label>
                <div class="input-group">
                  <span class="input-icon"><?= icon('phone', 16) ?></span>
                  <input type="tel" name="phone" class="form-control" placeholder="+998 ...">
                </div>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label"><?= t('message') ?> <span style="color:var(--danger)">*</span></label>
              <textarea name="message" class="form-control" rows="5" required placeholder="<?= lang()==='uz_cyrillic' ? "Хабарингизни ёзинг..." : "Xabaringizni yozing..." ?>"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block"><?= icon('send', 16) ?> <?= t('send') ?></button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Map -->
<section class="section section-soft" style="padding-top:32px">
  <div class="container">
    <div class="card" style="padding:0;overflow:hidden;border-radius:16px">
      <iframe
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3045.7!2d71.1!3d40.43!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0!2zWWF5cGFu!5e0!3m2!1sen!2suz!4v1700000000000"
        width="100%" height="380" style="border:0;display:block;filter:grayscale(.2) contrast(1.05)" loading="lazy"></iframe>
    </div>
  </div>
</section>

<style>
@media (max-width: 880px){
  section .container > div[style*="grid-template-columns:1fr 1.2fr"]{
    grid-template-columns:1fr !important;gap:16px !important;
  }
  .card[style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr !important}
}
</style>

<?php render_footer(); ?>
