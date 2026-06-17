<?php
/**
 * Umumiy funksiyalar, header/footer renderi, design system.
 * Tarjimalar ./lang/ ichida
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/icons.php';

// =========================================================
// TARJIMALAR
// =========================================================
function _load_translations(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $cache = [];
    foreach (['uz_latin', 'uz_cyrillic'] as $code) {
        $f = __DIR__ . '/../lang/' . $code . '.php';
        if (file_exists($f)) {
            $data = include $f;
            if (is_array($data)) {
                foreach ($data as $key => $val) {
                    $cache[$key][$code] = $val;
                }
            }
        }
    }
    return $cache;
}

function t(string $key, array $params = []): string {
    $data = _load_translations();
    $lang = $_SESSION['lang'] ?? 'uz_latin';
    $val = $data[$key][$lang] ?? $data[$key]['uz_latin'] ?? $key;
    foreach ($params as $k => $v) $val = str_replace('{'.$k.'}', (string)$v, $val);
    return $val;
}

function lang(): string { return $_SESSION['lang'] ?? 'uz_latin'; }

/** Lotin → Kirill avtomatik konvertor */
function uz_latin_to_cyrillic(string $text): string {
    $map = [
        // 4 belgili
        "sh'" => "ш", "ch'" => "ч",
        // 3 belgili
        "yo'" => "ё", "yu'" => "ю", "ya'" => "я",
        // 2 belgili
        "sh" => "ш", "ch" => "ч", "ng" => "нг", "yo" => "ё",
        "yu" => "ю", "ya" => "я", "ye" => "е", "o'" => "ў", "g'" => "ғ",
        "Sh" => "Ш", "Ch" => "Ч", "Yo" => "Ё", "Yu" => "Ю", "Ya" => "Я",
        "O'" => "Ў", "G'" => "Ғ",
        // 1 belgili
        "a"=>"а","b"=>"б","d"=>"д","e"=>"е","f"=>"ф","g"=>"г","h"=>"ҳ",
        "i"=>"и","j"=>"ж","k"=>"к","l"=>"л","m"=>"м","n"=>"н","o"=>"о",
        "p"=>"п","q"=>"қ","r"=>"р","s"=>"с","t"=>"т","u"=>"у","v"=>"в",
        "x"=>"х","y"=>"й","z"=>"з","'"=>"ъ",
        "A"=>"А","B"=>"Б","D"=>"Д","E"=>"Е","F"=>"Ф","G"=>"Г","H"=>"Ҳ",
        "I"=>"И","J"=>"Ж","K"=>"К","L"=>"Л","M"=>"М","N"=>"Н","O"=>"О",
        "P"=>"П","Q"=>"Қ","R"=>"Р","S"=>"С","T"=>"Т","U"=>"У","V"=>"В",
        "X"=>"Х","Y"=>"Й","Z"=>"З",
    ];
    return strtr($text, $map);
}

// =========================================================
// SOZLAMALAR (DB cache)
// =========================================================
function setting(string $key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        // Try Cache layer first
        $cacheFile = __DIR__ . '/../cache/data/settings.cache';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 600) {
            $cache = @unserialize(@file_get_contents($cacheFile));
        }
        if (!is_array($cache)) {
            $cache = [];
            $rows = db()->fetchAll("SELECT setting_key, setting_value FROM settings");
            foreach ($rows as $r) $cache[$r['setting_key']] = $r['setting_value'];
            // Persist
            @mkdir(dirname($cacheFile), 0755, true);
            @file_put_contents($cacheFile, serialize($cache));
        }
    }
    return $cache[$key] ?? $default;
}

function flush_settings_cache(): void {
    @unlink(__DIR__ . '/../cache/data/settings.cache');
}

// =========================================================
// AUTH yordamchilar
// =========================================================
function is_logged_in(): bool { return isset($_SESSION['user_id']); }
function current_user(): ?array {
    if (!is_logged_in()) return null;
    static $u = null;
    if ($u === null) {
        $u = db()->fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    }
    return $u;
}
function is_admin(): bool {
    $u = current_user();
    return $u && in_array($u['role'], ['admin','developer']);
}
function is_developer(): bool {
    $u = current_user();
    return $u && $u['role'] === 'developer';
}
function require_login(): void {
    if (!is_logged_in()) { header('Location: /login.php'); exit; }
}
function require_admin(): void {
    require_login();
    if (!is_admin()) { header('Location: /'); exit; }
}
function require_developer(): void {
    require_login();
    if (!is_developer()) { header('Location: /'); exit; }
}

