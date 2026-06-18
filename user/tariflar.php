<?php
require_once __DIR__ . '/../includes/bootstrap.php';
auth_class();
require_login();

$u = current_user();
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';
$msg = ''; $err = '';

// To'lov so'rovi yaratish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    if (!csrf_check()) {
        $err = t('csrf_invalid');
    } else {
        $tariff_id = (int)$_POST['tariff_id'];
        $method    = in_array($_POST['method'] ?? 'manual', ['manual','click','payme']) ? $_POST['method'] : 'manual';
        $tariff    = db()->fetch("SELECT * FROM tariffs WHERE id=? AND status='active'", [$tariff_id]);

        if (!$tariff || $tariff['price'] <= 0) {
            $err = lang()==='uz_cyrillic' ? "Тариф топилмади" : "Tarif topilmadi";
        } else {
            // Screenshot upload (manual uchun majburiy)
            $screenshot = null;
            if (!empty($_FILES['screenshot']['name'])) {
                $up = Security::upload_image($_FILES['screenshot'], 'pay_'.$u['id']);
                if ($up['ok']) {
                    $screenshot = $up['url'];
                    @chmod(BASE_PATH . $up['url'], 0644);
                } else {
                    $err = $up['error'];
                }
            }

            if ($method === 'manual' && !$screenshot) {
                $err = lang()==='uz_cyrillic'
                    ? "Чек скриншотини юкланг"
                    : "Chek skrinshotini yuklang";
            }

            if (!$err) {
                db()->execute(
                    "INSERT INTO payments (user_id, tariff_id, amount, method, screenshot, status, note)
                     VALUES (?,?,?,?,?,'pending',?)",
                    [$u['id'], $tariff_id, $tariff['price'], $method, $screenshot,
                     "Foydalanuvchi tarif so'radi"]);
                $payment_id = (int)db()->lastInsertId();

                // Notification — admin'larga
                Notify::sendToAdmins('payment_pending',
                    "Yangi to'lov: " . $tariff['name_latin'],
                    "{$u['first_name']} {$u['last_name']} — " . money($tariff['price']) . " so'm",
                    ['link' => '/admin/tolovlar.php', 'icon' => 'card']);

                // Foydalanuvchiga
                Notify::send($u['id'], 'payment_pending',
                    lang()==='uz_cyrillic' ? "Тўлов сўрови юборилди" : "To'lov so'rovi yuborildi",
                    lang()==='uz_cyrillic'
                        ? "Админ тасдиқлашини кутинг (1-24 соат)"
                        : "Admin tasdiqlashini kuting (1-24 soat)",
                    ['link' => '/user/tariflar.php', 'icon' => 'clock']);

                // Telegram'ga ham xabar (admin chat'ga)
                if (setting('telegram_admin_chat_id')) {
                    @require_once __DIR__ . '/../telegram/api.php';
                    if (class_exists('TelegramAPI')) {
                        $msg_tg = "💰 <b>Yangi to'lov</b>\n\n"
                                . "👤 " . htmlspecialchars($u['first_name'].' '.$u['last_name']) . "\n"
                                . "📞 " . htmlspecialchars($u['phone'] ?? '—') . "\n"
                                . "💎 " . htmlspecialchars($tariff['name_latin']) . "\n"
                                . "💵 " . number_format($tariff['price'], 0, '.', ' ') . " so'm\n"
                                . "🆔 #$payment_id\n"
                                . "🔗 " . SITE_URL . "/admin/tolovlar.php";

                        $kbd = ['inline_keyboard' => [[
                            ['text' => '✅ Tasdiqlash', 'callback_data' => "approve:$payment_id"],
                            ['text' => '❌ Rad etish',  'callback_data' => "reject:$payment_id"],
                        ]]];

                        if ($screenshot) {
                            TelegramAPI::sendPhoto((int)setting('telegram_admin_chat_id'),
                                SITE_URL . $screenshot, $msg_tg,
                                ['reply_markup' => json_encode($kbd)]);
                        } else {
                            TelegramAPI::notifyAdmin($msg_tg, ['reply_markup' => json_encode($kbd)]);
                        }
                    }
                }

                audit('payment_created', "Tariff: {$tariff['name_latin']} ({$method})");

                // Click yoki Payme avtomatik (agar sozlangan bo'lsa)
                if ($method === 'click' && setting('click_merchant_id')) {
                    @require_once __DIR__ . '/../includes/payments/click.php';
                    if (class_exists('ClickPayment')) {
                        header('Location: ' . ClickPayment::build_payment_url($payment_id, (float)$tariff['price']));
                        exit;
                    }
                }
                if ($method === 'payme' && setting('payme_merchant_id')) {
                    @require_once __DIR__ . '/../includes/payments/payme.php';
                    if (class_exists('PaymePayment')) {
                        header('Location: ' . PaymePayment::build_payment_url($payment_id, (float)$tariff['price']));
                        exit;
                    }
                }

                $msg = lang()==='uz_cyrillic'
                    ? "✓ Тўлов сўрови юборилди! Админ кўриб чиқади."
                    : "✓ To'lov so'rovi yuborildi! Admin ko'rib chiqadi.";
            }
        }
    }
}

