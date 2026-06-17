<?php
/**
 * Chek tasdiqlash sahifasi — signed token bilan
 *
 * URL formati: /api/check-invoice.php?token=<payment_id>.<hmac>
 *   - oldingi 12-belgili md5 hash juda qisqa edi (brute-force imkoni)
 *   - va loop bilan barcha to'lovlarni o'qiyotgan edi (DoS riski)
 *   - endi indexed lookup + HMAC-SHA256 tekshirish
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

// Public endpoint — rate limit
$rl = Security::rate_limit('checkinv_' . Security::client_ip(), 30, 600);
if (!$rl['allowed']) {
    http_response_code(429);
    render_head('Juda ko\'p so\'rov');
    render_header();
    echo '<div class="container section text-center" style="padding:80px 20px"><h1>429 — Juda ko\'p so\'rov</h1><p>Iltimos, biroz kutib turing.</p></div>';
    render_footer();
    exit;
}

$token = trim($_GET['token'] ?? $_GET['code'] ?? '');

render_head('Chek tekshirish');
render_header();

// Token format tekshiruvi
$invoice = null;
$pid = 0;
if ($token && str_contains($token, '.')) {
    [$pidStr, $sig] = explode('.', $token, 2);
    $pid = (int)$pidStr;
    if ($pid > 0 && preg_match('/^[a-f0-9]{32}$/', $sig)) {
        $payment = db()->fetch(
            "SELECT p.*, u.first_name, u.last_name, t.name_latin tname
             FROM payments p
             LEFT JOIN users u ON p.user_id = u.id
             LEFT JOIN tariffs t ON p.tariff_id = t.id
             WHERE p.id = ? LIMIT 1",
            [$pid]
        );
        if ($payment) {
            // HMAC tekshiruvi (timing-safe)
            $expected = Security::sign_token('invoice:' . $payment['id'] . ':' . $payment['created_at']);
            if (hash_equals(substr($expected, 0, 32), $sig)) {
                $invoice = $payment;
            }
        }
    }
}
?>

<section class="section">
  <div class="container" style="max-width:600px">
    <?php if (!$invoice): ?>
      <div class="card text-center" style="padding:60px 30px">
        <?= icon('x-circle', 64) ?>
        <h2 style="margin-top:14px;color:var(--danger-dark)">Chek topilmadi</h2>
        <p class="text-soft">Tasdiqlash havolasi noto'g'ri yoki chek o'chirilgan.</p>
        <div class="mt-3"><a href="/" class="btn btn-primary">Bosh sahifa</a></div>
      </div>
    <?php else: ?>
      <div class="card" style="padding:0;overflow:hidden">
        <div style="background:linear-gradient(135deg,
          <?= $invoice['status']==='approved' ? 'var(--success),#059669' : ($invoice['status']==='rejected' ? 'var(--danger),#DC2626' : 'var(--warning),#D97706') ?>);
          color:#fff;padding:32px;text-align:center">
          <div style="margin-bottom:8px">
            <?= icon($invoice['status']==='approved' ? 'check-circle' : ($invoice['status']==='rejected' ? 'x-circle' : 'clock'), 64) ?>
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
            <tr><td class="text-soft" style="padding:8px 0">Usul:</td><td><?= strtoupper(e($invoice['method'])) ?></td></tr>
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