// =========================================================
// Yordamchilar
// =========================================================
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($v, string $cur = '') {
    $r = number_format((float)$v, 0, '.', ' ');
    return $cur ? "$r $cur" : $r;
}
function flash(string $key, ?string $msg = null) {
    if ($msg === null) {
        $v = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $v;
    }
    $_SESSION['flash'][$key] = $msg;
}

// =========================================================
// HEADER / FOOTER renderi
// =========================================================
function render_head(string $page_title = '', array $opts = []): void {
    $title = $page_title ? e($page_title) . ' — ' . e(setting('site_name', SITE_NAME)) : e(setting('site_name', SITE_NAME));
    $logo  = e(setting('site_logo', '/assets/images/logo.svg'));
    $desc  = e($opts['description'] ?? setting('seo_description'));
    ?>
<!DOCTYPE html>
<html lang="<?= lang() === 'uz_cyrillic' ? 'uz-Cyrl' : 'uz' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="<?= PRIMARY_COLOR ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?= e(setting('site_name', SITE_NAME)) ?>">
<meta name="format-detection" content="telephone=no, address=no, email=no">
<meta name="color-scheme" content="light">
<title><?= $title ?></title>
<meta name="description" content="<?= $desc ?>">
<meta name="keywords" content="<?= e(setting('seo_keywords')) ?>">
<meta property="og:title" content="<?= $title ?>">
<meta property="og:description" content="<?= $desc ?>">
<meta property="og:image" content="<?= e(setting('site_banner', '/assets/images/banner.svg')) ?>">
<meta property="og:type" content="website">
<link rel="icon" type="image/svg+xml" href="<?= $logo ?>">
<link rel="manifest" href="/manifest.json">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css?v=4">
<style>
/* PHP-interpolated CSS variables (only the dynamic ones) */
:root{--primary-500:<?= PRIMARY_COLOR ?>;--primary:<?= PRIMARY_COLOR ?>}
</style>
<?php if (!empty($opts['extra_head'])) echo $opts['extra_head']; ?>
</head>
<body>
<div class="toast-container" id="toastContainer"></div>
<?php
}

function render_header(string $active = ''): void {
    $logo_text = lang() === 'uz_cyrillic' ? 'ВатанПарвар Яйпан' : 'VatanParvar Yaypan';
?>
<header class="header">
  <div class="container nav">
    <a href="/" class="logo">
      <span class="logo-icon">VP</span>
      <span><?= $logo_text ?></span>
    </a>
    <ul class="nav-menu" id="navMenu">
      <li><a href="/"          class="<?= $active==='home'?'active':'' ?>"><?= t('home') ?></a></li>
      <li><a href="/tariflar.php" class="<?= $active==='tariffs'?'active':'' ?>"><?= t('tariffs') ?></a></li>
      <li><a href="/blog.php"  class="<?= $active==='blog'?'active':'' ?>"><?= t('blog') ?></a></li>
      <li><a href="/aloqa.php" class="<?= $active==='contact'?'active':'' ?>"><?= t('contact') ?></a></li>
    </ul>
    <div class="nav-actions">
      <div class="lang-switch">
        <a href="?setlang=uz_latin"    class="<?= lang()==='uz_latin'?'active':'' ?>">Uz</a>
        <a href="?setlang=uz_cyrillic" class="<?= lang()==='uz_cyrillic'?'active':'' ?>">Кр</a>
      </div>
      <?php if (is_logged_in()): ?>
        <?php $u = current_user(); $panel = is_developer()?'/developer/':(is_admin()?'/admin/':'/user/');
              $unread = class_exists('Notify') ? Notify::unread($u['id']) : 0; ?>

        <!-- Notification bell -->
        <div class="notif-wrap" id="notifWrap">
          <button class="btn-icon btn-light btn-sm notif-bell" onclick="toggleNotif(event)" aria-label="Notifications">
            <?= icon('bell', 18) ?>
            <?php if ($unread > 0): ?>
              <span class="notif-badge"><?= $unread > 99 ? '99+' : $unread ?></span>
            <?php endif; ?>
          </button>
          <div class="notif-dropdown" id="notifDropdown">
            <div class="notif-header">
              <strong><?= lang()==='uz_cyrillic' ? "Хабарномалар" : "Xabarnomalar" ?></strong>
              <?php if ($unread > 0): ?>
                <a href="/api/?action=mark_all_read" onclick="markAllRead(event)" style="font-size:12px"><?= lang()==='uz_cyrillic' ? "Барчасини ўқиш" : "Hammasini o'qish" ?></a>
              <?php endif; ?>
            </div>
            <div class="notif-list" id="notifList">
              <div class="notif-loading">
                <div class="skeleton" style="height:60px;border-radius:8px;margin-bottom:8px"></div>
                <div class="skeleton" style="height:60px;border-radius:8px"></div>
              </div>
            </div>
          </div>
        </div>

        <a href="<?= $panel ?>" class="btn btn-outline btn-sm"><?= icon('user', 16) ?> <?= e($u['first_name']) ?></a>
      <?php else: ?>
        <a href="/login.php" class="btn btn-ghost btn-sm"><?= t('login') ?></a>
        <a href="/register.php" class="btn btn-primary btn-sm"><?= t('register') ?></a>
      <?php endif; ?>
      <button class="menu-toggle" type="button" onclick="window.toggleNav&&window.toggleNav(this)" aria-label="Menu" aria-expanded="false" aria-controls="navMenu">
        <?= icon('menu', 22) ?>
      </button>
    </div>
  </div>
</header>
<?php
}

