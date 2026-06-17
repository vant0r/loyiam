<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$u = current_user();
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

// Referallar statistika
$total_invited = (int)(db()->fetch("SELECT COUNT(*) c FROM users WHERE referred_by = ?", [$u['id']])['c'] ?? 0);
$total_active  = (int)(db()->fetch(
    "SELECT COUNT(*) c FROM users WHERE referred_by = ? AND tariff_id IS NOT NULL", [$u['id']])['c'] ?? 0);
$total_pending = (float)(db()->fetch(
    "SELECT COALESCE(SUM(bonus_amount),0) c FROM referrals WHERE referrer_id = ? AND status='pending'",
    [$u['id']])['c'] ?? 0);
$total_paid    = (float)(db()->fetch(
    "SELECT COALESCE(SUM(bonus_amount),0) c FROM referrals WHERE referrer_id = ? AND status='paid'",
    [$u['id']])['c'] ?? 0);

// Taklif qilingan foydalanuvchilar
$invited = db()->fetchAll(
    "SELECT u.id, u.first_name, u.last_name, u.created_at, u.tariff_id,
            r.bonus_amount, r.status r_status
     FROM users u
     LEFT JOIN referrals r ON r.referred_id = u.id AND r.referrer_id = ?
     WHERE u.referred_by = ? ORDER BY u.created_at DESC LIMIT 50",
    [$u['id'], $u['id']]);

$ref_link = SITE_URL . '/register.php?ref=' . urlencode($u['referral_code']);

