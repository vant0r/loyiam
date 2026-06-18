<?php
/**
 * BOOTSTRAP — minimal backend-only entrypoint.
 *
 * Faqat PHP backend funksiyalari yuklaydi (DB, auth, security, helpers).
 * HEC QANDAY HTML/CSS/JS RENDER QILMAYDI — har sahifa o'z view'ini yozadi.
 *
 * Standalone sahifa shabloni:
 *   <?php require_once __DIR__ . '/includes/bootstrap.php';
 *   // backend logic ...
 *   ?>
 *   <!DOCTYPE html>
 *   <html><head>... own <style> ...</head>
 *   <body>... own HTML + own <script> ...</body></html>
 */

require_once __DIR__ . '/config.php';      // session, DB const, env, security headers
require_once __DIR__ . '/database.php';    // db()
require_once __DIR__ . '/icons.php';       // icon()
require_once __DIR__ . '/security.php';    // Security::*
require_once __DIR__ . '/scrape_guard.php';// ScrapeGuard::guard() (auto-call)
require_once __DIR__ . '/notifications.php'; // Notify

// =========================================================
// TARJIMALAR (functions.php'dagi bilan bir xil — duplicated to avoid coupling)
// =========================================================
if (!function_exists('_load_translations')) {
    function _load_translations(): array {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = [];
        foreach (['uz_latin', 'uz_cyrillic'] as $code) {
            $f = __DIR__ . '/../lang/' . $code . '.php';
            if (file_exists($f)) {
                $data = include $f;
                if (is_array($data)) {
                    foreach ($data as $key => $val) $cache[$key][$code] = $val;
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
}

// =========================================================
// SETTINGS (DB-cached)
// =========================================================
if (!function_exists('setting')) {
    function setting(string $key, $default = '') {
        static $cache = null;
        if ($cache === null) {
            $cacheFile = __DIR__ . '/../cache/data/settings.json';
            if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 600) {
                $raw = @file_get_contents($cacheFile);
                $decoded = $raw ? json_decode($raw, true) : null;
                if (is_array($decoded)) $cache = $decoded;
            }
            if (!is_array($cache)) {
                $cache = [];
                try {
                    $rows = db()->fetchAll("SELECT setting_key, setting_value FROM settings");
                    foreach ($rows as $r) $cache[$r['setting_key']] = $r['setting_value'];
                } catch (\Throwable $e) {}
                @mkdir(dirname($cacheFile), 0755, true);
                @file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE), LOCK_EX);
            }
        }
        return $cache[$key] ?? $default;
    }
/**
 * Panel sahifalari uchun shared CSS (admin/user sidebar layout).
 * Har sahifa bu STRING'ni o'z <style> ichiga inline qiladi.
 */
function panel_css(): string {
    return <<<'CSS'
/* === PANEL CHROME (sidebar + main layout) === */
.layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
.sidebar{
  background:linear-gradient(180deg,#0B1220 0%,#0F172A 50%,#131F35 100%);
  color:#CBD5E1;position:sticky;top:0;height:100vh;overflow-y:auto;padding:18px 0;
  display:flex;flex-direction:column;
}
.sidebar-logo{padding:0 18px 14px;border-bottom:1px solid rgba(255,255,255,.08);
  margin-bottom:10px;display:flex;align-items:center;gap:10px;color:#fff;font-weight:700}
.sidebar-logo .li{width:36px;height:36px;background:linear-gradient(135deg,#3B82F6,#1E40AF);border-radius:9px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:13px}
.sidebar-menu{list-style:none;padding:6px 10px;flex:1;margin:0}
.sidebar-menu li{margin-bottom:2px}
.sidebar-menu a{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:8px;
  color:#94A3B8;font-size:13.5px;font-weight:500;transition:all .15s;text-decoration:none}
.sidebar-menu a:hover{background:rgba(255,255,255,.05);color:#fff}
.sidebar-menu a.active{background:#3B82F6;color:#fff;box-shadow:0 4px 14px rgba(59,130,246,.4)}
.sidebar-bottom{padding:10px 10px;border-top:1px solid rgba(255,255,255,.08)}
.sidebar-user{padding:10px 12px;background:rgba(255,255,255,.04);border-radius:8px;
  display:flex;align-items:center;gap:10px;margin-bottom:6px}
.sidebar-user-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#3B82F6,#1E40AF);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0}
.sidebar-user-info{flex:1;overflow:hidden;min-width:0}
.sidebar-user-name{color:#fff;font-size:13px;font-weight:600;text-overflow:ellipsis;overflow:hidden;white-space:nowrap}
.sidebar-user-role{color:#94A3B8;font-size:11px}
.sidebar-link{display:flex;align-items:center;gap:10px;padding:9px 12px;color:#94A3B8;font-size:12.5px;border-radius:6px;text-decoration:none}
.sidebar-link:hover{background:rgba(255,255,255,.05);color:#fff}
.sidebar-link.danger{color:#FCA5A5}

.main{padding:22px 28px;background:var(--bg-soft);overflow-x:auto;min-width:0}
.page-header-modern{display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:14px;padding-bottom:18px;margin-bottom:22px;border-bottom:1px solid #EEF1F5}
.page-eyebrow{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-mute);margin-bottom:6px;display:inline-flex;align-items:center;gap:6px}
.page-header-modern h1{font-size:clamp(22px,2.4vw,30px);font-weight:800;letter-spacing:-.02em;margin:0;color:var(--text);line-height:1.15}
.page-subtitle{font-size:14px;color:var(--text-soft);margin-top:6px}
.page-toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap}

/* Mobile sidebar toggle */
.sidebar-toggle-btn{
  display:none;position:fixed;top:14px;left:14px;
  width:42px;height:42px;align-items:center;justify-content:center;
  border-radius:8px;background:#fff;color:var(--text);
  border:1px solid var(--border);box-shadow:var(--shadow-sm);
  z-index:1001;cursor:pointer;
}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);z-index:999;cursor:pointer}

@media (max-width:992px){
  .layout{grid-template-columns:1fr}
  .sidebar{position:fixed;left:-100%;top:0;width:min(86vw,300px);z-index:1000;transition:left .3s;box-shadow:8px 0 32px rgba(15,23,42,.2)}
  .sidebar.open{left:0}
  .sidebar.open ~ .sidebar-overlay{display:block}
  .sidebar-toggle-btn{display:inline-flex}
  .main{padding:60px 16px 16px}
}

/* Bottom nav (mobile) */
.bottom-nav{
  position:fixed;bottom:0;left:0;right:0;display:none;
  background:rgba(255,255,255,.96);backdrop-filter:blur(20px);
  border-top:1px solid var(--border);
  padding:6px 4px;padding-bottom:max(6px,env(safe-area-inset-bottom));
  z-index:50;justify-content:space-around;
}
.bottom-nav a{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;flex:1;min-height:54px;padding:6px 4px;color:var(--text-mute);font-size:10px;font-weight:500;border-radius:6px;text-decoration:none}
.bottom-nav a.active{color:var(--primary);background:var(--primary-light)}
@media (max-width:880px){.bottom-nav.show{display:flex}body.has-bn{padding-bottom:72px}}
CSS;
}

/**
 * Panel sidebar HTML render qiladi (string qaytaradi)
 * @param string $type 'user'|'admin'|'developer'
 * @param string $active aktiv menyu kaliti
 */
function panel_sidebar(string $type, string $active): string {
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
        ];
    }
    $logo_text = lang()==='uz_cyrillic' ? 'ВП' : 'VP';
    $first_letter = $u ? mb_strtoupper(mb_substr($u['first_name'],0,1)) : '?';

    ob_start();
    ?>
<aside class="sidebar" id="appSidebar">
  <div class="sidebar-logo"><span class="li"><?= e($logo_text) ?></span><span><?= e(setting('site_name', SITE_NAME)) ?></span></div>
  <ul class="sidebar-menu">
    <?php foreach ($items as $it): ?>
      <li><a href="<?= e($it[1]) ?>" class="<?= $active===$it[0]?'active':'' ?>"><?= icon($it[2], 17) ?> <span><?= e($it[3]) ?></span></a></li>
    <?php endforeach; ?>
  </ul>
  <div class="sidebar-bottom">
    <?php if ($u): ?>
    <div class="sidebar-user">
      <div class="sidebar-user-avatar"><?= $first_letter ?></div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= e($u['first_name'].' '.$u['last_name']) ?></div>
        <div class="sidebar-user-role"><?= e($u['role']) ?></div>
      </div>
    </div>
    <?php endif; ?>
    <a href="/" class="sidebar-link"><?= icon('home', 14) ?> <?= t('home') ?></a>
    <form method="post" action="/logout.php" style="margin:0">
      <?= csrf_field() ?>
      <button type="submit" class="sidebar-link danger" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;font-family:inherit"><?= icon('logout', 14) ?> <?= t('logout') ?></button>
    </form>
  </div>
</aside>
<div class="sidebar-overlay" onclick="document.getElementById('appSidebar').classList.remove('open')"></div>
<button type="button" class="sidebar-toggle-btn" onclick="document.getElementById('appSidebar').classList.toggle('open')" aria-label="Menu"><?= icon('menu', 20) ?></button>
<?php
    return ob_get_clean();
}

/**
 * Panel JS — sidebar toggle, modal, toast helperlar (string qaytaradi)
 */
function panel_js(): string {
    return <<<'JS'
// Modal helpers
window.openModal = id => {
  const m = typeof id === 'string' ? document.getElementById(id) : id;
  if (m) { m.classList.add('show'); document.body.style.overflow = 'hidden'; }
};
window.closeModal = id => {
  const m = id ? (typeof id === 'string' ? document.getElementById(id) : id) : document.querySelector('.modal-backdrop.show');
  if (m) { m.classList.remove('show'); document.body.style.overflow = ''; }
};
document.addEventListener('click', e => {
  const close = e.target.closest('[data-modal-close]');
  if (close) { e.preventDefault(); closeModal(close.closest('.modal-backdrop')); return; }
  const open = e.target.closest('[data-modal-open]');
  if (open) { e.preventDefault(); openModal(open.dataset.modalOpen); return; }
  if (e.target.classList.contains('modal-backdrop')) closeModal(e.target);
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
JS;
}

}
// </function_exists guard for setting>


// =========================================================
// AUTH yordamchilar
// =========================================================
if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool { return isset($_SESSION['user_id']); }
    function current_user(): ?array {
        if (!is_logged_in()) return null;
        static $u = null;
        if ($u === null) {
            try { $u = db()->fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]); }
            catch (\Throwable $e) { $u = null; }
        }
        return $u;
    }
    function is_admin(): bool {
        $u = current_user();
        return $u && in_array($u['role'], ['admin','developer'], true);
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
}

// =========================================================
// Yordamchilar
// =========================================================
if (!function_exists('e')) {
    function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
    function money($v, string $cur = ''): string {
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
}

/**
 * Auto-load Auth class (only when needed; /login.php, /forgot.php etc.)
 */
function auth_class(): void {
    if (!class_exists('Auth')) {
        require_once __DIR__ . '/auth.php';
    }
}

/**
 * Standalone sahifaning shared CSS bazasini qaytaradi (~5KB).
 * Har sahifa BOSHIDA <style> ichiga oladi, keyin o'z customlarini qo'shadi.
 *
 * Bu yagona "shared" element — lekin u STRING qaytaradi, file emas.
 * Sahifa output'ida tashqi <link> bo'lmaydi.
 */
function base_css(): string {
    return <<<'CSS'
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html{-webkit-text-size-adjust:100%;text-size-adjust:100%}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif,'Apple Color Emoji';font-size:15px;line-height:1.6;color:#0F172A;background:#fff;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
a{color:#3B82F6;text-decoration:none;transition:color .15s}
a:hover{color:#2563EB}
img,svg{max-width:100%;display:block}
button{font-family:inherit;cursor:pointer;border:none;background:none;color:inherit;-webkit-tap-highlight-color:transparent}
button:focus-visible,a:focus-visible{outline:2px solid #3B82F6;outline-offset:2px;border-radius:4px}
input,select,textarea{font-family:inherit;font-size:16px}
input:focus,textarea:focus,select:focus{outline:none}
:root{
  --primary:#3B82F6;--primary-dark:#2563EB;--primary-light:#DBEAFE;--primary-50:#EFF6FF;--primary-200:#BFDBFE;--primary-700:#1D4ED8;
  --text:#0F172A;--text-soft:#475569;--text-mute:#94A3B8;
  --border:#E2E8F0;--border-strong:#CBD5E1;
  --bg:#fff;--bg-soft:#F8FAFC;--bg-mute:#F1F5F9;--bg-hover:#E2E8F0;
  --success:#10B981;--success-light:#D1FAE5;--success-dark:#065F46;
  --warning:#F59E0B;--warning-light:#FEF3C7;--warning-dark:#92400E;
  --danger:#EF4444;--danger-light:#FEE2E2;--danger-dark:#991B1B;
  --r-sm:6px;--r-md:8px;--r-lg:12px;--r-xl:16px;--r-2xl:24px;--r-full:9999px;
  --shadow-sm:0 1px 3px rgba(15,23,42,.06);
  --shadow:0 4px 12px rgba(15,23,42,.08);
  --shadow-lg:0 12px 28px rgba(15,23,42,.10);
  --t-fast:.15s ease;--t-base:.25s ease;
  --container:1200px;
}
.container{max-width:var(--container);margin:0 auto;padding:0 20px}
h1,h2,h3,h4,h5,h6{font-weight:700;line-height:1.15;letter-spacing:-.015em;color:var(--text)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 20px;min-height:46px;border-radius:var(--r-md);font-weight:600;font-size:14px;cursor:pointer;border:1px solid transparent;transition:all .15s ease;white-space:nowrap;user-select:none;text-decoration:none}
.btn:active{transform:translateY(1px)}
.btn-primary{background:var(--primary);color:#fff;box-shadow:0 4px 12px rgba(59,130,246,.25)}
.btn-primary:hover{background:var(--primary-dark);color:#fff;box-shadow:0 6px 18px rgba(59,130,246,.35)}
.btn-light{background:var(--bg-mute);color:var(--text)}
.btn-light:hover{background:var(--bg-hover)}
.btn-ghost{color:var(--text-soft)}
.btn-ghost:hover{background:var(--bg-mute);color:var(--text)}
.btn-danger{background:var(--danger);color:#fff}
.btn-block{display:flex;width:100%}
.btn-sm{min-height:36px;padding:8px 14px;font-size:13px}
.btn-lg{min-height:54px;padding:14px 26px;font-size:15px}
.alert{padding:12px 16px;border-radius:var(--r-md);font-size:14px;display:flex;align-items:flex-start;gap:10px;margin-bottom:14px}
.alert-success{background:var(--success-light);color:var(--success-dark);border:1px solid #A7F3D0}
.alert-danger{background:var(--danger-light);color:var(--danger-dark);border:1px solid #FCA5A5}
.alert-warning{background:var(--warning-light);color:var(--warning-dark);border:1px solid #FCD34D}
.alert-info{background:var(--primary-50);color:var(--primary-700);border:1px solid #BFDBFE}
.form-group{margin-bottom:14px}
.form-label{display:block;font-weight:500;margin-bottom:6px;font-size:13px;color:var(--text-soft)}
.form-control{width:100%;padding:12px 14px;min-height:48px;border:1.5px solid var(--border);border-radius:var(--r-md);background:#fff;font-size:16px;color:var(--text);transition:all .15s ease}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(59,130,246,.15)}
.form-control[disabled]{background:var(--bg-mute);cursor:not-allowed}
.badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:var(--r-full);font-size:11px;font-weight:700;line-height:1.4}
.badge-success{background:var(--success-light);color:var(--success-dark)}
.badge-warning{background:var(--warning-light);color:var(--warning-dark)}
.badge-danger{background:var(--danger-light);color:var(--danger-dark)}
.badge-info{background:var(--primary-light);color:var(--primary-700)}
.card{background:#fff;border:1px solid var(--border);border-radius:var(--r-xl);padding:20px;box-shadow:var(--shadow-sm)}
.text-soft{color:var(--text-soft)}
.text-mute{color:var(--text-mute)}
.text-center{text-align:center}
.flex{display:flex}.gap-2{gap:8px}.gap-3{gap:12px}
.items-center{align-items:center}.justify-between{justify-content:space-between}
.mb-2{margin-bottom:12px}.mb-3{margin-bottom:18px}
@media (hover:none){.btn:hover{transform:none}}
@media (max-width:640px){.container{padding:0 16px}.btn{font-size:14px}}
CSS;
}
