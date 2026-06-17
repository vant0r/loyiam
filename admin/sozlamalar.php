<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$msg = '';

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // LOGO yuklash
    if ($action === 'logo' && !empty($_FILES['logo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','svg','webp'])) {
            $name = 'logo.' . $ext;
            $dest = BASE_PATH . '/assets/images/' . $name;
            @mkdir(dirname($dest), 0755, true);
            if (@move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                db()->execute("INSERT INTO settings (setting_key,setting_value,setting_type,setting_group) VALUES ('site_logo',?,'image','general')
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", ['/assets/images/' . $name]);
                $msg = lang()==='uz_cyrillic' ? 'Логотип юкланди' : 'Logotip yuklandi';
            }
        }
    }
    // BANNER yuklash
    elseif ($action === 'banner' && !empty($_FILES['banner']['name'])) {
        $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','webp'])) {
            $name = 'banner.' . $ext;
            $dest = BASE_PATH . '/assets/images/' . $name;
            @mkdir(dirname($dest), 0755, true);
            if (@move_uploaded_file($_FILES['banner']['tmp_name'], $dest)) {
                db()->execute("INSERT INTO settings (setting_key,setting_value,setting_type,setting_group) VALUES ('site_banner',?,'image','general')
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", ['/assets/images/' . $name]);
                $msg = lang()==='uz_cyrillic' ? 'Баннер юкланди' : 'Banner yuklandi';
            }
        }
    }
    // Default ticket image
    elseif (in_array($action, ['default_ticket_image','default_question_image']) && !empty($_FILES['image']['name'])) {
        $up = Security::upload_image($_FILES['image'], $action);
        if ($up['ok']) {
            db()->execute("INSERT INTO settings (setting_key,setting_value,setting_type,setting_group) VALUES (?,?,'image','general')
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                           [$action, $up['url']]);
            $msg = 'Standart rasm yangilandi';
        } else {
            $msg = $up['error'] ?? 'Yuklash xatosi';
        }
    }
    // Boshqa sozlamalar (group bo'yicha)
    elseif ($action === 'save_group') {
        $group = $_POST['group'] ?? '';
        foreach ($_POST as $k => $v) {
            if (in_array($k, ['action','group'])) continue;
            if (is_array($v)) continue;
            db()->execute("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?,?,?)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_group = VALUES(setting_group)",
                          [$k, $v, $group]);
        }
        $msg = lang()==='uz_cyrillic' ? 'Сақланди' : 'Saqlandi';
    }
}

// Cache resetlash
flush_settings_cache();

$tab = $_GET['tab'] ?? 'general';

