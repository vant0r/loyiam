<?php
/**
 * user/referallar.php — STANDALONE referrals page
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$u = current_user();
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

$total_invited = (int)(db()->fetch("SELECT COUNT(*) c FROM users WHERE referred_by = ?", [$u['id']])['c'] ?? 0);
$total_active  = (int)(db()->fetch("SELECT COUNT(*) c FROM users WHERE referred_by = ? AND tariff_id IS NOT NULL", [$u['id']])['c'] ?? 0);
$total_pending = (float)(db()->fetch("SELECT COALESCE(SUM(bonus_amount),0) c FROM referrals WHERE referrer_id = ? AND status='pending'", [$u['id']])['c'] ?? 0);
$total_paid    = (float)(db()->fetch("SELECT COALESCE(SUM(bonus_amount),0) c FROM referrals WHERE referrer_id = ? AND status='paid'", [$u['id']])['c'] ?? 0);

$invited = db()->fetchAll(
    "SELECT u.id, u.first_name, u.last_name, u.created_at, u.tariff_id, r.bonus_amount, r.status r_status
     FROM users u LEFT JOIN referrals r ON r.referred_id = u.id AND r.referrer_id = ?
     WHERE u.referred_by = ? ORDER BY u.created_at DESC LIMIT 50", [$u['id'], $u['id']]);

$ref_link = SITE_URL . '/register.php?ref=' . urlencode($u['referral_code']);
$site_name = setting('site_name', SITE_NAME);
?><!DOCTYPE html>
<html lang="<?= lang() === 'uz_cyrillic' ? 'uz-Cyrl' : 'uz' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#EC4899">
<title><?= e(t('referrals')) ?> — <?= e($site_name) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(setting('site_logo', '/assets/images/logo.svg')) ?>">
<style>
<?= base_css() ?>
<?= panel_css() ?>

/* === USER/REFERALLAR.PHP custom === */
.ref-hero{position:relative;background:linear-gradient(135deg,#EC4899 0%,#8B5CF6 50%,#3B82F6 100%);border-radius:20px;padding:28px;color:#fff;overflow:hidden;box-shadow:0 16px 40px rgba(236,72,153,.3);margin-bottom:18px}
.ref-hero::before{content:'';position:absolute;right:-30px;bottom:-30px;font-size:200px;opacity:.1;line-height:1;font-family:Apple Color Emoji,sans-serif}
.ref-hero::before{content:'🎁'}
.ref-hero h2{color:#fff;font-size:24px;margin-bottom:8px;font-weight:800;line-height:1.2;position:relative}
.ref-hero p{color:#fff;opacity:.92;margin-bottom:18px;font-size:14.5px;max-width:560px;line-height:1.55;position:relative}
.ref-link-box{background:rgba(255,255,255,.18);backdrop-filter:blur(10px);padding:12px 14px;border-radius:12px;display:flex;gap:10px;align-items:center;border:1px solid rgba(255,255,255,.2);position:relative;flex-wrap:wrap}
.ref-link-input{background:transparent;border:none;color:#fff;flex:1;outline:none;font-family:ui-monospace,monospace;font-size:12.5px;min-width:180px;text-overflow:ellipsis}
.btn-copy{background:#fff;color:#EC4899;font-weight:700;padding:8px 14px;border-radius:8px;font-size:12px;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:all .15s}
.btn-copy:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.15)}
.btn-copy.copied{background:#10B981;color:#fff}
.share-row{display:flex;gap:8px;margin-top:14px;flex-wrap:wrap;position:relative}
.btn-share{background:rgba(255,255,255,.2);color:#fff;padding:8px 14px;border-radius:8px;font-size:12.5px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
.btn-share:hover{background:rgba(255,255,255,.3);color:#fff}

.metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(170px,100%),1fr));gap:12px;margin-bottom:18px}
.metric-card{background:#fff;border:1px solid #EEF1F5;border-radius:14px;padding:16px}
.metric-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:10px}
.metric-icon.blue{background:#EFF6FF;color:#2563EB}
.metric-icon.green{background:#D1FAE5;color:#065F46}
.metric-icon.warning{background:#FEF3C7;color:#92400E}
.metric-icon.pink{background:#FCE7F3;color:#9F1239}
.metric-value{font-size:22px;font-weight:800;line-height:1.05;font-variant-numeric:tabular-nums}
.metric-label{font-size:11.5px;color:var(--text-soft);margin-top:3px}

.steps-card{background:#fff;border:1px solid #EEF1F5;border-radius:14px;padding:24px;margin-bottom:18px}
.steps-card h3{font-size:16px;font-weight:700;margin-bottom:18px}
.steps-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
@media (max-width:640px){.steps-grid{grid-template-columns:1fr}}
.step-item{text-align:center;padding:14px}
.step-num{width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-size:20px;font-weight:800}
.step-num.s1{background:var(--primary-light);color:var(--primary-dark)}
.step-num.s2{background:#FEF3C7;color:#92400E}
.step-num.s3{background:#D1FAE5;color:#065F46}
.step-item h4{font-weight:700;margin-bottom:4px;font-size:14px}
.step-item p{color:var(--text-soft);font-size:12.5px;line-height:1.5}

.section-card{background:#fff;border:1px solid #EEF1F5;border-radius:14px;overflow:hidden}
.section-card-head{padding:14px 18px;border-bottom:1px solid #EEF1F5;background:#FAFBFC;font-weight:700;font-size:14px}
.invited-row{display:grid;grid-template-columns:auto 1fr auto auto;gap:12px;align-items:center;padding:12px 18px;border-bottom:1px solid #EEF1F5}
.invited-row:last-child{border-bottom:none}
.invited-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#3B82F6,#1E40AF);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px}
.invited-info strong{font-weight:600;font-size:13.5px}
.invited-info small{display:block;font-size:11.5px;color:var(--text-mute);margin-top:2px}

.empty-state{padding:36px 20px;text-align:center}
.empty-icon{width:60px;height:60px;border-radius:14px;background:#fff;border:1px solid var(--border);margin:0 auto 12px;display:flex;align-items:center;justify-content:center;color:var(--text-mute)}
</style>
</head>
<body>
<div class="layout">
<?= panel_sidebar('user', 'referrals') ?>
<main class="main">

<div class="page-header-modern">
  <div>
    <div class="page-eyebrow"><?= icon('gift', 12) ?> <?= lang()==='uz_cyrillic' ? "Бонуслар" : "Bonuslar" ?></div>
    <h1><?= t('referrals') ?></h1>
    <div class="page-subtitle"><?= lang()==='uz_cyrillic' ? "Дўст таклиф қилинг ва бонус ютинг" : "Do'st taklif qiling va bonus yutib oling" ?></div>
  </div>
</div>

<div class="ref-hero">
  <h2><?= lang()==='uz_cyrillic' ? "Ҳар бир дўст учун — 5,000 сўм бонус!" : "Har bir do'st uchun — 5,000 so'm bonus!" ?></h2>
  <p><?= lang()==='uz_cyrillic' ? "Дўстингизга ҳаволани улашинг. Улар рўйхатдан ўтиб, тўлов қилишганда, сиз бонус оласиз." : "Do'stingizga havolani ulashing. Ular ro'yxatdan o'tib, to'lov qilishganda, siz bonus olasiz." ?></p>
  <div class="ref-link-box">
    <span style="font-size:18px;flex-shrink:0;position:relative">🔗</span>
    <input type="text" id="refLink" value="<?= e($ref_link) ?>" readonly class="ref-link-input">
    <button class="btn-copy" id="btnCopy" onclick="copyRefLink()" type="button"><?= icon('copy', 12) ?> <?= t('copy_link') ?></button>
  </div>
  <div class="share-row">
    <a href="https://t.me/share/url?url=<?= urlencode($ref_link) ?>&text=<?= urlencode(lang()==='uz_cyrillic' ? "ВатанПарвар Яйпан автомактаб платформасига қўшилинг!" : "VatanParvar Yaypan avtomaktab platformasiga qo'shiling!") ?>" target="_blank" class="btn-share"><?= icon('telegram', 13) ?> Telegram</a>
    <button onclick="shareNative()" type="button" class="btn-share" style="cursor:pointer;border:none"><?= icon('send', 13) ?> <?= t('share') ?></button>
  </div>
</div>

<div class="metric-grid">
  <div class="metric-card"><div class="metric-icon blue"><?= icon('users', 18) ?></div><div class="metric-value"><?= $total_invited ?></div><div class="metric-label"><?= t('invited_friends') ?></div></div>
  <div class="metric-card"><div class="metric-icon green"><?= icon('check-circle', 18) ?></div><div class="metric-value"><?= $total_active ?></div><div class="metric-label"><?= lang()==='uz_cyrillic' ? "Фаол" : "Faol" ?></div></div>
  <div class="metric-card"><div class="metric-icon warning"><?= icon('clock', 18) ?></div><div class="metric-value" style="font-size:18px"><?= money($total_pending) ?></div><div class="metric-label"><?= t('pending_bonus') ?></div></div>
  <div class="metric-card"><div class="metric-icon pink"><?= icon('gem', 18) ?></div><div class="metric-value" style="font-size:18px"><?= money($total_paid) ?></div><div class="metric-label"><?= t('paid_bonus') ?></div></div>
</div>

<div class="steps-card">
  <h3><?= lang()==='uz_cyrillic' ? "Қандай ишлайди?" : "Qanday ishlaydi?" ?></h3>
  <div class="steps-grid">
    <div class="step-item">
      <div class="step-num s1">1</div>
      <h4><?= lang()==='uz_cyrillic' ? "Ҳаволани улашинг" : "Havolani ulashing" ?></h4>
      <p><?= lang()==='uz_cyrillic' ? "Реферал ҳаволани дўстларингизга юборинг" : "Referal havolani do'stlaringizga yuboring" ?></p>
    </div>
    <div class="step-item">
      <div class="step-num s2">2</div>
      <h4><?= lang()==='uz_cyrillic' ? "Дўстлар рўйхатдан ўтади" : "Do'stlar ro'yxatdan o'tadi" ?></h4>
      <p><?= lang()==='uz_cyrillic' ? "Сизнинг ҳавола орқали аккаунт яратишади" : "Sizning havolangiz orqali akkaunt yaratishadi" ?></p>
    </div>
    <div class="step-item">
      <div class="step-num s3">3</div>
      <h4><?= lang()==='uz_cyrillic' ? "Бонус оласиз" : "Bonus olasiz" ?></h4>
      <p><?= lang()==='uz_cyrillic' ? "5,000 сўм бонус сизнинг балансингизга" : "5,000 so'm bonus sizning balansingizga" ?></p>
    </div>
  </div>
</div>

<div class="section-card">
  <div class="section-card-head"><?= lang()==='uz_cyrillic' ? "Таклиф қилинганлар" : "Taklif qilinganlar" ?></div>
  <?php if (empty($invited)): ?>
    <div class="empty-state"><div class="empty-icon"><?= icon('users', 28) ?></div><h3 style="font-size:15px"><?= lang()==='uz_cyrillic' ? "Ҳали таклифлар йўқ" : "Hali takliflar yo'q" ?></h3><p style="font-size:13px;color:var(--text-soft);margin-top:6px"><?= lang()==='uz_cyrillic' ? "Биринчи дўстингизни таклиф қилинг!" : "Birinchi do'stingizni taklif qiling!" ?></p></div>
  <?php else: foreach ($invited as $r): ?>
    <div class="invited-row">
      <div class="invited-avatar"><?= mb_substr($r['first_name'],0,1) ?></div>
      <div class="invited-info">
        <strong><?= e($r['first_name'].' '.$r['last_name']) ?></strong>
        <small><?= date('d.m.Y', strtotime($r['created_at'])) ?></small>
      </div>
      <?php if ($r['tariff_id']): ?><span class="badge badge-success">✓ <?= t('active') ?></span>
      <?php else: ?><span class="badge" style="background:var(--bg-mute);color:var(--text-soft)"><?= lang()==='uz_cyrillic' ? "Тарифсиз" : "Tarifsiz" ?></span><?php endif; ?>
      <?php if ($r['bonus_amount']): $cls = $r['r_status']==='paid'?'success':'warning'; ?>
        <span class="badge badge-<?= $cls ?>"><?= money($r['bonus_amount']) ?></span>
      <?php else: ?><span style="color:var(--text-mute)">—</span><?php endif; ?>
    </div>
  <?php endforeach; endif; ?>
</div>

</main>
</div>
<script>
<?= panel_js() ?>

function copyRefLink(){
  const inp = document.getElementById('refLink');
  inp.select();
  inp.setSelectionRange(0, 99999);
  navigator.clipboard.writeText(inp.value).then(() => {
    const b = document.getElementById('btnCopy');
    b.classList.add('copied');
    b.innerHTML = '✓ <?= lang()==='uz_cyrillic' ? "Кўчирилди" : "Ko\'chirildi" ?>';
    setTimeout(() => { b.classList.remove('copied'); b.innerHTML = '📋 <?= addslashes(t('copy_link')) ?>'; }, 2000);
  });
}
function shareNative(){
  if (navigator.share) {
    navigator.share({ title: '<?= e($site_name) ?>', text: '<?= addslashes(lang()==='uz_cyrillic' ? "Автомактаб платформаси" : "Avtomaktab platformasi") ?>', url: '<?= e($ref_link) ?>' });
  } else { copyRefLink(); }
}
</script>
</body></html>
