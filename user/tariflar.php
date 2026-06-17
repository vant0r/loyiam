<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payments/click.php';
require_once __DIR__ . '/../includes/payments/payme.php';
require_login();

$u = current_user();
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

$msg = ''; $err = '';

// To'lov yuborish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    if (!csrf_check()) {
        $err = t('csrf_invalid');
    } else {
        $tariff_id = (int)$_POST['tariff_id'];
        $method    = in_array($_POST['method'] ?? '', ['click','payme','manual']) ? $_POST['method'] : 'manual';
        $tariff    = db()->fetch("SELECT * FROM tariffs WHERE id=? AND status='active'", [$tariff_id]);

        if ($tariff && $tariff['price'] > 0) {
            $screenshot = null;
            if ($method === 'manual' && !empty($_FILES['screenshot']['name'])) {
                $up = Security::upload_image($_FILES['screenshot'], 'pay_'.$u['id']);
                if ($up['ok']) $screenshot = $up['url'];
                else $err = $up['error'];
            }

            if (!$err) {
                $status = $method === 'manual' ? 'pending' : 'pending';
                db()->execute(
                    "INSERT INTO payments (user_id, tariff_id, amount, method, screenshot, status)
                     VALUES (?,?,?,?,?,?)",
                    [$u['id'], $tariff_id, $tariff['price'], $method, $screenshot, $status]);
                $payment_id = (int)db()->lastInsertId();
                audit('payment_created', "Tarif: {$tariff['name_latin']} ({$method})");

                // Click yoki Payme bo'lsa — to'g'ridan to'g'ri payment URL'ga
                if ($method === 'click') {
                    header('Location: ' . ClickPayment::build_payment_url($payment_id, (float)$tariff['price'], SITE_URL.'/user/tariflar.php'));
                    exit;
                }
                if ($method === 'payme') {
                    header('Location: ' . PaymePayment::build_payment_url($payment_id, (float)$tariff['price']));
                    exit;
                }
                $msg = lang()==='uz_cyrillic'
                    ? 'Тўлов сўрови юборилди. Админ кутиб турибди.'
                    : 'To\'lov so\'rovi yuborildi. Admin kutib turibdi.';
            }
        }
    }
}

