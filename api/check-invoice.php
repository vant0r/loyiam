<?php
/**
 * Chek tasdiqlash sahifasi (URL'da hash bilan)
 */
require_once __DIR__ . '/../includes/functions.php';

$code = strtoupper(trim($_GET['code'] ?? ''));

render_head('Chek tekshirish');
render_header();

if (!preg_match('/^[A-F0-9]{12}$/', $code)) {
    echo '<div class="container section text-center"><h1>Noto\'g\'ri kod</h1><p>Tasdiqlash kodi 12 ta belgidan iborat bo\'lishi kerak.</p><a href="/" class="btn btn-primary">Bosh sahifa</a></div>';
    render_footer();
    exit;
}

// Hashni qidiramiz
$invoice = null;
$payments = db()->fetchAll("SELECT p.*, u.first_name, u.last_name, t.name_latin tname
                            FROM payments p
                            LEFT JOIN users u ON p.user_id = u.id
                            LEFT JOIN tariffs t ON p.tariff_id = t.id");
foreach ($payments as $p) {
    $h = strtoupper(substr(md5($p['id'].$p['created_at']), 0, 12));
    if ($h === $code) { $invoice = $p; break; }
}
?>

<section class="section">
  <div class="container" style="max-width:600px">
    <?php if (!$invoice): ?>
      <div class="card text-center" style="padding:60px 30px">
        <?= icon('x-circle', 64) ?>
        <h2 style="margin-top:14px;color:var(--danger-dark)">Chek topilmadi</h2>
        <p class="text-soft">Tasdiqlash kodi noto'g'ri yoki chek o'chirilgan.</p>
        <code style="background:var(--bg-mute);padding:6px 12px;border-radius:6px;margin-top:14px;display:inline-block"><?= e($code) ?></code>
        <div class="mt-3"><a href="/" class="btn btn-primary">Bosh sahifa</a></div>
      </div>
    <?php else: ?>
      <div class="card" style="padding:0;overflow:hidden">
        <div style="background:linear-gradient(135deg,
          <?= $invoice['status']==='approved' ? 'var(--success),#059669' : ($invoice['status']==='rejected' ? 'var(--danger),#DC2626' : 'var(--warning),#D97706') ?>);
          color:#fff;padding:32px;text-align:center">
          <div style="font-size:64px;margin-bottom:8px">
            <?= $invoice['status']==='approved' ? '✓' : ($invoice['status']==='rejected' ? '✕' : '⏳') ?>
          </div>
          <h2 style="color:#fff">Chek <?= $invoice['status']==='approved' ? 'tasdiqlangan' : ($invoice['status']==='rejected' ? 'rad etilgan' : 'kutilmoqda') ?></h2>
          <p style="opacity:.9">#<?= str_pad((string)$invoice['id'], 6, '0', STR_PAD_LEFT) ?></p>
        </div>
        <div style="padding:24px">
          <table style="width:100%">
            <tr><td class="text-soft" style="padding:8px 0">Mijoz:</td><td><strong><?= e($invoice['first_name'].' '.$invoice['last_name']) ?></strong></td></tr>
            <tr><td class="text-soft" style="padding:8px 0">Tarif:</td><td><strong><?= e($invoice['tname']) ?></strong></td></tr>
            <tr><td class="text-soft" style="padding:8px 0">Summa:</td><td><strong><?= money($invoice['amount']) ?> <?= t('soum') ?></strong></td></tr>
            <tr><td class="text-soft" style="padding:8px 0">Sana:</td><td><?= date('d.m.Y H:i', strtotime($invoice['created_at'])) ?></td></tr>
            <tr><td class="text-soft" style="padding:8px 0">Usul:</td><td><?= strtoupper($invoice['method']) ?></td></tr>
          </table>
          <div class="alert alert-success mt-3" style="font-size:13px">
            <?= icon('shield', 18) ?>
            <span>Bu chek <?= e(setting('site_name')) ?> tomonidan tasdiqlangan</span>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php render_footer(); ?>