function render_footer(): void {
    $year = date('Y');
?>
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="logo" style="color:#fff;margin-bottom:14px">
          <span class="logo-icon">VP</span>
          <span><?= e(setting('site_name', SITE_NAME)) ?></span>
        </div>
        <p style="font-size:var(--fs-sm);line-height:var(--lh-relaxed);color:#94A3B8"><?= t('footer_about') ?></p>
        <div class="footer-social">
          <a href="<?= e(setting('telegram_url','#')) ?>" target="_blank" aria-label="Telegram"><?= icon('telegram', 18) ?></a>
          <a href="<?= e(setting('instagram_url','#')) ?>" target="_blank" aria-label="Instagram"><?= icon('instagram', 18) ?></a>
          <a href="<?= e(setting('youtube_url','#')) ?>" target="_blank" aria-label="YouTube"><?= icon('youtube', 18) ?></a>
          <a href="<?= e(setting('facebook_url','#')) ?>" target="_blank" aria-label="Facebook"><?= icon('facebook', 18) ?></a>
        </div>
      </div>
      <div>
        <h4><?= t('quick_links') ?></h4>
        <ul>
          <li><a href="/"><?= t('home') ?></a></li>
          <li><a href="/tariflar.php"><?= t('tariffs') ?></a></li>
          <li><a href="/blog.php"><?= t('blog') ?></a></li>
          <li><a href="/aloqa.php"><?= t('contact') ?></a></li>
        </ul>
      </div>
      <div>
        <h4><?= t('cabinet') ?></h4>
        <ul>
          <li><a href="/login.php"><?= t('login') ?></a></li>
          <li><a href="/register.php"><?= t('register') ?></a></li>
          <li><a href="/user/"><?= t('dashboard') ?></a></li>
        </ul>
      </div>
      <div>
        <h4><?= t('contact') ?></h4>
        <ul>
          <li class="flex items-center gap-2"><?= icon('map-pin', 16) ?> <span><?= e(lang()==='uz_cyrillic' ? setting('site_address_cyrillic') : setting('site_address')) ?></span></li>
          <li class="flex items-center gap-2"><?= icon('phone', 16) ?> <a href="tel:<?= e(setting('site_phone')) ?>"><?= e(setting('site_phone')) ?></a></li>
          <li class="flex items-center gap-2"><?= icon('mail', 16) ?> <a href="mailto:<?= e(setting('site_email')) ?>"><?= e(setting('site_email')) ?></a></li>
          <li class="flex items-center gap-2"><?= icon('clock', 16) ?> <span><?= e(setting('working_hours')) ?></span></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      © <?= $year ?> <?= e(setting('site_name', SITE_NAME)) ?>. <?= t('all_rights') ?>.
    </div>
  </div>
</footer>
<script>
/* ============== CORE JS ============== */
// FAQ accordion
document.querySelectorAll('.faq-item').forEach(item => {
  const q = item.querySelector('.faq-q');
  if (q) q.addEventListener('click', () => {
    document.querySelectorAll('.faq-item').forEach(i => { if (i !== item) i.classList.remove('open'); });
    item.classList.toggle('open');
  });
});

// Sidebar toggle (admin/user panels)
window.toggleSidebar = (force) => {
  const sb = document.querySelector('.sidebar');
  if (!sb) return;
  const willOpen = typeof force === 'boolean' ? force : !sb.classList.contains('open');
  sb.classList.toggle('open', willOpen);
  document.body.classList.toggle('sidebar-open', willOpen);
  document.querySelector('.sidebar-toggle-btn')?.setAttribute('aria-expanded', willOpen);
};

// Mobile nav menu toggle (header hamburger)
window.toggleNav = (btn) => {
  const menu = document.getElementById('navMenu');
  if (!menu) return;
  const willOpen = !menu.classList.contains('open');
  menu.classList.toggle('open', willOpen);
  document.body.classList.toggle('nav-open', willOpen);
  (btn || document.querySelector('.menu-toggle'))?.setAttribute('aria-expanded', willOpen);
};

// Close mobile nav on outside click, link click, or Escape
document.addEventListener('click', (e) => {
  const menu = document.getElementById('navMenu');
  if (menu && menu.classList.contains('open')) {
    if (e.target.closest('.menu-toggle')) return;
    if (e.target.closest('.nav-menu a') || !e.target.closest('.nav-menu')) {
      menu.classList.remove('open');
      document.body.classList.remove('nav-open');
      document.querySelector('.menu-toggle')?.setAttribute('aria-expanded','false');
    }
  }
  // Close sidebar on link click (mobile)
  const sb = document.querySelector('.sidebar.open');
  if (sb && e.target.closest('.sidebar a') && window.innerWidth <= 992) {
    setTimeout(() => window.toggleSidebar(false), 150);
  }
});
document.addEventListener('keydown', e => {
  if (e.key !== 'Escape') return;
  const menu = document.getElementById('navMenu');
  if (menu?.classList.contains('open')) {
    menu.classList.remove('open');
    document.body.classList.remove('nav-open');
    document.querySelector('.menu-toggle')?.setAttribute('aria-expanded','false');
  }
  if (document.querySelector('.sidebar.open')) window.toggleSidebar(false);
});

// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const id = a.getAttribute('href');
    if (id.length > 1) {
      const t = document.querySelector(id);
      if (t) { e.preventDefault(); t.scrollIntoView({behavior:'smooth'}); }
    }
  });
});