$tariffs = db()->fetchAll("SELECT * FROM tariffs WHERE status='active' ORDER BY sort_order");
$payments = db()->fetchAll(
    "SELECT p.*, t.name_$lang_field tname FROM payments p
     LEFT JOIN tariffs t ON p.tariff_id=t.id
     WHERE p.user_id=? ORDER BY p.created_at DESC LIMIT 20", [$u['id']]);
$current_tariff = $u['tariff_id'] ? db()->fetch("SELECT * FROM tariffs WHERE id=?", [$u['tariff_id']]) : null;

$click_enabled = !!setting('click_merchant_id');
$payme_enabled = !!setting('payme_merchant_id');

$selectedTariff = (int)($_GET['tariff'] ?? 0);

render_head(t('tariffs'));
?>
<div class="layout">
<?= panel_sidebar('user', 'tariffs') ?>
<main class="main">
  <div class="page-header">
    <div class="page-title"><?= icon('gem', 28) ?> <?= t('tariffs') ?></div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-success"><?= icon('check-circle', 20) ?> <span><?= e($msg) ?></span></div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="alert alert-danger"><?= icon('x-circle', 20) ?> <span><?= e($err) ?></span></div>
  <?php endif; ?>

  <!-- Joriy tarif yoki Bepul -->
  <?php if ($current_tariff):
    $expDays = $u['tariff_expires_at'] ? max(0, (strtotime($u['tariff_expires_at']) - time()) / 86400) : 0;
  ?>
  <div class="current-tariff-banner mb-3">
    <div class="ct-content">
      <div class="ct-icon"><?= icon('gem', 32) ?></div>
      <div class="ct-info">
        <div class="ct-label"><?= t('current_tariff') ?></div>
        <h2 class="ct-name"><?= e($current_tariff['name_'.$lang_field]) ?></h2>
        <?php if ($u['tariff_expires_at']): ?>
        <div class="ct-expires">
          <?= icon('clock', 14) ?>
          <span><?= t('expires_at') ?>: <strong><?= date('d.m.Y', strtotime($u['tariff_expires_at'])) ?></strong></span>
          <span class="badge" style="background:rgba(255,255,255,.2);color:#fff;margin-left:8px">
            <?= floor($expDays) ?> <?= t('days') ?>
          </span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tariflar -->
  <div class="grid-3 mb-3 stagger">
    <?php foreach ($tariffs as $tariff):
      $features = explode('|', $tariff['features_'.$lang_field] ?? '');
      $isCurrent = $current_tariff && $current_tariff['id'] == $tariff['id'];
    ?>
    <div class="card pricing-card <?= $tariff['is_popular']?'popular':'' ?> <?= $isCurrent?'is-current':'' ?>"
         <?= $isCurrent ? 'style="border-color:var(--success)"' : '' ?>>
      <?php if ($isCurrent): ?>
        <div class="pricing-badge" style="background:var(--success)">
          <?= icon('check', 12) ?> <?= t('active') ?>
        </div>
      <?php elseif ($tariff['is_popular']): ?>
        <div class="pricing-badge"><?= icon('star', 12) ?> <?= t('popular') ?></div>
      <?php endif; ?>
      <h3><?= e($tariff['name_'.$lang_field]) ?></h3>
      <p class="pricing-desc"><?= e($tariff['description_'.$lang_field]) ?></p>
      <div class="pricing-price">
        <?php if ($tariff['price']==0): ?><?= t('free') ?>
        <?php else: ?><?= money($tariff['price']) ?> <small><?= t('soum') ?></small><?php endif; ?>
      </div>
      <ul class="pricing-features">
        <?php foreach ($features as $f): if (trim($f)) echo '<li>'.e(trim($f)).'</li>'; endforeach; ?>
      </ul>
      <?php if ($tariff['price'] > 0 && !$isCurrent): ?>
        <button class="btn btn-primary btn-block" onclick="payOpen(<?= $tariff['id'] ?>, '<?= e(addslashes($tariff['name_'.$lang_field])) ?>', <?= $tariff['price'] ?>)">
          <?= icon('arrow-right', 16) ?> <?= t('choose_plan') ?>
        </button>
      <?php elseif ($isCurrent): ?>
        <button class="btn btn-success btn-block" disabled>
          <?= icon('check', 16) ?> <?= t('active') ?>
        </button>
      <?php else: ?>
        <button class="btn btn-light btn-block" disabled><?= t('free') ?></button>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- To'lov tarixi -->
  <div class="card" style="padding:0;overflow:hidden">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border)">
      <h3 style="font-size:16px;font-weight:700;margin:0;display:flex;align-items:center;gap:10px">
        <?= icon('logs', 20) ?> <?= t('pay_history') ?>
      </h3>
    </div>
    <?php if (empty($payments)): ?>
      <div class="empty-state">
        <?= icon('card', 48) ?>
        <p class="mt-2 text-soft" style="font-size:14px"><?= t('no_payments') ?></p>
      </div>
    <?php else: ?>
    <div class="payment-list">
      <?php foreach ($payments as $p):
        $cls = ['pending'=>'warning','approved'=>'success','rejected'=>'danger','refunded'=>'mute'][$p['status']] ?? 'mute';
        $icn = ['pending'=>'clock','approved'=>'check-circle','rejected'=>'x-circle','refunded'=>'refresh'][$p['status']] ?? 'help';
      ?>
      <div class="payment-item">
        <div class="payment-icon <?= $cls ?>"><?= icon($icn, 22) ?></div>
        <div class="payment-body">
          <div class="payment-title"><?= e($p['tname'] ?? '—') ?></div>
          <div class="payment-meta">
            <span><?= icon('calendar', 12) ?> <?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></span>
            <span><?= icon('card', 12) ?> <?= e(strtoupper($p['method'])) ?></span>
          </div>
        </div>
        <div class="payment-right">
          <div class="payment-amount"><?= money($p['amount']) ?> <?= t('soum') ?></div>
          <span class="badge badge-<?= $cls ?>"><?= e(t($p['status'])) ?></span>
        </div>
        <a href="/invoice.php?id=<?= $p['id'] ?>" target="_blank" class="payment-action" title="Chek">
          <?= icon('document', 16) ?>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</main>