render_head(t('referrals'));
?>
<div class="layout">
<?php render_sidebar('user', 'referrals'); ?>
<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title"><?= icon('gift', 28) ?> <?= t('referrals') ?></div>
      <div class="page-subtitle"><?= lang()==='uz_cyrillic' ? "Дўст таклиф қилинг ва бонус ютинг" : "Do'st taklif qiling va bonus yutib oling" ?></div>
    </div>
  </div>

  <!-- Hero card with referral link -->
  <div class="card card-primary mb-3" style="position:relative;overflow:hidden">
    <div style="position:absolute;right:-20px;bottom:-20px;font-size:200px;opacity:.1;line-height:1">🎁</div>
    <div style="position:relative">
      <h2 style="color:#fff;font-size:24px;margin-bottom:8px">
        <?= lang()==='uz_cyrillic' ? "Ҳар бир дўст учун — 5,000 сўм бонус!" : "Har bir do'st uchun — 5,000 so'm bonus!" ?>
      </h2>
      <p style="color:#fff;opacity:.92;margin-bottom:18px;font-size:15px;max-width:560px">
        <?= lang()==='uz_cyrillic' ? "Дўстингизга ҳаволани улашинг. Улар рўйхатдан ўтиб, тўлов қилишганда, сиз бонус оласиз." : "Do'stingizga havolani ulashing. Ular ro'yxatdan o'tib, to'lov qilishganda, siz bonus olasiz." ?>
      </p>

      <div style="background:rgba(255,255,255,.15);backdrop-filter:blur(10px);padding:14px 18px;border-radius:12px;display:flex;gap:10px;align-items:center;border:1px solid rgba(255,255,255,.2)">
        <span style="font-size:20px;flex-shrink:0">🔗</span>
        <input type="text" id="refLink" value="<?= e($ref_link) ?>" readonly
          style="background:transparent;border:none;color:#fff;flex:1;outline:none;font-family:monospace;font-size:13px;min-width:0;text-overflow:ellipsis">
        <button class="btn btn-sm" onclick="copyRefLink()" style="background:#fff;color:var(--primary);flex-shrink:0">
          <?= icon('logs', 14) ?> <?= t('copy_link') ?>
        </button>
      </div>

      <div class="flex gap-2 mt-3 flex-wrap">
        <a href="https://t.me/share/url?url=<?= urlencode($ref_link) ?>&text=<?= urlencode(lang()==='uz_cyrillic' ? "ВатанПарвар Яйпан автомактаб платформасига қўшилинг!" : "VatanParvar Yaypan avtomaktab platformasiga qo'shiling!") ?>"
           target="_blank" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff">
          <?= icon('telegram', 14) ?> Telegram
        </a>
        <button onclick="shareNative()" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff">
          <?= icon('send', 14) ?> <?= t('share') ?>
        </button>
      </div>
    </div>
  </div>

  <!-- Statistika -->
  <div class="grid-4 mb-3">
    <div class="stat-card">
      <div class="stat-icon"><?= icon('users', 22) ?></div>
      <div class="value"><?= $total_invited ?></div>
      <div class="label"><?= t('invited_friends') ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon success"><?= icon('check-circle', 22) ?></div>
      <div class="value"><?= $total_active ?></div>
      <div class="label"><?= lang()==='uz_cyrillic' ? "Фаол таклифлар" : "Faol takliflar" ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon warning"><?= icon('clock', 22) ?></div>
      <div class="value" style="font-size:22px"><?= money($total_pending) ?></div>
      <div class="label"><?= t('pending_bonus') ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FCE7F3;color:#9F1239"><?= icon('gem', 22) ?></div>
      <div class="value" style="font-size:22px"><?= money($total_paid) ?></div>
      <div class="label"><?= t('paid_bonus') ?></div>
    </div>
  </div>

  <!-- Qanday ishlaydi -->
  <div class="card mb-3">
    <h3 style="font-weight:700;margin-bottom:18px;font-size:18px"><?= lang()==='uz_cyrillic' ? "Қандай ишлайди?" : "Qanday ishlaydi?" ?></h3>
    <div class="grid-3">
      <div class="text-center">
        <div style="width:60px;height:60px;background:var(--primary-light);color:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:24px;font-weight:800">1</div>
        <h4 style="font-weight:700;margin-bottom:4px;font-size:15px"><?= lang()==='uz_cyrillic' ? "Ҳаволани улашинг" : "Havolani ulashing" ?></h4>
        <p style="color:var(--text-soft);font-size:13px"><?= lang()==='uz_cyrillic' ? "Реферал ҳаволани дўстларингизга юборинг" : "Referal havolani do'stlaringizga yuboring" ?></p>
      </div>
      <div class="text-center">
        <div style="width:60px;height:60px;background:var(--warning-light);color:var(--warning-dark);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:24px;font-weight:800">2</div>
        <h4 style="font-weight:700;margin-bottom:4px;font-size:15px"><?= lang()==='uz_cyrillic' ? "Дўстлар рўйхатдан ўтади" : "Do'stlar ro'yxatdan o'tadi" ?></h4>
        <p style="color:var(--text-soft);font-size:13px"><?= lang()==='uz_cyrillic' ? "Сизнинг ҳавола орқали аккаунт яратишади" : "Sizning havolangiz orqali akkaunt yaratishadi" ?></p>
      </div>
      <div class="text-center">
        <div style="width:60px;height:60px;background:var(--success-light);color:var(--success-dark);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:24px;font-weight:800">3</div>
        <h4 style="font-weight:700;margin-bottom:4px;font-size:15px"><?= lang()==='uz_cyrillic' ? "Бонус оласиз" : "Bonus olasiz" ?></h4>
        <p style="color:var(--text-soft);font-size:13px"><?= lang()==='uz_cyrillic' ? "Улар тўлов қилгач, балансингизга 5,000 сўм қўшилади" : "Ular to'lov qilgach, balansingizga 5,000 so'm qo'shiladi" ?></p>
      </div>
    </div>
  </div>

  <!-- Ro'yxat -->
  <div class="card" style="padding:0">
    <div style="padding:20px 24px;border-bottom:1px solid var(--border)">
      <h3 style="font-weight:700;font-size:18px"><?= lang()==='uz_cyrillic' ? "Таклиф қилинганлар" : "Taklif qilinganlar" ?></h3>
    </div>
    <?php if (empty($invited)): ?>
      <div class="empty-state">
        <?= icon('users', 64) ?>
        <h3><?= lang()==='uz_cyrillic' ? "Ҳали таклифлар йўқ" : "Hali takliflar yo'q" ?></h3>
        <p><?= lang()==='uz_cyrillic' ? "Биринчи дўстингизни таклиф қилинг!" : "Birinchi do'stingizni taklif qiling!" ?></p>
      </div>
    <?php else: ?>
    <div class="table-wrap table-flat">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th><?= t('name') ?></th>
            <th><?= t('registered_at') ?></th>
            <th><?= t('status') ?></th>
            <th><?= lang()==='uz_cyrillic' ? "Бонус" : "Bonus" ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invited as $i => $r): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td>
              <div class="flex items-center gap-2">
                <div class="review-avatar" style="width:32px;height:32px;font-size:13px"><?= mb_substr($r['first_name'],0,1) ?></div>
                <strong><?= e($r['first_name'].' '.$r['last_name']) ?></strong>
              </div>
            </td>
            <td><?= date('d.m.Y', strtotime($r['created_at'])) ?></td>
            <td>
              <?php if ($r['tariff_id']): ?>
                <span class="badge badge-success">✓ <?= t('active') ?></span>
              <?php else: ?>
                <span class="badge badge-mute"><?= lang()==='uz_cyrillic' ? "Тарифсиз" : "Tarifsiz" ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($r['bonus_amount']):
                $cls = $r['r_status']==='paid'?'success':'warning';
              ?>
                <span class="badge badge-<?= $cls ?>"><?= money($r['bonus_amount']) ?> <?= t('soum') ?></span>
              <?php else: ?>
                <span class="text-mute">—</span>
              <?php endif; ?>
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

<script>
function copyRefLink(){
  const inp = document.getElementById('refLink');
  inp.select(); inp.setSelectionRange(0, 99999);
  navigator.clipboard.writeText(inp.value).then(() => {
    toast('<?= lang()==='uz_cyrillic' ? "Ҳавола нусхаланди!" : "Havola nusxalandi!" ?>', 'success');
  });
}
function shareNative(){
  const link = document.getElementById('refLink').value;
  const text = '<?= lang()==='uz_cyrillic' ? "ВатанПарвар Яйпан автомактаб платформасига қўшилинг!" : "VatanParvar Yaypan avtomaktab platformasiga qo'shiling!" ?>';
  if (navigator.share) {
    navigator.share({title:'<?= e(setting('site_name')) ?>', text, url:link});
  } else {
    copyRefLink();
  }
}
</script>
</body></html>