// Modal helpers
window.openModal = id => {
  const m = typeof id === 'string' ? document.getElementById(id) : id;
  if (m) {
    m.classList.add('show');
    document.body.style.overflow = 'hidden';
    // Focus first input
    setTimeout(() => {
      const firstInput = m.querySelector('input:not([type=hidden]):not([disabled]), textarea, select');
      if (firstInput) firstInput.focus();
    }, 100);
  }
};
window.closeModal = id => {
  const m = id
    ? (typeof id === 'string' ? document.getElementById(id) : id)
    : document.querySelector('.modal-backdrop.show');
  if (!m) return;
  const modal = m.querySelector('.modal');
  if (modal) {
    modal.style.animation = 'modalSlideOut .25s ease forwards';
    m.style.animation = 'fadeOut .25s ease forwards';
    setTimeout(() => {
      m.classList.remove('show');
      modal.style.animation = '';
      m.style.animation = '';
      document.body.style.overflow = '';
    }, 250);
  } else {
    m.classList.remove('show');
    document.body.style.overflow = '';
  }
};
// Yagona modal handler (data-modal-open / data-modal-close / backdrop click)
document.addEventListener('click', e => {
  const closeTrigger = e.target.closest('[data-modal-close]');
  if (closeTrigger) { e.preventDefault(); closeModal(closeTrigger.closest('.modal-backdrop')); return; }

  const openTrigger = e.target.closest('[data-modal-open]');
  if (openTrigger) { e.preventDefault(); openModal(openTrigger.dataset.modalOpen); return; }

  // Backdrop bosish (modal ichida emas)
  if (e.target.classList.contains('modal-backdrop')) {
    closeModal(e.target);
  }
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// Toast
window.toast = (msg, type='info', duration=4000) => {
  const c = document.getElementById('toastContainer');
  if (!c) return;
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  const icons = {success:'\u2713', danger:'\u2715', warning:'!', info:'i'};
  t.innerHTML = '<span class="toast-icon" style="font-weight:bold;font-size:18px;width:20px;text-align:center">'+icons[type]+'</span><div class="toast-body">'+msg+'</div><button class="toast-close" onclick="this.parentElement.remove()">\u2715</button>';
  c.appendChild(t);
  setTimeout(() => { t.style.animation='fadeOut .3s forwards'; setTimeout(()=>t.remove(), 300); }, duration);
};

// CountUp animation
window.countUp = (el, target, duration=1500) => {
  const start = performance.now();
  const animate = (now) => {
    const elapsed = now - start;
    const progress = Math.min(elapsed/duration, 1);
    const eased = 1 - Math.pow(1-progress, 3);
    el.textContent = Math.floor(target * eased).toLocaleString();
    if (progress < 1) requestAnimationFrame(animate);
    else el.textContent = target.toLocaleString();
  };
  requestAnimationFrame(animate);
};
// Auto-trigger countup when in view
const observer = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const el = entry.target;
      const target = parseInt(el.dataset.count, 10);
      if (!isNaN(target) && !el.dataset.counted) {
        countUp(el, target);
        el.dataset.counted = '1';
      }
    }
  });
}, {threshold:.2});
document.querySelectorAll('[data-count]').forEach(el => observer.observe(el));

