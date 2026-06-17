<?php
/**
 * Invoice (HTML chek) generator — print-friendly, PDF'ga export qilish mumkin
 */
require_once __DIR__ . '/../functions.php';

class Invoice {

    public static function render(int $payment_id): void {
        $p = db()->fetch(
            "SELECT p.*, u.first_name, u.last_name, u.phone, u.email,
                    t.name_latin t_name, t.duration_days
             FROM payments p
             LEFT JOIN users u ON p.user_id = u.id
             LEFT JOIN tariffs t ON p.tariff_id = t.id
             WHERE p.id = ?", [$payment_id]);

        if (!$p) { http_response_code(404); echo "Chek topilmadi"; return; }

        $hash = strtoupper(substr(md5($p['id'].$p['created_at']), 0, 12));
        $methodName = ['click'=>'Click','payme'=>'Payme','manual'=>'Bank Karta','telegram'=>'Telegram'][$p['method']] ?? $p['method'];
        $statusBadge = match($p['status']) {
            'approved' => '<span style="color:#10B981">✓ Tasdiqlangan</span>',
            'pending'  => '<span style="color:#F59E0B">⏳ Kutilmoqda</span>',
            'rejected' => '<span style="color:#EF4444">✗ Rad etilgan</span>',
            default    => $p['status'],
        };
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<title>Chek #<?= $p['id'] ?> — <?= e(setting('site_name')) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter','Segoe UI',sans-serif;background:#F1F5F9;padding:30px 16px;color:#0F172A;line-height:1.5}
.invoice{max-width:680px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;
  box-shadow:0 10px 40px rgba(15,23,42,.08)}
.invoice-head{background:linear-gradient(135deg,#3B82F6,#1E40AF);color:#fff;padding:30px 36px;
  display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px}
.invoice-logo{display:flex;align-items:center;gap:12px;font-weight:800;font-size:18px}
.logo-box{width:44px;height:44px;background:rgba(255,255,255,.2);border-radius:10px;
  display:flex;align-items:center;justify-content:center;font-weight:800}
.invoice-no{text-align:right;font-size:13px;opacity:.85}
.invoice-no strong{font-size:24px;display:block;margin-top:4px}
.invoice-body{padding:36px}
.invoice-meta{display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-bottom:30px}
.invoice-meta h4{font-size:11px;text-transform:uppercase;color:#94A3B8;letter-spacing:.08em;margin-bottom:6px;font-weight:700}
.invoice-meta p{font-size:14px;line-height:1.6;color:#0F172A}
.invoice-meta strong{color:#0F172A;font-weight:600}
.invoice-table{width:100%;border-collapse:collapse;margin-bottom:24px}
.invoice-table th{background:#F8FAFC;padding:12px 14px;text-align:left;font-size:11px;
  text-transform:uppercase;color:#64748B;letter-spacing:.05em;border-bottom:1px solid #E2E8F0}
.invoice-table td{padding:14px;border-bottom:1px solid #E2E8F0;font-size:14px}
.invoice-table .price{text-align:right;font-weight:700;color:#0F172A}
.invoice-total{display:flex;justify-content:flex-end;padding:18px 0;border-top:2px solid #0F172A}
.invoice-total .total-row{display:flex;gap:50px;font-size:16px}
.invoice-total .label{color:#64748B}
.invoice-total .amount{font-size:28px;font-weight:800;color:#3B82F6}
.invoice-footer{padding:20px 36px;background:#F8FAFC;text-align:center;font-size:13px;color:#64748B;border-top:1px solid #E2E8F0}
.invoice-status{display:inline-block;padding:5px 14px;border-radius:20px;font-weight:700;font-size:13px;
  background:#F0FDF4;border:1px solid #BBF7D0}
.actions{display:flex;justify-content:center;gap:12px;margin-top:24px}
.btn{padding:12px 24px;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-family:inherit;
  text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-size:14px}
.btn-primary{background:#3B82F6;color:#fff}
.btn-light{background:#F1F5F9;color:#0F172A}
.qr{margin-top:16px;display:flex;align-items:center;gap:14px;padding:14px;background:#F8FAFC;border-radius:10px;font-size:12px;color:#64748B}
.qr-box{width:80px;height:80px;background:#fff;border:1px solid #E2E8F0;border-radius:8px;
  display:flex;align-items:center;justify-content:center;font-size:10px;color:#94A3B8;text-align:center}
@media print {
  body{background:#fff;padding:0}
  .invoice{box-shadow:none;max-width:100%}
  .actions{display:none}
}
</style>
</head>
<body>
<div class="invoice">
  <div class="invoice-head">
    <div class="invoice-logo">
      <span class="logo-box">VP</span>
      <div>
        <div><?= e(setting('site_name', SITE_NAME)) ?></div>
        <div style="font-size:11px;opacity:.85;font-weight:400">Avtomaktab platformasi</div>
      </div>
    </div>
    <div class="invoice-no">
      To'lov cheki<br>
      <strong>#<?= str_pad((string)$p['id'], 6, '0', STR_PAD_LEFT) ?></strong>
    </div>
  </div>

  <div class="invoice-body">
    <div class="invoice-meta">
      <div>
        <h4>Mijoz</h4>
        <p>
          <strong><?= e($p['first_name'].' '.$p['last_name']) ?></strong><br>
          <?= e($p['phone'] ?? $p['email']) ?>
        </p>
      </div>
      <div>
        <h4>To'lov ma'lumotlari</h4>
        <p>
          <strong>Sana:</strong> <?= date('d.m.Y H:i', strtotime($p['created_at'])) ?><br>
          <strong>Usul:</strong> <?= e($methodName) ?><br>
          <strong>Holati:</strong> <?= $statusBadge ?>
          <?php if ($p['transaction_id']): ?>
            <br><strong>TX:</strong> <code style="font-size:11px"><?= e($p['transaction_id']) ?></code>
          <?php endif; ?>
        </p>
      </div>
    </div>

    <table class="invoice-table">
      <thead>
        <tr><th>Xizmat</th><th>Muddat</th><th class="price">Summa</th></tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <strong>Tarif: <?= e($p['t_name'] ?? '—') ?></strong>
            <div style="font-size:12px;color:#64748B;margin-top:2px">VatanParvar Yaypan o'quv platformasi</div>
          </td>
          <td><?= (int)($p['duration_days'] ?? 30) ?> kun</td>
          <td class="price"><?= number_format($p['amount'], 0, '.', ' ') ?> so'm</td>
        </tr>
      </tbody>
    </table>

    <div class="invoice-total">
      <div class="total-row">
        <span class="label">JAMI:</span>
        <span class="amount"><?= number_format($p['amount'], 0, '.', ' ') ?> so'm</span>
      </div>
    </div>

    <div class="qr">
      <div class="qr-box">
        QR<br>
        <?= $hash ?>
      </div>
      <div>
        <strong>Tasdiqlash kodi:</strong> <code style="font-size:14px;color:#3B82F6"><?= $hash ?></code><br>
        <small>Chekni tekshirish uchun ushbu kodni ishlatishingiz mumkin.<br>
        <?= e(SITE_URL) ?>/api/check-invoice.php?code=<?= $hash ?></small>
      </div>
    </div>

    <div class="actions">
      <button class="btn btn-primary" onclick="window.print()">🖨 Chop etish</button>
      <a href="/user/tariflar.php" class="btn btn-light">← Ortga</a>
    </div>
  </div>

  <div class="invoice-footer">
    <strong><?= e(setting('site_name')) ?></strong> · <?= e(setting('site_phone')) ?> · <?= e(setting('site_email')) ?><br>
    <?= e(setting('site_address')) ?>
  </div>
</div>
</body></html>
<?php
    }
}
