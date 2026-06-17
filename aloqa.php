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

<section class="hero" style="padding:60px 0">
  <div class="container text-center">
    <h1 style="font-size:42px;font-weight:800;margin-bottom:14px;color:var(--text)"><?= t('contact') ?></h1>
    <p style="color:var(--text-soft)"><?= lang()==='uz_cyrillic' ? 'Биз билан боғланинг — савол-жавоблар учун доим тайёрмиз' : 'Biz bilan bog\'laning — savol-javoblar uchun doim tayyormiz' ?></p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid-2">
      <!-- Kontakt ma'lumotlar -->
      <div>
        <h2 style="font-size:24px;font-weight:700;margin-bottom:20px"><?= lang()==='uz_cyrillic' ? 'Биз билан боғланинг' : 'Biz bilan bog\'laning' ?></h2>

        <div class="card mb-2" style="display:flex;gap:14px;align-items:flex-start">
          <div class="service-icon" style="width:46px;height:46px;font-size:20px;flex-shrink:0;margin:0">📍</div>
          <div>
            <strong><?= t('address') ?></strong>
            <p style="color:var(--text-soft);font-size:14px;margin-top:4px"><?= e(lang()==='uz_cyrillic' ? setting('site_address_cyrillic') : setting('site_address')) ?></p>
          </div>
        </div>
        <div class="card mb-2" style="display:flex;gap:14px;align-items:flex-start">
          <div class="service-icon" style="width:46px;height:46px;font-size:20px;flex-shrink:0;margin:0">📞</div>
          <div>
            <strong><?= t('phone') ?></strong>
            <p style="margin-top:4px"><a href="tel:<?= e(setting('site_phone')) ?>"><?= e(setting('site_phone')) ?></a></p>
          </div>
        </div>
        <div class="card mb-2" style="display:flex;gap:14px;align-items:flex-start">
          <div class="service-icon" style="width:46px;height:46px;font-size:20px;flex-shrink:0;margin:0">✉️</div>
          <div>
            <strong>Email</strong>
            <p style="margin-top:4px"><a href="mailto:<?= e(setting('site_email')) ?>"><?= e(setting('site_email')) ?></a></p>
          </div>
        </div>
        <div class="card" style="display:flex;gap:14px;align-items:flex-start">
          <div class="service-icon" style="width:46px;height:46px;font-size:20px;flex-shrink:0;margin:0">🕐</div>
          <div>
            <strong><?= t('work_time') ?></strong>
            <p style="color:var(--text-soft);font-size:14px;margin-top:4px"><?= e(setting('working_hours')) ?></p>
          </div>
        </div>
      </div>

      <!-- Form -->
      <div>
        <h2 style="font-size:24px;font-weight:700;margin-bottom:20px"><?= t('write_us') ?></h2>
        <div class="card">
          <?php if ($success): ?>
            <div class="alert alert-success"><?= lang()==='uz_cyrillic' ? 'Хабарингиз муваффақиятли юборилди!' : 'Xabaringiz muvaffaqiyatli yuborildi!' ?></div>
          <?php endif; ?>
          <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
          <?php endif; ?>

          <form method="post">
      <?= csrf_field() ?>
            <div class="form-group">
              <label class="form-label"><?= t('name') ?> *</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="grid-2" style="gap:14px">
              <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control">
              </div>
              <div class="form-group">
                <label class="form-label"><?= t('phone') ?></label>
                <input type="tel" name="phone" class="form-control" placeholder="+998 ...">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label"><?= t('message') ?> *</label>
              <textarea name="message" class="form-control" rows="5" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><?= t('send') ?></button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Xarita -->
<section class="section section-soft">
  <div class="container">
    <div class="card" style="padding:0;overflow:hidden;border-radius:var(--radius-lg)">
      <iframe
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3045.7!2d71.1!3d40.43!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0!2zWWF5cGFu!5e0!3m2!1sen!2suz!4v1700000000000"
        width="100%" height="400" style="border:0;display:block" loading="lazy"></iframe>
    </div>
  </div>
</section>

<?php render_footer(); ?>