// Form button loading state — defer disable to NOT block submission
document.addEventListener('submit', e => {
  const form = e.target;
  if (form.tagName !== 'FORM' || form.dataset.noLoading) return;
  const btn = e.submitter || form.querySelector('button[type="submit"]:not([data-no-loading])');
  if (btn && !btn.classList.contains('btn-loading') && !btn.disabled) {
    // Defer to next tick — form already submits with button enabled
    setTimeout(() => {
      btn.classList.add('btn-loading');
      btn.disabled = true;
    }, 0);
    // Avto-restore agar form 20 soniyada javob qaytarmasa
    setTimeout(() => {
      btn.classList.remove('btn-loading');
      btn.disabled = false;
    }, 20000);
  }
});

// Show password toggle
document.querySelectorAll('[data-toggle-password]').forEach(btn => {
  btn.addEventListener('click', () => {
    const target = document.getElementById(btn.dataset.togglePassword);
    if (target) target.type = target.type === 'password' ? 'text' : 'password';
  });
});

// Confirm helper
window.confirmAction = (msg) => confirm(msg || 'Davom ettirilsinmi?');

// PWA — Service Worker registration
if ('serviceWorker' in navigator && location.protocol === 'https:') {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(err => console.warn('SW reg failed', err));
  });
}

// =============== NOTIFICATIONS ===============
let notifLoaded = false;
window.toggleNotif = (e) => {
  if (e) e.stopPropagation();
  const wrap = document.getElementById('notifWrap');
  if (!wrap) return;
  wrap.classList.toggle('open');
  if (wrap.classList.contains('open') && !notifLoaded) loadNotifications();
};
async function loadNotifications(){
  const list = document.getElementById('notifList');
  if (!list) return;
  try {
    const r = await fetch('/api/?action=notifications');
    const data = await r.json();
    if (data.ok && data.items) {
      renderNotifications(data.items);
      notifLoaded = true;
    } else {
      list.innerHTML = '<div class="notif-empty">Xabarnoma yo\u2019q</div>';
    }
  } catch (e) {
    list.innerHTML = '<div class="notif-empty">Yuklashda xato</div>';
  }
}
function renderNotifications(items){
  const list = document.getElementById('notifList');
  if (!items.length) {
    list.innerHTML = '<div class="notif-empty">Xabarnoma yo\u2019q</div>';
    return;
  }
  const colorMap = {success:'success',danger:'danger',warning:'warning',info:'info'};
  const html = items.map(n => {
    const time = formatRelativeTime(n.created_at);
    const color = colorMap[n.color] || 'info';
    const iconSvg = n.icon_svg || '';
    return `<a href="${n.link || '#'}" class="notif-item ${n.is_read==0?'unread':''}" data-id="${n.id}" onclick="markRead(${n.id})">
      <div class="notif-icon-wrap ${color}">${iconSvg}</div>
      <div class="notif-body">
        <div class="notif-title">${escapeHtml(n.title)}</div>
        <div class="notif-msg">${escapeHtml(n.message || '')}</div>
        <div class="notif-time">${time}</div>
      </div>
    </a>`;
  }).join('');
  list.innerHTML = html;
}
function escapeHtml(s){
  return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}
function formatRelativeTime(ts){
  const d = new Date(ts.replace(' ', 'T'));
  const diff = (Date.now() - d.getTime()) / 1000;
  if (diff < 60) return 'hozir';
  if (diff < 3600) return Math.floor(diff/60) + ' daq oldin';
  if (diff < 86400) return Math.floor(diff/3600) + ' soat oldin';
  if (diff < 604800) return Math.floor(diff/86400) + ' kun oldin';
  return d.toLocaleDateString('uz-UZ');
}
async function markRead(id){
  try { await fetch('/api/?action=mark_read&id=' + id); } catch(e){}
}
async function markAllRead(e){
  if (e) e.preventDefault();
  try {
    await fetch('/api/?action=mark_all_read');
    document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
    document.querySelector('.notif-badge')?.remove();
  } catch(e){}
}
// Tashqariga bosganda yopish
document.addEventListener('click', e => {
  if (!e.target.closest('.notif-wrap')) {
    document.getElementById('notifWrap')?.classList.remove('open');
  }
});

