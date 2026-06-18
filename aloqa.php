<?php
/**
 * aloqa.php — STANDALONE contact page
 */
require_once __DIR__ . '/includes/bootstrap.php';

$success = false; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = t('csrf_invalid');
    } else {
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
                    audit('contact_form', "From: $name", 'info');
                } else {
                    $error = lang()==='uz_cyrillic' ? "Хабар юборишда хатолик" : "Xabar yuborishda xatolik";
                }
            }
        }
    }
}

$site_name = setting('site_name', SITE_NAME);
$site_phone = setting('site_phone');
$site_email = setting('site_email');
$site_address = lang()==='uz_cyrillic' ? setting('site_address_cyrillic') : setting('site_address');
$work_hours = setting('working_hours');
?><!DOCTYPE html>
<html lang="<?= lang() === 'uz_cyrillic' ? 'uz-Cyrl' : 'uz' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#3B82F6">
<title><?= e(t('contact')) ?> — <?= e($site_name) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(setting('site_logo', '/assets/images/logo.svg')) ?>">
<style>
<?= base_css() ?>

/* ===== ALOQA.PHP — contact page custom ===== */
.al-header{
  background:rgba(255,255,255,.85);backdrop-filter:saturate(180%) blur(20px);
  border-bottom:1px solid var(--border);position:sticky;top:0;z-index:50;
}
.al-nav{display:flex;align-items:center;justify-content:space-between;padding:14px 0;flex-wrap:wrap;gap:12px}
.al-logo{display:inline-flex;align-items:center;gap:10px;font-weight:800;color:var(--text);text-decoration:none;font-size:15px}
.al-logo .li{width:40px;height:40px;border-radius:11px;background:linear-gradient(135deg,var(--primary),#1E40AF);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px}
.al-menu{display:flex;gap:18px;list-style:none}
.al-menu a{color:var(--text-soft);font-size:14px;font-weight:500}
.al-menu a:hover,.al-menu a.active{color:var(--primary)}
.al-actions{display:flex;align-items:center;gap:8px}
.al-lang{display:inline-flex;background:var(--bg-mute);border-radius:8px;padding:3px;gap:2px}
.al-lang a{padding:4px 10px;border-radius:5px;font-size:11px;font-weight:700;color:var(--text-soft)}
.al-lang a.active{background:#fff;color:var(--primary)}
@media (max-width:880px){.al-menu{display:none}}

.al-hero{padding:64px 0 32px;background:linear-gradient(180deg,#EFF6FF,#fff);text-align:center}
.al-hero .eyebrow{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--primary);background:var(--primary-50);padding:6px 14px;border-radius:100px}
.al-hero h1{font-size:clamp(28px,5vw,44px);font-weight:800;letter-spacing:-.02em;margin:14px 0;color:var(--text)}
.al-hero p{color:var(--text-soft);font-size:clamp(15px,1.4vw,17px);max-width:560px;margin:0 auto;line-height:1.6}

.al-grid{display:grid;grid-template-columns:1fr 1.2fr;gap:24px;padding:48px 0}
@media (max-width:880px){.al-grid{grid-template-columns:1fr;padding:32px 0}}

.info-card{display:flex;gap:14px;padding:18px;background:#fff;border:1px solid var(--border);border-radius:14px;margin-bottom:12px;transition:all .2s}
.info-card:hover{border-color:var(--primary-200);box-shadow:0 4px 12px rgba(0,0,0,.04)}
.info-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.info-icon.blue{background:var(--primary-50);color:var(--primary)}
.info-icon.green{background:#D1FAE5;color:#065F46}
.info-icon.violet{background:#EDE9FE;color:#5B21B6}
.info-icon.amber{background:#FEF3C7;color:#92400E}
.info-card strong{display:block;font-size:13px;font-weight:700;color:var(--text);margin-bottom:4px}
.info-card p{color:var(--text-soft);font-size:13px;line-height:1.5;margin:0}
.info-card a{color:var(--primary)}

.social-card{background:linear-gradient(135deg,var(--primary-50),#fff);border:1px solid var(--primary-200);border-radius:14px;padding:18px;margin-top:8px}
.social-card h3{font-size:14px;font-weight:700;margin-bottom:10px;display:flex;align-items:center;gap:8px}
.social-pills{display:flex;flex-wrap:wrap;gap:6px}
.social-pill{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;background:#fff;border:1px solid var(--border);border-radius:100px;font-size:12px;font-weight:600;color:var(--text);text-decoration:none;transition:all .15s}
.social-pill:hover{border-color:var(--primary);color:var(--primary);transform:translateY(-1px)}

.contact-form{background:#fff;border:1px solid var(--border);border-radius:18px;padding:28px;box-shadow:0 4px 12px rgba(0,0,0,.04)}
.contact-form h2{font-size:22px;font-weight:800;letter-spacing:-.015em;margin-bottom:6px}
.contact-form .form-sub{color:var(--text-soft);font-size:13.5px;margin-bottom:20px}
.input-group{position:relative}
.input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-mute)}
.input-group .form-control{padding-left:42px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media (max-width:480px){.form-row{grid-template-columns:1fr}}
@media (max-width:880px){.contact-form{padding:20px}}

.al-map{padding:32px 0;background:var(--bg-soft)}
.al-map .card{padding:0;overflow:hidden;border-radius:18px;border:1px solid var(--border)}

.al-footer{background:linear-gradient(180deg,#0F172A,#1E293B);color:#CBD5E1;padding:32px 0;text-align:center;font-size:13px;margin-top:0}
.al-footer p{color:#94A3B8}
</style>
</head>
<body>

<header class="al-header">
  <div class="container al-nav">
    <a href="/" class="al-logo"><span class="li">VP</span><span><?= e($site_name) ?></span></a>
    <ul class="al-menu">
      <li><a href="/"><?= t('home') ?></a></li>
      <li><a href="/tariflar.php"><?= t('tariffs') ?></a></li>
      <li><a href="/blog.php"><?= t('blog') ?></a></li>
      <li><a href="/aloqa.php" class="active"><?= t('contact') ?></a></li>
    </ul>
    <div class="al-actions">
      <div class="al-lang">
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

<section class="al-hero">
  <div class="container">
    <div class="eyebrow"><?= icon('message', 12) ?> <?= lang()==='uz_cyrillic' ? "Биз билан боғланинг" : "Biz bilan bog'laning" ?></div>
    <h1><?= t('contact') ?></h1>
    <p><?= lang()==='uz_cyrillic' ? "Саволларингиз бўлса бизга ёзинг — биз доим тайёрмиз" : "Savollaringiz bo'lsa bizga yozing — biz doim tayyormiz" ?></p>
  </div>
</section>

<section>
  <div class="container al-grid">

    <!-- INFO -->
    <div>
      <h2 style="font-size:20px;font-weight:800;letter-spacing:-.015em;margin-bottom:14px"><?= lang()==='uz_cyrillic' ? "Алоқа маълумотлари" : "Aloqa ma'lumotlari" ?></h2>

      <?php if ($site_address): ?>
      <div class="info-card">
        <div class="info-icon blue"><?= icon('map-pin', 20) ?></div>
        <div><strong><?= t('address') ?></strong><p><?= e($site_address) ?></p></div>
      </div>
      <?php endif; ?>

      <?php if ($site_phone): ?>
      <div class="info-card">
        <div class="info-icon green"><?= icon('phone', 20) ?></div>
        <div><strong><?= t('phone') ?></strong><p><a href="tel:<?= e($site_phone) ?>"><?= e($site_phone) ?></a></p></div>
      </div>
      <?php endif; ?>

      <?php if ($site_email): ?>
      <div class="info-card">
        <div class="info-icon violet"><?= icon('mail', 20) ?></div>
        <div><strong>Email</strong><p><a href="mailto:<?= e($site_email) ?>"><?= e($site_email) ?></a></p></div>
      </div>
      <?php endif; ?>

      <?php if ($work_hours): ?>
      <div class="info-card">
        <div class="info-icon amber"><?= icon('clock', 20) ?></div>
        <div><strong><?= t('work_time') ?></strong><p><?= e($work_hours) ?></p></div>
      </div>
      <?php endif; ?>

      <div class="social-card">
        <h3><?= icon('sparkles', 14) ?> <?= lang()==='uz_cyrillic' ? "Ижтимоий тармоқлар" : "Ijtimoiy tarmoqlar" ?></h3>
        <div class="social-pills">
          <a href="<?= e(setting('telegram_url','#')) ?>" target="_blank" class="social-pill"><?= icon('telegram', 14) ?> Telegram</a>
          <a href="<?= e(setting('instagram_url','#')) ?>" target="_blank" class="social-pill"><?= icon('instagram', 14) ?> Instagram</a>
          <a href="<?= e(setting('youtube_url','#')) ?>" target="_blank" class="social-pill"><?= icon('youtube', 14) ?> YouTube</a>
          <a href="<?= e(setting('facebook_url','#')) ?>" target="_blank" class="social-pill"><?= icon('facebook', 14) ?> Facebook</a>
        </div>
      </div>
    </div>

    <!-- FORM -->
    <div>
      <div class="contact-form">
        <h2><?= t('write_us') ?></h2>
        <p class="form-sub"><?= lang()==='uz_cyrillic' ? "Биз тез орада сизга жавоб берамиз" : "Biz tez orada sizga javob beramiz" ?></p>

        <?php if ($success): ?>
          <div class="alert alert-success"><?= icon('check-circle', 18) ?>
            <div><strong><?= lang()==='uz_cyrillic' ? "Раҳмат!" : "Rahmat!" ?></strong>
              <div style="font-size:13px"><?= lang()==='uz_cyrillic' ? "Хабарингиз муваффақиятли юборилди" : "Xabaringiz muvaffaqiyatli yuborildi" ?></div></div>
          </div>
        <?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= icon('x-circle', 18) ?> <?= e($error) ?></div><?php endif; ?>

        <form method="post">
          <?= csrf_field() ?>
          <div class="form-group">
            <label class="form-label"><?= t('name') ?> <span style="color:var(--danger)">*</span></label>
            <div class="input-group">
              <span class="input-icon"><?= icon('user', 16) ?></span>
              <input type="text" name="name" class="form-control" required maxlength="150" placeholder="<?= lang()==='uz_cyrillic' ? "Исмингиз" : "Ismingiz" ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Email</label>
              <div class="input-group">
                <span class="input-icon"><?= icon('mail', 16) ?></span>
                <input type="email" name="email" class="form-control" maxlength="150" placeholder="email@example.com">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label"><?= t('phone') ?></label>
              <div class="input-group">
                <span class="input-icon"><?= icon('phone', 16) ?></span>
                <input type="tel" name="phone" class="form-control" maxlength="30" placeholder="+998 ...">
              </div>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label"><?= t('message') ?> <span style="color:var(--danger)">*</span></label>
            <textarea name="message" class="form-control" rows="5" required maxlength="2000" placeholder="<?= lang()==='uz_cyrillic' ? "Хабарингизни ёзинг..." : "Xabaringizni yozing..." ?>" style="min-height:120px"></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-lg btn-block"><?= icon('send', 16) ?> <?= t('send') ?></button>
        </form>
      </div>
    </div>

  </div>
</section>

<section class="al-map">
  <div class="container">
    <div class="card">
      <iframe
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3045.7!2d71.1!3d40.43!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0!2zWWF5cGFu!5e0!3m2!1sen!2suz!4v1700000000000"
        width="100%" height="380" style="border:0;display:block;filter:grayscale(.2) contrast(1.05)" loading="lazy"></iframe>
    </div>
  </div>
</section>

<footer class="al-footer">
  <div class="container">
    <p>© <?= date('Y') ?> <?= e($site_name) ?>. <?= t('all_rights') ?>.</p>
  </div>
</footer>

</body></html>