</div>

<style>
/* Current tariff banner */
.current-tariff-banner{background:linear-gradient(135deg,#10B981,#059669);color:#fff;
  border-radius:var(--r-xl);padding:24px 28px;position:relative;overflow:hidden;
  box-shadow:0 12px 32px rgba(16,185,129,.25)}
.current-tariff-banner::before{content:'';position:absolute;right:-50px;top:-50px;width:200px;height:200px;
  background:radial-gradient(circle,rgba(255,255,255,.2),transparent 70%);border-radius:50%}
.ct-content{display:flex;align-items:center;gap:18px;position:relative;z-index:1}
.ct-icon{width:64px;height:64px;background:rgba(255,255,255,.2);border-radius:16px;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;backdrop-filter:blur(10px)}
.ct-info{flex:1}
.ct-label{font-size:12px;text-transform:uppercase;letter-spacing:.05em;opacity:.85;font-weight:600}
.ct-name{font-size:24px;font-weight:800;margin:4px 0;color:#fff}
.ct-expires{display:inline-flex;align-items:center;gap:6px;font-size:13px;background:rgba(255,255,255,.15);
  padding:5px 12px;border-radius:20px;margin-top:4px}

/* Payment list */
.payment-list{display:flex;flex-direction:column}
.payment-item{display:flex;align-items:center;gap:14px;padding:14px 24px;border-bottom:1px solid var(--border);transition:background .2s}
.payment-item:hover{background:var(--bg-soft)}
.payment-item:last-child{border-bottom:none}
.payment-icon{width:44px;height:44px;border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.payment-icon.success{background:var(--success-light);color:var(--success-dark)}
.payment-icon.warning{background:var(--warning-light);color:var(--warning-dark)}
.payment-icon.danger{background:var(--danger-light);color:var(--danger-dark)}
.payment-icon.mute{background:var(--bg-mute);color:var(--text-soft)}
.payment-body{flex:1;min-width:0}
.payment-title{font-weight:600;font-size:14px;margin-bottom:4px}
.payment-meta{display:flex;gap:14px;font-size:12px;color:var(--text-soft);flex-wrap:wrap}
.payment-meta span{display:inline-flex;align-items:center;gap:4px}
.payment-right{text-align:right;flex-shrink:0;display:flex;flex-direction:column;align-items:flex-end;gap:4px}
.payment-amount{font-weight:700;font-size:14px;color:var(--text)}
.payment-action{padding:8px;border-radius:var(--r-sm);background:var(--bg-mute);color:var(--text-soft);
  display:flex;align-items:center;justify-content:center;transition:all .2s;text-decoration:none;flex-shrink:0}
.payment-action:hover{background:var(--primary);color:#fff;transform:translateY(-1px)}

@media(max-width:640px){
  .payment-item{padding:12px 16px;gap:10px;flex-wrap:wrap}
  .payment-meta{font-size:11px}
  .payment-right{margin-left:auto}
  .payment-action{margin-left:auto}
  .ct-content{flex-direction:column;align-items:flex-start;text-align:left}
}

/* MOBILE-FIRST OVERRIDES v3.0 — tariflar (current banner + payment list) */
@media(max-width:880px){
  .current-tariff-banner{padding:18px 18px;border-radius:14px}
  .ct-icon{width:48px;height:48px;border-radius:12px}
  .ct-name{font-size:18px;margin:2px 0}
  .ct-label{font-size:10px}
  .ct-expires{font-size:11px;padding:4px 10px}
  .payment-item{padding:12px 14px;gap:10px}
  .payment-icon{width:38px;height:38px}
  .payment-title{font-size:13px;margin-bottom:3px}
}
@media(max-width:480px){
  .current-tariff-banner{padding:16px}
  .ct-content{flex-direction:column;align-items:flex-start;gap:12px}
  .ct-icon{width:44px;height:44px}
  .ct-name{font-size:17px}
  .payment-item{padding:10px 12px;flex-wrap:wrap}
  .payment-body{flex:1 1 100%;order:2;margin-top:6px}
  .payment-icon{order:1}
  .payment-right{order:3;margin-left:auto;flex-direction:row;align-items:center;gap:8px}
  .payment-action{order:4}
}
</style>

<!-- ====== TO'LOV MODAL ====== -->
<div id="payModal" class="modal-backdrop">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3 class="modal-title">
        <?= icon('card', 22) ?> <?= t('payment') ?> · <span id="payTitle"></span>
      </h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" id="payForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="pay">
      <input type="hidden" name="tariff_id" id="payTariffId">

      <div class="modal-body">
        <!-- Amount -->
        <div class="amount-display mb-3">
          <div class="amount-label"><?= t('pay_amount') ?></div>
          <div class="amount-value" id="payAmount"></div>
        </div>

        <!-- Method tabs -->
        <div class="method-tabs">
          <label class="method-tab active" data-method="manual">
            <input type="radio" name="method" value="manual" checked>
            <span class="method-icon">💳</span>
            <span class="method-name">Karta orqali</span>
            <span class="method-badge">Tavsiya etiladi</span>
          </label>

          <?php if ($click_enabled): ?>
          <label class="method-tab" data-method="click">
            <input type="radio" name="method" value="click">
            <span class="method-icon">⚡</span>
            <span class="method-name">Click</span>
          </label>
          <?php endif; ?>

          <?php if ($payme_enabled): ?>
          <label class="method-tab" data-method="payme">
            <input type="radio" name="method" value="payme">
            <span class="method-icon">💎</span>
            <span class="method-name">Payme</span>
          </label>
          <?php endif; ?>
        </div>

        <!-- Manual (screenshot) -->
        <div class="method-content" id="content-manual">
          <div class="payment-card mb-3">
            <div class="payment-card-label">💳 Karta raqami</div>
            <div class="payment-card-number" id="cardNumber"><?= e(setting('card_number','8600 1234 5678 9012')) ?></div>
            <button type="button" class="btn btn-light btn-sm" onclick="copyCard()" id="copyBtn">
              <?= icon('logs', 14) ?> Nusxalash
            </button>
            <div class="payment-card-holder">
              <span class="text-soft" style="font-size:12px"><?= t('card_holder') ?>:</span>
              <strong><?= e(setting('card_holder','VATANPARVAR YAYPAN')) ?></strong>
            </div>
          </div>

          <!-- Steps -->
          <div class="pay-steps mb-3">
            <div class="pay-step">
              <div class="pay-step-num">1</div>
              <div><?= lang()==='uz_cyrillic' ? "Юқоридаги картага суммани ўтказинг" : "Yuqoridagi kartaga summani o'tkazing" ?></div>
            </div>
            <div class="pay-step">
              <div class="pay-step-num">2</div>
              <div><?= lang()==='uz_cyrillic' ? "Чек скриншотини юкланг" : "Chek skrinshotini yuklang" ?></div>
            </div>
            <div class="pay-step">
              <div class="pay-step-num">3</div>
              <div><?= lang()==='uz_cyrillic' ? "Админ тасдиқлашини кутинг (1-24 соат)" : "Admin tasdiqlashini kuting (1-24 soat)" ?></div>
            </div>
          </div>

          <!-- Screenshot upload -->
          <div class="form-group">
            <label class="form-label"><?= t('screenshot') ?> <span style="color:var(--danger)">*</span></label>
            <div class="image-uploader" id="screenshotDrop">
              <input type="file" name="screenshot" accept="image/*" id="screenshotInput" hidden>
              <div class="image-uploader-empty" id="screenshotEmpty">
                <?= icon('upload', 36) ?>
                <strong>Skrinshotni shu yerga tashlang</strong>
                <small>yoki bosib tanlang (JPG, PNG, max 5MB)</small>
              </div>
              <img id="screenshotPreview" style="display:none;width:100%;height:200px;object-fit:contain;background:#000">
              <button type="button" class="btn btn-sm" onclick="removeScreenshot(event)" id="screenshotRemoveBtn"
                      style="display:none;position:absolute;top:8px;right:8px;background:rgba(255,0,0,.9);color:#fff">
                <?= icon('x', 14) ?>
              </button>
            </div>
          </div>

          <!-- Telegram tip -->
          <div class="alert alert-info" style="font-size:13px">
            <?= icon('telegram', 18) ?>
            <div><strong><?= lang()==='uz_cyrillic' ? "Тез усул:" : "Tezkor usul:" ?></strong>
            <?= lang()==='uz_cyrillic'
              ? "Скриншотни Telegram бот @". e(setting('telegram_url','vatanparvar')) ." га ҳам юбориш мумкин"
              : "Skrinshotni Telegram bot @" . e(str_replace('https://t.me/','',setting('telegram_url','vatanparvar'))) . " ga ham yuborish mumkin" ?>
            </div>
          </div>
        </div>

        <!-- Click -->
        <div class="method-content" id="content-click" style="display:none">
          <div class="alert alert-success" style="font-size:14px">
            <?= icon('zap', 20) ?>
            <div><strong>Click orqali avtomatik to'lov</strong><br>
            <?= lang()==='uz_cyrillic' ? "Click саҳифасига йўналтирасиз. Тўловдан сўнг тарифингиз дарҳол фаоллашади." : "Click sahifasiga yo'naltirasiz. To'lovdan so'ng tarifingiz darhol faollashadi." ?></div>
          </div>
        </div>

        <!-- Payme -->
        <div class="method-content" id="content-payme" style="display:none">
          <div class="alert alert-success" style="font-size:14px">
            <?= icon('zap', 20) ?>
            <div><strong>Payme orqali avtomatik to'lov</strong><br>
            <?= lang()==='uz_cyrillic' ? "Payme саҳифасига йўналтирасиз. Тўловдан сўнг тарифингиз дарҳол фаоллашади." : "Payme sahifasiga yo'naltirasiz. To'lovdan so'ng tarifingiz darhol faollashadi." ?></div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-modal-close><?= t('cancel') ?></button>
        <button type="submit" class="btn btn-primary" id="paySubmitBtn">
          <?= icon('send', 16) ?> <?= t('pay_now') ?>
        </button>
      </div>
    </form>
  </div>
</div>

<style>
/* Payment modal */
.amount-display{background:linear-gradient(135deg,var(--primary-50),var(--primary-100));
  border:1px solid var(--primary-200);border-radius:var(--r-lg);padding:20px;text-align:center}
.amount-label{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--primary-700);font-weight:700}
.amount-value{font-size:36px;font-weight:800;color:var(--primary-700);margin-top:6px}

.method-tabs{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}
.method-tab{flex:1;min-width:140px;background:var(--bg-soft);border:2px solid var(--border);border-radius:var(--r-md);
  padding:14px 12px;cursor:pointer;text-align:center;transition:all .25s var(--ease-soft);position:relative;display:flex;flex-direction:column;align-items:center;gap:4px}
.method-tab input{display:none}
.method-tab:hover{border-color:var(--primary-300);transform:translateY(-2px)}
.method-tab.active{border-color:var(--primary);background:var(--primary-50);box-shadow:0 4px 12px rgba(59,130,246,.15)}
.method-icon{font-size:28px}
.method-name{font-weight:700;font-size:14px}
.method-badge{font-size:10px;background:var(--success);color:#fff;padding:2px 8px;border-radius:10px;font-weight:700}

.payment-card{background:linear-gradient(135deg,#1E40AF,#3B82F6);color:#fff;
  border-radius:var(--r-lg);padding:24px;position:relative;overflow:hidden;
  box-shadow:0 12px 28px rgba(59,130,246,.3)}
.payment-card::before{content:'';position:absolute;top:-30px;right:-30px;width:160px;height:160px;
  background:radial-gradient(circle,rgba(255,255,255,.2),transparent 70%);border-radius:50%}
.payment-card-label{font-size:11px;text-transform:uppercase;letter-spacing:.1em;opacity:.85}
.payment-card-number{font-size:24px;font-weight:700;letter-spacing:3px;margin:8px 0 12px;font-family:'Courier New',monospace;
  position:relative;z-index:1}
.payment-card-holder{margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,.2);
  display:flex;justify-content:space-between;align-items:center;font-size:13px;flex-wrap:wrap;gap:6px}
.payment-card-holder strong{color:#fff;letter-spacing:1px}

.pay-steps{display:flex;gap:14px;flex-wrap:wrap}
.pay-step{flex:1;min-width:180px;background:var(--bg-soft);border-radius:var(--r-md);padding:12px;
  display:flex;align-items:center;gap:10px;font-size:13px}
.pay-step-num{flex-shrink:0;width:28px;height:28px;background:var(--primary);color:#fff;border-radius:50%;
  display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px}

.image-uploader{position:relative;border:2px dashed var(--border);border-radius:var(--r-lg);
  overflow:hidden;cursor:pointer;background:var(--bg-soft);transition:all .25s;min-height:200px}
.image-uploader:hover{border-color:var(--primary);background:var(--primary-50)}
.image-uploader.is-dragover{border-color:var(--primary);background:var(--primary-100);transform:scale(1.01)}
.image-uploader-empty{padding:40px 20px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:8px;color:var(--text-soft)}
.image-uploader-empty strong{font-size:14px;color:var(--text)}
.image-uploader-empty small{font-size:11px;color:var(--text-mute)}

@media(max-width:640px){
  .method-tabs{flex-direction:column}
  .method-tab{flex:none;width:100%}
  .payment-card-number{font-size:20px}
  .pay-step{min-width:auto;width:100%}
}

/* MOBILE-FIRST OVERRIDES v3.0 — payment modal */
@media(max-width:880px){
  .amount-display{padding:16px;border-radius:12px}
  .amount-label{font-size:11px}
  .amount-value{font-size:30px;margin-top:4px}
  .method-tabs{gap:6px;margin-bottom:16px}
  .method-tab{padding:12px 10px;border-width:2px;border-radius:10px;min-height:54px}
  .method-icon{font-size:24px}
  .method-name{font-size:13px}
  .method-badge{font-size:9px;padding:2px 6px}
  .payment-card{padding:18px;border-radius:12px}
  .payment-card-label{font-size:10px}
  .payment-card-number{font-size:18px;letter-spacing:2px;margin:6px 0 10px}
  .payment-card-holder{font-size:12px;padding-top:10px;margin-top:10px}
  .pay-steps{gap:8px}
  .pay-step{padding:10px;font-size:12px;gap:8px}
  .pay-step-num{width:24px;height:24px;font-size:11px}
  .image-uploader{min-height:160px}
  .image-uploader-empty{padding:28px 16px}
  .image-uploader-empty strong{font-size:13px}
}
@media(max-width:480px){
  .amount-value{font-size:26px}
  .method-tab{padding:10px 8px;min-height:48px}
  .method-icon{font-size:20px}
  .method-name{font-size:12px}
  .payment-card-number{font-size:16px;letter-spacing:1.5px}
}
</style>

<script>
function payOpen(id, title, amount){
  document.getElementById('payTariffId').value = id;
  document.getElementById('payTitle').textContent = title;
  document.getElementById('payAmount').textContent = amount.toLocaleString('uz-UZ').replace(/,/g,' ') + " so'm";
  selectMethod('manual');
  removeScreenshot();
  openModal('payModal');
}

function selectMethod(m){
  document.querySelectorAll('.method-tab').forEach(t => t.classList.remove('active'));
  document.querySelector(`.method-tab[data-method="${m}"]`)?.classList.add('active');
  document.querySelector(`.method-tab[data-method="${m}"] input`).checked = true;

  document.querySelectorAll('.method-content').forEach(c => c.style.display = 'none');
  document.getElementById('content-'+m).style.display = '';

  // Submit button text
  const btn = document.getElementById('paySubmitBtn');
  if (m === 'manual') {
    btn.innerHTML = '<?= icon('send', 16) ?> Yuborish';
  } else {
    btn.innerHTML = '<?= icon('zap', 16) ?> ' + m.toUpperCase() + " orqali to'lash";
  }

  // Manual'da screenshot majburiy, boshqalarda yo'q
  const sInput = document.getElementById('screenshotInput');
  sInput.required = false; // We validate on submit
}

document.querySelectorAll('.method-tab').forEach(tab => {
  tab.addEventListener('click', () => selectMethod(tab.dataset.method));
});

function copyCard(){
  const num = '<?= e(setting('card_number','')) ?>'.replace(/\s/g,'');
  navigator.clipboard.writeText(num).then(() => {
    const btn = document.getElementById('copyBtn');
    btn.innerHTML = '✓ Nusxalandi';
    setTimeout(() => btn.innerHTML = '<?= icon('logs', 14) ?> Nusxalash', 2000);
    if (window.toast) toast('Karta raqami nusxalandi!', 'success');
  });
}

// Screenshot upload
(function(){
  const drop = document.getElementById('screenshotDrop');
  const input = document.getElementById('screenshotInput');
  const preview = document.getElementById('screenshotPreview');
  const empty = document.getElementById('screenshotEmpty');
  const removeBtn = document.getElementById('screenshotRemoveBtn');
  if (!drop) return;

  drop.addEventListener('click', e => {
    if (e.target.tagName !== 'BUTTON') input.click();
  });
  drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('is-dragover'); });
  drop.addEventListener('dragleave', () => drop.classList.remove('is-dragover'));
  drop.addEventListener('drop', e => {
    e.preventDefault(); drop.classList.remove('is-dragover');
    if (e.dataTransfer.files[0]) { input.files = e.dataTransfer.files; previewScreenshot(); }
  });
  input.addEventListener('change', previewScreenshot);

  window.previewScreenshot = function(){
    if (input.files && input.files[0]) {
      const r = new FileReader();
      r.onload = e => {
        preview.src = e.target.result;
        preview.style.display = 'block';
        empty.style.display = 'none';
        removeBtn.style.display = 'flex';
      };
      r.readAsDataURL(input.files[0]);
    }
  };
})();

function removeScreenshot(e){
  if (e) e.stopPropagation();
  document.getElementById('screenshotInput').value = '';
  document.getElementById('screenshotPreview').style.display = 'none';
  document.getElementById('screenshotEmpty').style.display = 'flex';
  document.getElementById('screenshotRemoveBtn').style.display = 'none';
}

// Submit validation
document.getElementById('payForm')?.addEventListener('submit', e => {
  const method = document.querySelector('input[name="method"]:checked')?.value;
  if (method === 'manual' && !document.getElementById('screenshotInput').files[0]) {
    e.preventDefault();
    if (window.toast) toast('Iltimos, chek skrinshotini yuklang', 'danger');
    document.getElementById('screenshotDrop').style.borderColor = 'var(--danger)';
    setTimeout(() => document.getElementById('screenshotDrop').style.borderColor = '', 2000);
  }
});

<?php if ($selectedTariff): ?>
window.addEventListener('load', () => {
  document.querySelector('button[onclick*="payOpen(<?= $selectedTariff ?>"]')?.click();
});
<?php endif; ?>
</script>
<script><?= panel_js() ?></script>
</body></html>