// Lazy loading polyfill (modern browsers ham support qiladi native loading="lazy")
document.querySelectorAll('img:not([loading])').forEach(img => img.loading = 'lazy');

// =============== ENHANCED INTERACTIONS (v2.1) ===============

// Reveal on scroll (IntersectionObserver)
(function(){
  if (!('IntersectionObserver' in window)) return;
  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        io.unobserve(entry.target);
      }
    });
  }, {threshold: 0.12, rootMargin: '0px 0px -40px 0px'});
  document.querySelectorAll('.reveal-on-scroll, .fade-up, .reveal-left, .reveal-right, .reveal-up').forEach(el => io.observe(el));
})();

// Auto-add reveal-on-scroll to sections (lekin already animated bo'lsa skip)
document.querySelectorAll('.section .card:not(.fade-up):not(.reveal-on-scroll), .grid-3 > *:not(.fade-up):not(.reveal-on-scroll), .grid-4 > *:not(.fade-up):not(.reveal-on-scroll)').forEach(el => {
  if (!el.closest('.no-auto-reveal')) el.classList.add('reveal-on-scroll');
});

// Re-observe newly added items
const lateIO = new IntersectionObserver((entries) => {
  entries.forEach(entry => entry.isIntersecting && entry.target.classList.add('visible'));
}, {threshold: 0.1});
document.querySelectorAll('.reveal-on-scroll').forEach(el => lateIO.observe(el));

// Ripple effect on buttons
document.addEventListener('click', e => {
  const btn = e.target.closest('.btn:not(.btn-loading):not(.no-ripple)');
  if (!btn) return;
  const rect = btn.getBoundingClientRect();
  const ripple = document.createElement('span');
  ripple.className = 'ripple';
  const size = Math.max(rect.width, rect.height);
  ripple.style.width = ripple.style.height = size + 'px';
  ripple.style.left = (e.clientX - rect.left - size/2) + 'px';
  ripple.style.top  = (e.clientY - rect.top  - size/2) + 'px';
  btn.appendChild(ripple);
  setTimeout(() => ripple.remove(), 600);
});

// Header shadow on scroll
(function(){
  const hdr = document.querySelector('.header');
  if (!hdr) return;
  let last = 0;
  const onScroll = () => {
    const y = window.scrollY;
    if (y > 8) hdr.style.boxShadow = '0 4px 20px rgba(15,23,42,.06)';
    else hdr.style.boxShadow = '';
    // Hide-on-scroll-down (only on mobile)
    if (window.innerWidth < 720) {
      if (y > last && y > 200) hdr.style.transform = 'translateY(-100%)';
      else hdr.style.transform = 'translateY(0)';
      hdr.style.transition = 'transform .3s ease';
    }
    last = y;
  };
  window.addEventListener('scroll', onScroll, {passive:true});
})();

// Mobile sidebar overlay (markup-rendered now, but JS sync for legacy pages)
(function(){
  const sidebar = document.querySelector('.sidebar');
  if (!sidebar) return;
  let overlay = document.querySelector('.sidebar-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    sidebar.parentNode.insertBefore(overlay, sidebar.nextSibling);
  }
  overlay.addEventListener('click', () => window.toggleSidebar?.(false));
})();

// Auto-add data-label attributes to table cells (for mobile responsive table)
document.querySelectorAll('table.table-responsive, .table-responsive table').forEach(tbl => {
  const headers = [...tbl.querySelectorAll('thead th')].map(th => th.textContent.trim());
  tbl.querySelectorAll('tbody tr').forEach(tr => {
    [...tr.children].forEach((td, i) => {
      if (headers[i] && !td.dataset.label) td.dataset.label = headers[i];
    });
  });
});

// Dropdown toggle
document.addEventListener('click', e => {
  const trigger = e.target.closest('[data-dropdown]');
  if (trigger) {
    const drop = trigger.closest('.dropdown') || document.getElementById(trigger.dataset.dropdown);
    if (drop) drop.classList.toggle('open');
    e.stopPropagation();
  } else {
    document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
  }
});