$tariffs   = db()->fetchAll("SELECT * FROM tariffs WHERE status='active' ORDER BY sort_order");
$payments  = db()->fetchAll("SELECT p.*, t.name_$lang_field tname
                             FROM payments p LEFT JOIN tariffs t ON p.tariff_id=t.id
                             WHERE p.user_id=? ORDER BY p.created_at DESC LIMIT 20", [$u['id']]);
$current_tariff = $u['tariff_id'] ? db()->fetch("SELECT * FROM tariffs WHERE id=?", [$u['tariff_id']]) : null;

$selectedTariff = (int)($_GET['tariff'] ?? 0);

render_head(t('tariffs'));
?>
<div class="layout">
<?php render_sidebar('user', 'tariffs'); ?>
<main class="main">
  <div class="page-header">
    <div class="page-title"><?= icon('gem', 28) ?> <?= t('tariffs') ?></div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= icon('check-circle', 18) ?> <?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= icon('x-circle', 18) ?> <?= e($err) ?></div><?php endif; ?>

  <!-- Joriy tarif -->
  <div class="card card-primary mb-3">
    <div class="flex justify-between items-center flex-wrap gap-3">
      <div>
        <div style="font-size:13px;opacity:.85;text-transform:uppercase;letter-spacing:.05em;font-weight:600"><?= t('current_tariff') ?></div>
        <div style="font-size:28px;font-weight:800;margin-top:4px">
          <?= $current_tariff ? e($current_tariff['name_'.$lang_field]) : t('free') ?>
        </div>
        <?php if ($u['tariff_expires_at'] && $current_tariff): ?>
          <?php
            $expDays = max(0, (strtotime($u['tariff_expires_at']) - time()) / 86400);
            $expClass = $expDays < 7 ? 'warning' : 'normal';
          ?>
          <div style="font-size:13px;opacity:.85;margin-top:6px">
            <?= icon('clock', 14) ?>
            <?= t('expires_at') ?>: <?= date('d.m.Y', strtotime($u['tariff_expires_at'])) ?>
            <span style="margin-left:8px;padding:2px 8px;border-radius:10px;background:rgba(255,255,255,.2);font-size:11px;font-weight:700">
              <?= floor($expDays) ?> <?= t('days') ?>
            </span>
          </div>
        <?php endif; ?>
      </div>
      <div style="font-size:64px;opacity:.7"><?= icon('gem', 64) ?></div>
    </div>
  </div>

  <!-- Tariflar -->
  <div class="grid-3 mb-3">
    <?php foreach ($tariffs as $tariff):
      $features = explode('|', $tariff['features_'.$lang_field] ?? '');
      $isCurrent = $current_tariff && $current_tariff['id'] == $tariff['id'];
    ?>
    <div class="card pricing-card <?= $tariff['is_popular']?'popular':'' ?> <?= $isCurrent?'is-current':'' ?>"
         <?= $isCurrent ? 'style="border-color:var(--success);box-shadow:0 0 0 2px var(--success-light)"' : '' ?>>
      <?php if ($isCurrent): ?>
        <div class="pricing-badge" style="background:var(--success)">
          <?= icon('check', 12) ?> <?= t('active') ?>
        </div>
      <?php elseif ($tariff['is_popular']): ?>
        <div class="pricing-badge"><?= t('popular') ?></div>
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
          <?= t('choose_plan') ?>
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
  <div class="card" style="padding:0">
    <div style="padding:20px 24px;border-bottom:1px solid var(--border)">
      <h3 style="font-size:18px;font-weight:700;display:flex;align-items:center;gap:10px">
        <?= icon('logs', 22) ?> <?= t('pay_history') ?>
      </h3>
    </div>
    <?php if (empty($payments)): ?>
      <div class="empty-state">
        <?= icon('card', 64) ?>
        <h3 class="mt-2"><?= t('no_payments') ?></h3>
      </div>
    <?php else: ?>
    <div class="table-wrap table-flat">
      <table>
        <thead><tr><th>#</th><th><?= t('tariffs') ?></th><th><?= t('amount') ?></th><th><?= t('method') ?></th><th><?= t('status') ?></th><th><?= t('date') ?></th><th><?= t('actions') ?></th></tr></thead>
        <tbody>
          <?php foreach ($payments as $i => $p): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><?= e($p['tname'] ?? '—') ?></td>
            <td><strong><?= money($p['amount']) ?></strong> <?= t('soum') ?></td>
            <td><span class="badge badge-mute"><?= e(strtoupper($p['method'])) ?></span></td>
            <td>
              <?php $cls = ['pending'=>'warning','approved'=>'success','rejected'=>'danger','refunded'=>'mute'][$p['status']] ?? 'mute'; ?>
              <span class="badge badge-<?= $cls ?>"><?= e(t($p['status'])) ?></span>
            </td>
            <td><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></td>
            <td>
              <a href="/invoice.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-light btn-sm">
                <?= icon('document', 14) ?> Chek
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</main>
</div>

<!-- To'lov modal -->
<div id="payModal" class="modal-backdrop">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3 class="modal-title">
        <?= icon('card', 22) ?> <?= t('payment') ?> · <span id="payTitle"></span>
      </h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="pay">
      <input type="hidden" name="tariff_id" id="payTariffId">

      <div class="modal-body">
        <div class="card mb-3" style="background:linear-gradient(135deg,var(--primary-50),var(--primary-100));border:none;text-align:center">
          <div style="color:var(--text-soft);font-size:13px"><?= t('pay_amount') ?></div>
          <div style="font-size:36px;font-weight:800;color:var(--primary);margin-top:4px" id="payAmount"></div>
        </div>

        <div class="form-group">
          <label class="form-label"><?= t('pay_method') ?></label>
          <div class="grid-3" style="gap:10px">
            <label class="card text-center" style="cursor:pointer;padding:18px 14px;margin:0" onclick="selectMethod('click')">
              <input type="radio" name="method" value="click" id="m_click" checked style="display:none">
              <div style="font-size:32px;margin-bottom:4px">⚡</div>
              <div style="font-weight:700;color:#1ABC9C">Click</div>
              <div style="font-size:11px;color:var(--text-mute)"><?= lang()==='uz_cyrillic' ? "Тез ва осон" : "Tez va oson" ?></div>
            </label>
            <label class="card text-center" style="cursor:pointer;padding:18px 14px;margin:0" onclick="selectMethod('payme')">
              <input type="radio" name="method" value="payme" id="m_payme" style="display:none">
              <div style="font-size:32px;margin-bottom:4px">💳</div>
              <div style="font-weight:700;color:#0EBCFB">Payme</div>
              <div style="font-size:11px;color:var(--text-mute)">Paycom</div>
            </label>
            <label class="card text-center" style="cursor:pointer;padding:18px 14px;margin:0" onclick="selectMethod('manual')">
              <input type="radio" name="method" value="manual" id="m_manual" style="display:none">
              <div style="font-size:32px;margin-bottom:4px">🏦</div>
              <div style="font-weight:700">Karta</div>
              <div style="font-size:11px;color:var(--text-mute)">Manual</div>
            </label>
          </div>
        </div>

        <div id="manualBox" style="display:none">
          <div class="alert alert-info" style="font-size:13px">
            <strong><?= t('card_number') ?>:</strong>
            <code style="font-size:16px;font-weight:700"><?= e(setting('card_number','8600 1234 5678 9012')) ?></code>
            <button type="button" class="btn btn-icon btn-sm" onclick="copyCard()" title="Copy"><?= icon('logs', 14) ?></button>
            <br>
            <strong><?= t('card_holder') ?>:</strong> <?= e(setting('card_holder','VATANPARVAR YAYPAN')) ?>
          </div>
          <div class="form-group">
            <label class="form-label"><?= t('screenshot') ?> (<?= t('optional') ?>)</label>
            <input type="file" name="screenshot" class="form-control" accept="image/*">
            <div class="form-help"><?= lang()==='uz_cyrillic' ? "Чек скриншоти - админ тасдиқлаши учун" : "Chek skrinshoti - admin tasdiqlashi uchun" ?></div>
          </div>
        </div>

        <div id="autoBox">
          <div class="alert alert-success" style="font-size:13px">
            <?= icon('shield', 18) ?>
            <span><?= lang()==='uz_cyrillic' ? "Тўлов автоматик тасдиқланади ва тариф дарҳол фаоллашади" : "To'lov avtomatik tasdiqlanadi va tarif darhol faollashadi" ?></span>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-modal-close><?= t('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= t('pay_now') ?> <?= icon('arrow-right', 16) ?></button>
      </div>
    </form>
  </div>
</div>

<script>
function payOpen(id, title, amount){
  document.getElementById('payTariffId').value = id;
  document.getElementById('payTitle').textContent = title;
  document.getElementById('payAmount').textContent = amount.toLocaleString('uz-UZ').replace(/,/g,' ') + ' so\'m';
  selectMethod('click');
  openModal('payModal');
}
function selectMethod(m){
  document.getElementById('m_'+m).checked = true;
  document.querySelectorAll('#payModal label.card').forEach(l => {
    l.style.borderColor = '';
    l.style.background = '';
  });
  const sel = document.getElementById('m_'+m).closest('label');
  sel.style.borderColor = 'var(--primary)';
  sel.style.background = 'var(--primary-50)';
  document.getElementById('manualBox').style.display = m === 'manual' ? 'block' : 'none';
  document.getElementById('autoBox').style.display = m !== 'manual' ? 'block' : 'none';
}
function copyCard(){
  navigator.clipboard.writeText('<?= e(setting('card_number')) ?>'.replace(/\s/g,''));
  toast('<?= lang()==='uz_cyrillic' ? "Нусхаланди!" : "Nusxalandi!" ?>', 'success');
}

<?php if ($selectedTariff): ?>
// Avto-ochish (?tariff= dan)
window.addEventListener('load', () => {
  const tariff = document.querySelector('button[onclick*="payOpen(<?= $selectedTariff ?>"]');
  if (tariff) tariff.click();
});
<?php endif; ?>
</script>
</body></html>