render_head(t('settings'));
?>
<div class="layout">
<?php render_sidebar('admin','settings'); ?>
<main class="main">
  <div class="page-header">
    <div class="page-title">⚙️ <?= t('settings') ?></div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <!-- Tabs -->
  <div class="card mb-3" style="padding:8px;display:flex;gap:6px;overflow-x:auto;flex-wrap:wrap">
    <?php
      $tabs = [
        'general' => lang()==='uz_cyrillic' ? '🏠 Умумий' : '🏠 Umumiy',
        'logo'    => lang()==='uz_cyrillic' ? '🖼️ Лого/Баннер' : '🖼️ Logo/Banner',
        'contact' => lang()==='uz_cyrillic' ? '📞 Контакт' : '📞 Kontakt',
        'social'  => '🌐 Social',
        'payment' => lang()==='uz_cyrillic' ? '💳 Тўлов' : '💳 To\'lov',
        'seo'     => '🔍 SEO',
        'telegram'=> '✈️ Telegram',
      ];
      foreach ($tabs as $key => $label):
    ?>
      <a href="?tab=<?= $key ?>" class="btn <?= $tab===$key?'btn-primary':'btn-light' ?> btn-sm"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($tab === 'general'): ?>
  <div class="card">
    <h3 style="font-size:18px;font-weight:700;margin-bottom:18px"><?= lang()==='uz_cyrillic' ? 'Умумий созламалар' : 'Umumiy sozlamalar' ?></h3>
    <form method="post">
      <input type="hidden" name="action" value="save_group">
      <input type="hidden" name="group" value="general">
      <div class="form-group"><label class="form-label">Site Name</label><input type="text" name="site_name" class="form-control" value="<?= e(setting('site_name')) ?>"></div>
      <div class="grid-3" style="gap:14px">
        <div class="form-group"><label class="form-label">Hero stat: Users</label><input type="text" name="hero_stats_users" class="form-control" value="<?= e(setting('hero_stats_users')) ?>"></div>
        <div class="form-group"><label class="form-label">Hero stat: Questions</label><input type="text" name="hero_stats_questions" class="form-control" value="<?= e(setting('hero_stats_questions')) ?>"></div>
        <div class="form-group"><label class="form-label">Hero stat: Success %</label><input type="text" name="hero_stats_success" class="form-control" value="<?= e(setting('hero_stats_success')) ?>"></div>
      </div>
      <button class="btn btn-primary"><?= t('save') ?></button>
    </form>
  </div>

  <?php elseif ($tab === 'logo'): ?>
  <div class="grid-2">
    <!-- LOGO -->
    <div class="card">
      <div class="setting-section-head">
        <h3>🎨 <?= lang()==='uz_cyrillic' ? 'Логотипни юклаш' : 'Logotipni yuklash' ?></h3>
        <span class="badge badge-info">Barcha qurilmalarda</span>
      </div>
      <div class="logo-preview">
        <?php if (setting('site_logo')): ?>
          <img src="<?= e(setting('site_logo')) ?>" alt="Logo">
        <?php else: ?>
          <div class="logo-empty">
            <?= icon('image', 32) ?>
            <span>Logo yo'q</span>
          </div>
        <?php endif; ?>
      </div>
      <form method="post" enctype="multipart/form-data" id="logoForm">
        <input type="hidden" name="action" value="logo">
        <div class="image-uploader" id="logoDrop">
          <input type="file" name="logo" accept="image/*" id="logoInput" hidden required>
          <div class="image-uploader-empty">
            <?= icon('upload', 32) ?>
            <strong>Logo tanlash</strong>
            <small>SVG, PNG (shaffof orqa fon)</small>
          </div>
        </div>
        <button class="btn btn-primary btn-block mt-2"><?= icon('upload', 16) ?> Yuklash</button>
      </form>
      <div class="form-help mt-2">
        <strong>Tavsiya:</strong> SVG yoki shaffof PNG (200×60px proporsiya), max 1MB
      </div>
    </div>

    <!-- BANNER -->
    <div class="card">
      <div class="setting-section-head">
        <h3>🌅 <?= lang()==='uz_cyrillic' ? 'Баннерни юклаш' : 'Bannerni yuklash' ?></h3>
        <span class="badge badge-warning">Faqat desktop/tablet</span>
      </div>
      <div class="banner-preview">
        <?php if (setting('site_banner')): ?>
          <img src="<?= e(setting('site_banner')) ?>" alt="Banner">
        <?php else: ?>
          <div class="banner-empty">
            <?= icon('image', 48) ?>
            <span>Banner yo'q</span>
          </div>
        <?php endif; ?>
      </div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="banner">
        <div class="image-uploader" id="bannerDrop">
          <input type="file" name="banner" accept="image/*" id="bannerInput" hidden required>
          <div class="image-uploader-empty">
            <?= icon('upload', 32) ?>
            <strong>Banner tanlash</strong>
            <small>JPG, PNG, WEBP (1200×400px tavsiya)</small>
          </div>
        </div>
        <button class="btn btn-primary btn-block mt-2"><?= icon('upload', 16) ?> Yuklash</button>
      </form>
      <div class="form-help mt-2">
        <strong>Eslatma:</strong> Banner mobil qurilmalarda yashirinadi
      </div>
    </div>

    <!-- DEFAULT IMAGES -->
    <div class="card" style="grid-column:1 / -1">
      <div class="setting-section-head">
        <h3>🖼️ Standart rasmlar</h3>
        <span class="badge badge-mute">Fallback</span>
      </div>
      <p class="text-soft mb-3" style="font-size:13px">
        Bilet yoki savolga rasm yuklanmasa, mana shu standart rasmlar ko'rsatiladi
      </p>
      <div class="grid-2">
        <div>
          <h4 style="font-size:14px;margin-bottom:8px">Standart bilet rasmi</h4>
          <div class="default-img-preview">
            <img src="<?= e(setting('default_ticket_image', '/assets/images/default-ticket.svg')) ?>" alt="">
          </div>
          <form method="post" enctype="multipart/form-data" class="mt-2">
            <input type="hidden" name="action" value="default_ticket_image">
            <input type="file" name="image" accept="image/*" class="form-control" required>
            <button class="btn btn-light btn-sm btn-block mt-1">Yangilash</button>
          </form>
        </div>
        <div>
          <h4 style="font-size:14px;margin-bottom:8px">Standart savol rasmi</h4>
          <div class="default-img-preview">
            <img src="<?= e(setting('default_question_image', '/assets/images/default-question.svg')) ?>" alt="">
          </div>
          <form method="post" enctype="multipart/form-data" class="mt-2">
            <input type="hidden" name="action" value="default_question_image">
            <input type="file" name="image" accept="image/*" class="form-control" required>
            <button class="btn btn-light btn-sm btn-block mt-1">Yangilash</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <style>
    .setting-section-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:8px}
    .setting-section-head h3{font-size:18px;font-weight:700;margin:0}
    .logo-preview, .banner-preview, .default-img-preview{
      background:repeating-conic-gradient(#F1F5F9 0% 25%, #fff 0% 50%) 50%/16px 16px;
      border-radius:var(--r-lg);padding:24px;margin-bottom:14px;text-align:center;
      border:1px solid var(--border);min-height:80px;display:flex;align-items:center;justify-content:center
    }
    .banner-preview{padding:0;overflow:hidden;aspect-ratio:3/1}
    .default-img-preview{padding:0;aspect-ratio:16/9;overflow:hidden;background:var(--bg-soft)}
    .logo-preview img{max-height:64px;max-width:100%}
    .banner-preview img, .default-img-preview img{width:100%;height:100%;object-fit:cover}
    .logo-empty, .banner-empty{display:flex;flex-direction:column;align-items:center;gap:6px;color:var(--text-mute)}
    .image-uploader{position:relative;border:2px dashed var(--border);border-radius:var(--r-lg);
      padding:24px;text-align:center;cursor:pointer;background:var(--bg-soft);transition:all .25s}
    .image-uploader:hover{border-color:var(--primary);background:var(--primary-50)}
    .image-uploader.is-dragover{border-color:var(--primary);background:var(--primary-100);transform:scale(1.01)}
    .image-uploader-empty{display:flex;flex-direction:column;align-items:center;gap:4px;color:var(--text-soft)}
    .image-uploader-empty strong{font-size:14px;color:var(--text)}
    .image-uploader-empty small{font-size:11px;color:var(--text-mute)}
  </style>
  <script>
    // Drag&drop for both
    ['logoDrop','bannerDrop'].forEach(id => {
      const drop = document.getElementById(id);
      if (!drop) return;
      const input = drop.querySelector('input[type=file]');
      drop.addEventListener('click', () => input.click());
      drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('is-dragover'); });
      drop.addEventListener('dragleave', () => drop.classList.remove('is-dragover'));
      drop.addEventListener('drop', e => {
        e.preventDefault(); drop.classList.remove('is-dragover');
        if (e.dataTransfer.files[0]) {
          input.files = e.dataTransfer.files;
          drop.querySelector('strong').textContent = input.files[0].name;
        }
      });
      input.addEventListener('change', () => {
        if (input.files[0]) drop.querySelector('strong').textContent = input.files[0].name;
      });
    });
  </script>

  <?php elseif ($tab === 'contact'): ?>
  <div class="card">
    <h3 style="font-size:18px;font-weight:700;margin-bottom:18px"><?= lang()==='uz_cyrillic' ? 'Контакт' : 'Kontakt' ?></h3>
    <form method="post">
      <input type="hidden" name="action" value="save_group"><input type="hidden" name="group" value="contact">
      <div class="grid-2" style="gap:14px">
        <div class="form-group"><label class="form-label">Telefon</label><input type="text" name="site_phone" class="form-control" value="<?= e(setting('site_phone')) ?>"></div>
        <div class="form-group"><label class="form-label">Email</label><input type="email" name="site_email" class="form-control" value="<?= e(setting('site_email')) ?>"></div>
      </div>
      <div class="form-group"><label class="form-label">Manzil (Lotin)</label><input type="text" name="site_address" class="form-control" value="<?= e(setting('site_address')) ?>"></div>
      <div class="form-group"><label class="form-label">Манзил (Кирилл)</label><input type="text" name="site_address_cyrillic" class="form-control" value="<?= e(setting('site_address_cyrillic')) ?>"></div>
      <div class="form-group"><label class="form-label">Ish vaqti</label><input type="text" name="working_hours" class="form-control" value="<?= e(setting('working_hours')) ?>"></div>
      <button class="btn btn-primary"><?= t('save') ?></button>
    </form>
  </div>

  <?php elseif ($tab === 'social'): ?>
  <div class="card">
    <h3 style="font-size:18px;font-weight:700;margin-bottom:18px">Ijtimoiy tarmoqlar</h3>
    <form method="post">
      <input type="hidden" name="action" value="save_group"><input type="hidden" name="group" value="social">
      <div class="grid-2" style="gap:14px">
        <div class="form-group"><label class="form-label">Telegram</label><input type="url" name="telegram_url" class="form-control" value="<?= e(setting('telegram_url')) ?>"></div>
        <div class="form-group"><label class="form-label">Instagram</label><input type="url" name="instagram_url" class="form-control" value="<?= e(setting('instagram_url')) ?>"></div>
        <div class="form-group"><label class="form-label">YouTube</label><input type="url" name="youtube_url" class="form-control" value="<?= e(setting('youtube_url')) ?>"></div>
        <div class="form-group"><label class="form-label">Facebook</label><input type="url" name="facebook_url" class="form-control" value="<?= e(setting('facebook_url')) ?>"></div>
      </div>
      <button class="btn btn-primary"><?= t('save') ?></button>
    </form>
  </div>

  <?php elseif ($tab === 'payment'): ?>
  <div class="card">
    <h3 style="font-size:18px;font-weight:700;margin-bottom:18px"><?= lang()==='uz_cyrillic' ? "Тўлов созламалари" : "To'lov sozlamalari" ?></h3>
    <form method="post">
      <input type="hidden" name="action" value="save_group"><input type="hidden" name="group" value="payment">
      <div class="grid-2" style="gap:14px">
        <div class="form-group"><label class="form-label">Karta raqami</label><input type="text" name="card_number" class="form-control" value="<?= e(setting('card_number')) ?>"></div>
        <div class="form-group"><label class="form-label">Karta egasi</label><input type="text" name="card_holder" class="form-control" value="<?= e(setting('card_holder')) ?>"></div>
      </div>
      <h4 style="font-weight:700;margin:14px 0">Click</h4>
      <div class="grid-2" style="gap:14px">
        <div class="form-group"><label class="form-label">Merchant ID</label><input type="text" name="click_merchant_id" class="form-control" value="<?= e(setting('click_merchant_id')) ?>"></div>
        <div class="form-group"><label class="form-label">Service ID</label><input type="text" name="click_service_id" class="form-control" value="<?= e(setting('click_service_id')) ?>"></div>
      </div>
      <h4 style="font-weight:700;margin:14px 0">Payme</h4>
      <div class="form-group"><label class="form-label">Merchant ID</label><input type="text" name="payme_merchant_id" class="form-control" value="<?= e(setting('payme_merchant_id')) ?>"></div>
      <button class="btn btn-primary"><?= t('save') ?></button>
    </form>
  </div>

  <?php elseif ($tab === 'seo'): ?>
  <div class="card">
    <h3 style="font-size:18px;font-weight:700;margin-bottom:18px">SEO</h3>
    <form method="post">
      <input type="hidden" name="action" value="save_group"><input type="hidden" name="group" value="seo">
      <div class="form-group"><label class="form-label">Title</label><input type="text" name="seo_title" class="form-control" value="<?= e(setting('seo_title')) ?>"></div>
      <div class="form-group"><label class="form-label">Description</label><textarea name="seo_description" class="form-control" rows="2"><?= e(setting('seo_description')) ?></textarea></div>
      <div class="form-group"><label class="form-label">Keywords</label><input type="text" name="seo_keywords" class="form-control" value="<?= e(setting('seo_keywords')) ?>"></div>
      <button class="btn btn-primary"><?= t('save') ?></button>
    </form>
  </div>

  <?php elseif ($tab === 'telegram'):
    $webhookInfo = null;
    if (setting('telegram_bot_token')) {
        require_once __DIR__ . '/../telegram/api.php';
        if (isset($_POST['set_webhook'])) {
            $r = TelegramAPI::setWebhook(SITE_URL . '/telegram/bot.php');
            $webhookMsg = $r['ok'] ? '✅ Webhook o\'rnatildi' : '❌ ' . ($r['description'] ?? 'Xatolik');
        }
        $webhookInfo = TelegramAPI::getWebhookInfo();
    }
  ?>
  <div class="card">
    <h3 style="font-size:18px;font-weight:700;margin-bottom:18px">✈️ Telegram Bot</h3>

    <?php if (!empty($webhookMsg)): ?>
      <div class="alert alert-info"><?= e($webhookMsg) ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="action" value="save_group"><input type="hidden" name="group" value="telegram">
      <div class="form-group">
        <label class="form-label">Bot Token</label>
        <input type="text" name="telegram_bot_token" class="form-control" value="<?= e(setting('telegram_bot_token')) ?>" placeholder="123456:ABC-DEF...">
        <div class="form-help">@BotFather'dan oling: https://t.me/BotFather</div>
      </div>
      <div class="form-group">
        <label class="form-label">Admin Chat ID</label>
        <input type="text" name="telegram_admin_chat_id" class="form-control" value="<?= e(setting('telegram_admin_chat_id')) ?>" placeholder="123456789">
        <div class="form-help">Sizning Telegram chat ID. @userinfobot orqali oling.</div>
      </div>
      <button class="btn btn-primary"><?= t('save') ?></button>
    </form>

    <?php if (setting('telegram_bot_token')): ?>
    <hr style="margin:24px 0;border:none;border-top:1px solid var(--border)">
    <h4 style="font-weight:700;margin-bottom:14px">🔗 Webhook</h4>
    <div class="card" style="background:var(--bg-soft);font-family:monospace;font-size:13px">
      <strong>URL:</strong> <code><?= e(SITE_URL) ?>/telegram/bot.php</code><br>
      <?php if ($webhookInfo && !empty($webhookInfo['ok'])):
        $info = $webhookInfo['result']; ?>
        <strong>Status:</strong>
        <?php if (!empty($info['url'])): ?>
          <span class="badge badge-success">Active</span> — <code><?= e($info['url']) ?></code><br>
          <strong>Pending updates:</strong> <?= (int)($info['pending_update_count'] ?? 0) ?><br>
          <?php if (!empty($info['last_error_message'])): ?>
            <strong style="color:var(--danger)">Last error:</strong> <?= e($info['last_error_message']) ?><br>
          <?php endif; ?>
        <?php else: ?>
          <span class="badge badge-warning">Not set</span>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <form method="post" style="margin-top:14px">
      <input type="hidden" name="set_webhook" value="1">
      <button class="btn btn-success">🔗 Webhook'ni o'rnatish</button>
    </form>
    <?php endif; ?>

    <div class="alert alert-info" style="margin-top:18px">
      <strong>📋 Bot komandalari (BotFather'ga yuborish):</strong>
      <pre style="margin-top:8px;font-size:12px;background:rgba(0,0,0,.05);padding:8px;border-radius:4px">start - Boshlash
tarif - Tariflar ro'yxati
test - Testni boshlash
aloqa - Aloqa ma'lumotlari
yordam - Yordam</pre>
    </div>
  </div>
  <?php endif; ?>
</main>
</div>
</body></html>
