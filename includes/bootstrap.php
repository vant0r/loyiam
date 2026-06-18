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
    function flush_settings_cache(): void {
        @unlink(__DIR__ . '/../cache/data/settings.json');
    }
}

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