// Counter animation pulse
window.countUp = (el, target, duration=1500) => {
  const start = performance.now();
  const animate = (now) => {
    const elapsed = now - start;
    const progress = Math.min(elapsed/duration, 1);
    const eased = 1 - Math.pow(1-progress, 3);
    el.textContent = Math.floor(target * eased).toLocaleString();
    el.classList.add('counting');
    if (progress < 1) requestAnimationFrame(animate);
    else { el.textContent = target.toLocaleString(); setTimeout(()=>el.classList.remove('counting'), 200); }
  };
  requestAnimationFrame(animate);
};

// Touch swipe support for carousels
document.querySelectorAll('.carousel-track').forEach(track => {
  let startX = 0, scrollLeft = 0, isDown = false;
  track.addEventListener('touchstart', e => {
    isDown = true; startX = e.touches[0].pageX; scrollLeft = track.scrollLeft;
  }, {passive:true});
  track.addEventListener('touchmove', e => {
    if (!isDown) return;
    const x = e.touches[0].pageX;
    track.scrollLeft = scrollLeft - (x - startX);
  }, {passive:true});
  track.addEventListener('touchend', () => isDown = false);
});

// Keyboard shortcuts
document.addEventListener('keydown', e => {
  // Cmd/Ctrl + K — focus search
  if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
    const search = document.querySelector('input[name="q"], input[type="search"]');
    if (search) { e.preventDefault(); search.focus(); }
  }
  // / — focus first input on page
  if (e.key === '/' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
    const inp = document.querySelector('input:not([type=hidden]):not([disabled])');
    if (inp) { e.preventDefault(); inp.focus(); }
  }
});

// Smooth count-up for [data-count] when in viewport (override existing)
const countObserver = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const el = entry.target;
      const target = parseInt(el.dataset.count, 10);
      if (!isNaN(target) && !el.dataset.counted) {
        countUp(el, target);
        el.dataset.counted = '1';
      }
    }
  });
}, {threshold:.2});
document.querySelectorAll('[data-count]').forEach(el => countObserver.observe(el));

// Auto theme-color update on scroll (subtle)
const themeMeta = document.querySelector('meta[name="theme-color"]');
if (themeMeta) {
  let scrolled = false;
  window.addEventListener('scroll', () => {
    const isScrolled = window.scrollY > 100;
    if (scrolled !== isScrolled) {
      scrolled = isScrolled;
      themeMeta.setAttribute('content', scrolled ? '#1E40AF' : '#3B82F6');
    }
  }, {passive:true});
}

// =============== ANIMATION CLEANUP (performance) ===============
// will-change ni animation tugagach o'chirish — RAM tejash
document.addEventListener('animationend', e => {
  if (e.target.classList.contains('fade-up') ||
      e.target.classList.contains('reveal-on-scroll')) {
    e.target.classList.add('animation-end');
  }
}, true);

// Smoother stagger (animate only when visible)
const staggerObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('stagger-visible');
      staggerObserver.unobserve(entry.target);
    }
  });
}, {threshold: 0.1, rootMargin: '0px 0px -60px 0px'});
document.querySelectorAll('.stagger').forEach(el => {
  el.classList.add('stagger-paused');
  staggerObserver.observe(el);
});

// Smoother image loading (skeleton effect)
document.querySelectorAll('img[loading="lazy"]').forEach(img => {
  if (img.complete) return;
  img.dataset.loading = '1';
  img.addEventListener('load', () => {
    delete img.dataset.loading;
    img.style.opacity = '1';
  }, {once:true});
  img.addEventListener('error', () => {
    delete img.dataset.loading;
  }, {once:true});
});
</script>
<?php
}

