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
    <div class="card">
      <h3 style="font-size:18px;font-weight:700;margin-bottom:14px">🖼️ <?= lang()==='uz_cyrillic' ? 'Логотипни юклаш' : 'Logotipni yuklash' ?></h3>
      <div class="text-center mb-3">
        <?php if (setting('site_logo')): ?>
          <img src="<?= e(setting('site_logo')) ?>" style="max-height:100px;margin:0 auto">
        <?php else: ?>
          <div style="height:100px;background:var(--bg-soft);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--text-mute)">No logo</div>
        <?php endif; ?>
      </div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="logo">
        <div class="form-group"><input type="file" name="logo" accept="image/*" class="form-control" required></div>
        <button class="btn btn-primary btn-block"><?= lang()==='uz_cyrillic' ? 'Юклаш' : 'Yuklash' ?></button>
      </form>
    </div>
    <div class="card">
      <h3 style="font-size:18px;font-weight:700;margin-bottom:14px">🖼️ <?= lang()==='uz_cyrillic' ? 'Баннерни юклаш' : 'Bannerni yuklash' ?></h3>
      <div class="text-center mb-3">
        <?php if (setting('site_banner')): ?>
          <img src="<?= e(setting('site_banner')) ?>" style="max-height:140px;border-radius:8px">
        <?php else: ?>
          <div style="height:140px;background:var(--bg-soft);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--text-mute)">No banner</div>
        <?php endif; ?>
      </div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="banner">
        <div class="form-group"><input type="file" name="banner" accept="image/*" class="form-control" required></div>
        <button class="btn btn-primary btn-block"><?= lang()==='uz_cyrillic' ? 'Юклаш' : 'Yuklash' ?></button>
      </form>
    </div>
  </div>

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