/** Sidebar (panel uchun) */
function render_sidebar(string $type, string $active): void {
    $u = current_user();
    $items = [];
    if ($type === 'user') {
        $items = [
            ['dashboard','/user/',           'dashboard',  t('dashboard')],
            ['tests',    '/user/testlar.php','document',   t('tests')],
            ['results',  '/user/natijalar.php','chart',    t('results')],
            ['rating',   '/user/reyting.php','trophy',     t('rating')],
            ['profile',  '/user/profil.php', 'user',       t('profile')],
            ['tariffs',  '/user/tariflar.php','gem',       t('tariffs')],
            ['referrals','/user/referallar.php','gift',    t('referrals')],
        ];
    } elseif ($type === 'admin') {
        $items = [
            ['dashboard','/admin/',           'dashboard', t('dashboard')],
            ['users',    '/admin/users.php', 'users',     t('users')],
            ['questions','/admin/savollar.php','help',    t('questions')],
            ['tickets',  '/admin/biletlar.php','ticket',  t('tickets')],
            ['payments', '/admin/tolovlar.php','card',    t('payments')],
            ['tariffs',  '/admin/tariflar.php','gem',     t('tariffs')],
            ['blog',     '/admin/blog.php',  'edit',      t('blog')],
            ['reviews',  '/admin/sharhlar.php','star',    t('reviews')],
            ['logs',     '/admin/loglar.php','logs',      t('logs')],
            ['settings', '/admin/sozlamalar.php','settings',t('settings')],
        ];
    } elseif ($type === 'developer') {
        $items = [
            ['dashboard','/developer/','dashboard','Dashboard'],
            ['db',       '/developer/#db','database','Database'],
            ['mig',      '/developer/#mig','refresh','Migratsiyalar'],
            ['cache',    '/developer/#cache','zap','Cache'],
            ['logs',     '/developer/#logs','logs','Loglar'],
            ['api',      '/developer/#api','code','API'],
        ];
    }
    $logo_text = lang()==='uz_cyrillic' ? 'ВП Яйпан' : 'VP Yaypan';
?>
<aside class="sidebar" id="appSidebar">
  <div class="sidebar-logo">
    <span class="logo-icon">VP</span>
    <span><?= $logo_text ?></span>
  </div>
  <ul class="sidebar-menu">
    <?php foreach ($items as $it): ?>
      <li><a href="<?= $it[1] ?>" class="<?= $active===$it[0]?'active':'' ?>">
        <?= icon($it[2], 18) ?> <span><?= $it[3] ?></span></a></li>
    <?php endforeach; ?>
  </ul>
  <div class="sidebar-bottom">
    <?php if ($u): ?>
    <div class="sidebar-user">
      <div class="review-avatar" style="width:36px;height:36px;font-size:14px;flex-shrink:0">
        <?= mb_substr($u['first_name'],0,1) ?>
      </div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= e($u['first_name'].' '.$u['last_name']) ?></div>
        <div class="sidebar-user-role"><?= e($u['role']) ?></div>
      </div>
    </div>
    <?php endif; ?>
    <a href="/" class="sidebar-menu-item" style="display:flex;align-items:center;gap:10px;padding:10px 14px;color:#94A3B8;font-size:13px;border-radius:6px">
      <?= icon('home', 16) ?> <?= t('home') ?>
    </a>
    <a href="/logout.php" style="display:flex;align-items:center;gap:10px;padding:10px 14px;color:#FCA5A5;font-size:13px;border-radius:6px">
      <?= icon('logout', 16) ?> <?= t('logout') ?>
    </a>
  </div>
</aside>
<div class="sidebar-overlay" onclick="window.toggleSidebar&&window.toggleSidebar(false)"></div>
<button class="sidebar-toggle-btn" type="button" onclick="window.toggleSidebar&&window.toggleSidebar()" aria-label="<?= lang()==='uz_cyrillic' ? 'Меню' : 'Menyu' ?>" aria-expanded="false" aria-controls="appSidebar">
  <?= icon('menu', 22) ?>
</button>
<?php
    // Mobile bottom nav (avto)
    if ($type === 'user' || $type === 'admin') render_bottom_nav($type, $active);
}

function do_logout(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header('Location: /');
    exit;
}

/**
 * Mobile bottom navigation (faqat user paneli uchun)
 */
function render_bottom_nav(string $type, string $active): void {
    $items = [];
    if ($type === 'user') {
        $items = [
            ['dashboard','/user/',           'dashboard',  t('dashboard')],
            ['tests',    '/user/testlar.php','document',   t('tests')],
            ['rating',   '/user/reyting.php','trophy',     t('rating')],
            ['tariffs',  '/user/tariflar.php','gem',       t('tariffs')],
            ['profile',  '/user/profil.php', 'user',       t('profile')],
        ];
    } elseif ($type === 'admin') {
        $items = [
            ['dashboard','/admin/',           'dashboard', t('dashboard')],
            ['users',    '/admin/users.php', 'users',     t('users')],
            ['payments', '/admin/tolovlar.php','card',    t('payments')],
            ['questions','/admin/savollar.php','help',    t('questions')],
            ['settings', '/admin/sozlamalar.php','settings',t('settings')],
        ];
    }
    if (empty($items)) return;
?>
<nav class="bottom-nav bottom-nav-show">
  <?php foreach ($items as $it): ?>
    <a href="<?= $it[1] ?>" class="<?= $active===$it[0]?'active':'' ?>">
      <?= icon($it[2], 22) ?>
      <span><?= $it[3] ?></span>
    </a>
  <?php endforeach; ?>
</nav>
<script>document.body.classList.add('has-bottom-nav');</script>
<?php
}
