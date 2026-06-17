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
<style>
/* ============================================================
   DESIGN SYSTEM v2 — VatanParvar Yaypan
   ============================================================ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  /* — BRAND COLORS — */
  --primary-50:#EFF6FF;
  --primary-100:#DBEAFE;
  --primary-200:#BFDBFE;
  --primary-300:#93C5FD;
  --primary-400:#60A5FA;
  --primary-500:<?= PRIMARY_COLOR ?>;
  --primary-600:#2563EB;
  --primary-700:#1D4ED8;
  --primary-800:#1E40AF;
  --primary-900:#1E3A8A;

  --primary:var(--primary-500);
  --primary-dark:var(--primary-600);
  --primary-light:var(--primary-100);
  --secondary:var(--primary-800);

  /* — NEUTRAL — */
  --bg:#ffffff;
  --bg-soft:#F8FAFC;
  --bg-mute:#F1F5F9;
  --bg-hover:#E2E8F0;
  --text:#0F172A;
  --text-soft:#475569;
  --text-mute:#94A3B8;
  --text-disabled:#CBD5E1;
  --border:#E2E8F0;
  --border-strong:#CBD5E1;

  /* — SEMANTIC — */
  --success:#10B981;
  --success-light:#D1FAE5;
  --success-dark:#065F46;
  --warning:#F59E0B;
  --warning-light:#FEF3C7;
  --warning-dark:#92400E;
  --danger:#EF4444;
  --danger-light:#FEE2E2;
  --danger-dark:#991B1B;
  --info:#3B82F6;
  --info-light:#DBEAFE;
  --info-dark:#1E40AF;

  /* — SPACING — */
  --sp-1:4px;  --sp-2:8px;  --sp-3:12px; --sp-4:16px;
  --sp-5:20px; --sp-6:24px; --sp-8:32px; --sp-10:40px;
  --sp-12:48px;--sp-16:64px;--sp-20:80px;

  /* — TYPOGRAPHY — */
  --font-sans:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
  --fs-xs:11px; --fs-sm:13px; --fs-base:15px; --fs-md:16px;
  --fs-lg:18px; --fs-xl:22px; --fs-2xl:28px; --fs-3xl:36px;
  --fs-4xl:48px; --fs-5xl:60px;
  --lh-tight:1.15; --lh-snug:1.3; --lh-base:1.6; --lh-relaxed:1.75;

  /* — BORDER RADIUS — */
  --r-xs:4px; --r-sm:6px; --r-md:8px; --r-lg:12px;
  --r-xl:16px; --r-2xl:24px; --r-full:9999px;
  --radius:var(--r-lg); --radius-lg:var(--r-xl);

  /* — SHADOWS — */
  --shadow-xs:0 1px 2px rgba(15,23,42,.04);
  --shadow-sm:0 2px 4px rgba(15,23,42,.06),0 1px 2px rgba(15,23,42,.04);
  --shadow-md:0 4px 12px rgba(15,23,42,.08),0 2px 4px rgba(15,23,42,.04);
  --shadow-lg:0 12px 32px rgba(15,23,42,.10),0 4px 8px rgba(15,23,42,.04);
  --shadow-xl:0 24px 48px rgba(15,23,42,.12);
  --shadow-primary:0 8px 24px rgba(59,130,246,.25);
  --shadow-primary-lg:0 16px 40px rgba(59,130,246,.30);
  --shadow:var(--shadow-md);

  /* — TRANSITIONS — */
  --ease-out:cubic-bezier(.22,1,.36,1);
  --ease-in-out:cubic-bezier(.65,0,.35,1);
  --spring:cubic-bezier(.34,1.56,.64,1);
  --t-fast:.15s var(--ease-out);
  --t-base:.25s var(--ease-out);
  --t-slow:.4s var(--ease-out);
  --transition:var(--t-base);

  /* — Z-INDEX — */
  --z-dropdown:100; --z-sticky:200; --z-modal-bg:900;
  --z-modal:1000;   --z-toast:1100; --z-tooltip:1200;

  --container:1200px;
}

html,body{height:100%}
body{
  font-family:var(--font-sans);
  font-size:var(--fs-base);
  line-height:var(--lh-base);
  color:var(--text);
  background:var(--bg);
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
  text-rendering:optimizeLegibility;
}
a{color:var(--primary);text-decoration:none;transition:color var(--t-fast)}
a:hover{color:var(--primary-dark)}
img,svg{max-width:100%;display:block}
button{font-family:inherit;cursor:pointer;border:none;background:none;color:inherit}
input,select,textarea{font-family:inherit;font-size:inherit}

/* ============== TYPOGRAPHY ============== */
h1,h2,h3,h4,h5,h6{font-weight:700;line-height:var(--lh-tight);letter-spacing:-.02em}
h1{font-size:var(--fs-4xl);font-weight:800}
h2{font-size:var(--fs-3xl);font-weight:800}
h3{font-size:var(--fs-2xl)}
h4{font-size:var(--fs-xl)}
h5{font-size:var(--fs-lg)}
h6{font-size:var(--fs-base)}
.eyebrow{font-size:var(--fs-xs);text-transform:uppercase;letter-spacing:.1em;font-weight:700;color:var(--primary)}
.text-soft{color:var(--text-soft)}
.text-mute{color:var(--text-mute)}
.text-primary{color:var(--primary)}
.text-success{color:var(--success-dark)}
.text-danger{color:var(--danger-dark)}
.text-warning{color:var(--warning-dark)}

/* ============== LAYOUT ============== */
.container{max-width:var(--container);margin:0 auto;padding:0 var(--sp-5)}
.row{display:flex;flex-wrap:wrap;gap:var(--sp-6)}

/* ============== ICONS ============== */
.icon{display:inline-block;flex-shrink:0;vertical-align:middle}
.icon-circle{width:48px;height:48px;border-radius:var(--r-lg);background:var(--primary-light);color:var(--primary);
  display:inline-flex;align-items:center;justify-content:center}
.icon-circle.icon-success{background:var(--success-light);color:var(--success-dark)}
.icon-circle.icon-warning{background:var(--warning-light);color:var(--warning-dark)}
.icon-circle.icon-danger{background:var(--danger-light);color:var(--danger-dark)}

/* ============== BUTTONS ============== */
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:var(--sp-2);
  padding:11px 20px;border-radius:var(--r-md);
  font-weight:600;font-size:var(--fs-sm);line-height:1;
  cursor:pointer;transition:all var(--t-fast);white-space:nowrap;
  border:1px solid transparent;user-select:none;position:relative;
}
.btn:focus-visible{outline:none;box-shadow:0 0 0 3px var(--primary-200)}
.btn:active{transform:translateY(1px)}
.btn-primary{background:var(--primary);color:#fff;box-shadow:var(--shadow-primary)}
.btn-primary:hover{background:var(--primary-dark);transform:translateY(-1px);box-shadow:var(--shadow-primary-lg);color:#fff}
.btn-secondary{background:var(--text);color:#fff}
.btn-secondary:hover{background:var(--text-soft);color:#fff;transform:translateY(-1px)}
.btn-outline{border:1px solid var(--primary);color:var(--primary);background:#fff}
.btn-outline:hover{background:var(--primary);color:#fff}
.btn-ghost{color:var(--text-soft)}
.btn-ghost:hover{background:var(--bg-mute);color:var(--text)}
.btn-light{background:var(--bg-mute);color:var(--text)}
.btn-light:hover{background:var(--bg-hover)}
.btn-danger{background:var(--danger);color:#fff}
.btn-danger:hover{background:var(--danger-dark);color:#fff}
.btn-success{background:var(--success);color:#fff}
.btn-success:hover{background:var(--success-dark);color:#fff}
.btn-xs{padding:5px 10px;font-size:var(--fs-xs)}
.btn-sm{padding:8px 14px;font-size:var(--fs-sm)}
.btn-lg{padding:14px 28px;font-size:var(--fs-md)}
.btn-xl{padding:18px 36px;font-size:var(--fs-lg);border-radius:var(--r-lg)}
.btn-icon{padding:9px;width:36px;height:36px}
.btn-icon.btn-sm{width:30px;height:30px;padding:6px}
.btn-icon.btn-lg{width:48px;height:48px;padding:12px}
.btn-block{display:flex;width:100%}
.btn[disabled],.btn-disabled{opacity:.5;cursor:not-allowed;pointer-events:none}
.btn-loading{color:transparent!important;pointer-events:none}
.btn-loading::after{
  content:'';position:absolute;top:50%;left:50%;width:18px;height:18px;
  margin:-9px 0 0 -9px;border:2px solid currentColor;border-top-color:transparent;
  border-radius:50%;animation:spin .7s linear infinite;color:#fff
}

/* ============== FORMS ============== */
.form-group{margin-bottom:var(--sp-5)}
.form-label{display:block;font-weight:500;margin-bottom:var(--sp-2);font-size:var(--fs-sm);color:var(--text-soft)}
.form-label .required{color:var(--danger)}
.form-control,.form-select,.form-textarea{
  width:100%;padding:11px 14px;
  border:1.5px solid var(--border);
  border-radius:var(--r-md);background:#fff;
  transition:all var(--t-fast);
  font-size:var(--fs-sm);color:var(--text);line-height:1.4;
}
.form-control:hover,.form-select:hover{border-color:var(--border-strong)}
.form-control:focus,.form-select:focus,.form-textarea:focus{
  outline:none;border-color:var(--primary);box-shadow:0 0 0 4px var(--primary-100);
}
.form-control.is-error,.form-select.is-error{border-color:var(--danger);background:#FFFBFB}
.form-control.is-error:focus{box-shadow:0 0 0 4px var(--danger-light)}
.form-control.is-success{border-color:var(--success)}
.form-control[disabled],.form-select[disabled]{background:var(--bg-mute);cursor:not-allowed;color:var(--text-mute)}
textarea.form-control,.form-textarea{resize:vertical;min-height:96px;line-height:var(--lh-base)}
.form-help{margin-top:var(--sp-2);font-size:var(--fs-xs);color:var(--text-mute)}
.form-error{margin-top:var(--sp-2);font-size:var(--fs-xs);color:var(--danger);display:flex;align-items:center;gap:4px}

/* Input with icon */
.input-group{position:relative}
.input-group .input-icon{position:absolute;top:50%;left:14px;transform:translateY(-50%);color:var(--text-mute);pointer-events:none}
.input-group .form-control{padding-left:42px}
.input-group .input-action{position:absolute;top:50%;right:8px;transform:translateY(-50%);
  background:transparent;border:none;width:32px;height:32px;border-radius:6px;color:var(--text-mute);cursor:pointer}
.input-group .input-action:hover{background:var(--bg-mute);color:var(--text)}

/* Checkbox / Radio */
.form-check{display:inline-flex;align-items:center;gap:var(--sp-2);cursor:pointer;user-select:none;font-size:var(--fs-sm)}
.form-check input[type="checkbox"],.form-check input[type="radio"]{
  width:18px;height:18px;cursor:pointer;accent-color:var(--primary)
}

/* ============== CARDS ============== */
.card{background:#fff;border:1px solid var(--border);border-radius:var(--r-xl);
  padding:var(--sp-6);transition:all var(--t-base);box-shadow:var(--shadow-xs)}
.card-hover:hover{box-shadow:var(--shadow-md);transform:translateY(-2px);border-color:var(--primary-200)}
.card-flat{border:none;box-shadow:none;background:var(--bg-soft)}
.card-primary{background:linear-gradient(135deg,var(--primary),var(--primary-700));color:#fff;border:none}
.card-primary h3,.card-primary h4{color:#fff}

/* ============== HEADER ============== */
.header{position:sticky;top:0;z-index:var(--z-sticky);background:rgba(255,255,255,.85);
  backdrop-filter:saturate(180%) blur(20px);-webkit-backdrop-filter:saturate(180%) blur(20px);
  border-bottom:1px solid var(--border)}
.nav{display:flex;align-items:center;justify-content:space-between;padding:var(--sp-3) 0;gap:var(--sp-4)}
.logo{display:flex;align-items:center;gap:var(--sp-3);font-weight:800;font-size:var(--fs-md);color:var(--text)}
.logo:hover{color:var(--text)}
.logo-icon{width:40px;height:40px;background:linear-gradient(135deg,var(--primary),var(--secondary));
  border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:var(--fs-sm)}
.nav-menu{display:flex;align-items:center;gap:var(--sp-6);list-style:none}
.nav-menu a{color:var(--text-soft);font-weight:500;font-size:var(--fs-sm);padding:6px 4px;position:relative;transition:color var(--t-fast)}
.nav-menu a:hover,.nav-menu a.active{color:var(--primary)}
.nav-menu a.active::after{content:'';position:absolute;bottom:-4px;left:0;right:0;height:2px;background:var(--primary);border-radius:2px}
.nav-actions{display:flex;align-items:center;gap:var(--sp-3)}
.lang-switch{display:inline-flex;background:var(--bg-mute);border-radius:var(--r-md);padding:3px;gap:2px}
.lang-switch a{padding:5px 11px;border-radius:var(--r-xs);font-size:var(--fs-xs);font-weight:700;color:var(--text-soft)}
.lang-switch a.active{background:#fff;color:var(--primary);box-shadow:var(--shadow-xs)}
.menu-toggle{display:none;width:40px;height:40px;align-items:center;justify-content:center;
  border-radius:var(--r-md);background:var(--bg-mute);color:var(--text)}

/* ============== HERO ============== */
.hero{padding:var(--sp-20) 0 var(--sp-16);background:linear-gradient(135deg,#F0F9FF 0%,#E0F2FE 50%,#DBEAFE 100%);
  position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;top:-50%;right:-10%;width:700px;height:700px;
  background:radial-gradient(circle,rgba(59,130,246,.18),transparent 70%);border-radius:50%;animation:floatY 8s ease-in-out infinite}
.hero::after{content:'';position:absolute;bottom:-30%;left:-10%;width:500px;height:500px;
  background:radial-gradient(circle,rgba(168,85,247,.12),transparent 70%);border-radius:50%;animation:floatY 10s ease-in-out infinite reverse}
.hero-grid{display:grid;grid-template-columns:1.2fr 1fr;gap:var(--sp-12);align-items:center;position:relative;z-index:1}
.hero h1{font-size:var(--fs-4xl);line-height:1.1;margin-bottom:var(--sp-4);
  background:linear-gradient(135deg,var(--text) 0%,var(--primary) 100%);-webkit-background-clip:text;
  -webkit-text-fill-color:transparent;background-clip:text}
.hero p.lead{font-size:var(--fs-lg);color:var(--text-soft);margin-bottom:var(--sp-6);max-width:560px;line-height:var(--lh-relaxed)}
.hero-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:var(--sp-4);margin-top:var(--sp-10)}
.stat-box{padding:var(--sp-5);background:rgba(255,255,255,.7);backdrop-filter:blur(12px);
  border-radius:var(--r-lg);text-align:center;box-shadow:var(--shadow-xs);border:1px solid rgba(255,255,255,.6)}
.stat-num{font-size:var(--fs-3xl);font-weight:800;color:var(--primary);line-height:1;
  background:linear-gradient(135deg,var(--primary),var(--secondary));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.stat-label{font-size:var(--fs-xs);color:var(--text-soft);margin-top:var(--sp-2);font-weight:500}
.hero-image{aspect-ratio:1;background:linear-gradient(135deg,var(--primary),var(--secondary));
  border-radius:32px;display:flex;align-items:center;justify-content:center;font-size:160px;color:#fff;
  box-shadow:var(--shadow-primary-lg);transform:rotate(-3deg);position:relative;
  animation:floatY 6s ease-in-out infinite}
.hero-image::before{content:'';position:absolute;inset:-20px;background:linear-gradient(135deg,var(--primary-300),transparent);
  border-radius:40px;z-index:-1;opacity:.5;filter:blur(40px)}

/* ============== SECTIONS ============== */
.section{padding:var(--sp-20) 0}
.section-sm{padding:var(--sp-12) 0}
.section-soft{background:var(--bg-soft)}
.section-dark{background:var(--text);color:#fff}
.section-title{font-size:var(--fs-3xl);font-weight:800;text-align:center;margin-bottom:var(--sp-3);color:var(--text)}
.section-subtitle{text-align:center;color:var(--text-soft);font-size:var(--fs-md);margin-bottom:var(--sp-12);
  max-width:640px;margin-left:auto;margin-right:auto;line-height:var(--lh-relaxed)}

/* ============== GRID ============== */
.grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:var(--sp-6)}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:var(--sp-6)}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:var(--sp-5)}
.grid-6{display:grid;grid-template-columns:repeat(6,1fr);gap:var(--sp-4)}

/* ============== SERVICE CARDS ============== */
.service-card{padding:var(--sp-8) var(--sp-6);text-align:center}
.service-card .icon-circle{width:64px;height:64px;font-size:30px;margin:0 auto var(--sp-4)}
.service-card h3{font-size:var(--fs-lg);font-weight:700;margin-bottom:var(--sp-2)}
.service-card p{color:var(--text-soft);font-size:var(--fs-sm)}

/* ============== STEPS ============== */
.step-card{position:relative;padding-top:var(--sp-8)}
.step-num{position:absolute;top:-18px;left:24px;width:44px;height:44px;
  background:linear-gradient(135deg,var(--primary),var(--secondary));
  color:#fff;border-radius:var(--r-full);display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:var(--fs-md);box-shadow:var(--shadow-primary)}

/* ============== PRICING ============== */
.pricing-card{padding:var(--sp-10) var(--sp-6);text-align:center;position:relative;display:flex;flex-direction:column}
.pricing-card.popular{border:2px solid var(--primary);transform:scale(1.05);background:#fff;
  box-shadow:var(--shadow-primary-lg)}
.pricing-badge{position:absolute;top:-14px;left:50%;transform:translateX(-50%);
  background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;
  padding:6px 16px;border-radius:var(--r-full);font-size:var(--fs-xs);font-weight:700;
  text-transform:uppercase;letter-spacing:.05em;box-shadow:var(--shadow-primary)}
.pricing-card h3{font-size:var(--fs-xl);font-weight:700;margin-bottom:var(--sp-2)}
.pricing-card .pricing-desc{color:var(--text-soft);font-size:var(--fs-sm);min-height:42px}
.pricing-price{font-size:var(--fs-4xl);font-weight:800;color:var(--primary);margin:var(--sp-5) 0;line-height:1}
.pricing-price small{font-size:var(--fs-sm);color:var(--text-soft);font-weight:500}
.pricing-features{list-style:none;text-align:left;margin:var(--sp-5) 0;flex:1}
.pricing-features li{padding:var(--sp-2) 0;color:var(--text-soft);display:flex;align-items:flex-start;gap:var(--sp-2);font-size:var(--fs-sm)}
.pricing-features li::before{content:'';flex-shrink:0;width:18px;height:18px;border-radius:50%;
  background:var(--success-light) url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23065F46' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'><polyline points='20 6 9 17 4 12'/></svg>") center/12px no-repeat;
  margin-top:2px}

/* ============== REVIEWS ============== */
.review-card{padding:var(--sp-6);background:#fff;border:1px solid var(--border);border-radius:var(--r-xl);transition:all var(--t-base)}
.review-card:hover{box-shadow:var(--shadow-md);transform:translateY(-2px)}
.review-stars{display:flex;gap:2px;color:#FBBF24;margin-bottom:var(--sp-3)}
.review-text{color:var(--text);margin-bottom:var(--sp-4);line-height:var(--lh-relaxed);font-size:var(--fs-sm)}
.review-text::before{content:'"';font-size:var(--fs-3xl);color:var(--primary-200);font-family:Georgia;
  line-height:0;vertical-align:-15px}
.review-author{display:flex;align-items:center;gap:var(--sp-3)}
.review-avatar{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));
  color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:var(--fs-md)}

/* ============== FAQ ============== */
.faq-item{background:#fff;border:1px solid var(--border);border-radius:var(--r-lg);margin-bottom:var(--sp-3);overflow:hidden;transition:all var(--t-base)}
.faq-item:hover{border-color:var(--primary-200)}
.faq-q{padding:var(--sp-5) var(--sp-5);cursor:pointer;display:flex;justify-content:space-between;
  align-items:center;font-weight:600;gap:var(--sp-3);color:var(--text)}
.faq-q:hover{background:var(--bg-soft)}
.faq-a{padding:0 var(--sp-5);max-height:0;overflow:hidden;transition:max-height var(--t-slow),padding var(--t-base);color:var(--text-soft);line-height:var(--lh-relaxed)}
.faq-item.open{border-color:var(--primary);box-shadow:var(--shadow-sm)}
.faq-item.open .faq-a{padding:0 var(--sp-5) var(--sp-5);max-height:400px}
.faq-item.open .faq-q .icon{transform:rotate(180deg);color:var(--primary)}
.faq-q .icon{transition:transform var(--t-base);color:var(--text-mute)}

/* ============== FOOTER ============== */
.footer{background:linear-gradient(180deg,#0F172A 0%,#1E293B 100%);color:#CBD5E1;padding:var(--sp-16) 0 var(--sp-8);margin-top:var(--sp-20)}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1.4fr;gap:var(--sp-10);margin-bottom:var(--sp-10)}
.footer h4{color:#fff;font-size:var(--fs-md);margin-bottom:var(--sp-5);font-weight:700}
.footer ul{list-style:none}
.footer ul li{margin-bottom:var(--sp-3)}
.footer a{color:#CBD5E1;font-size:var(--fs-sm);transition:color var(--t-fast)}
.footer a:hover{color:#fff}
.footer-social{display:flex;gap:var(--sp-2);margin-top:var(--sp-4)}
.footer-social a{width:40px;height:40px;background:rgba(255,255,255,.06);border-radius:var(--r-md);
  display:flex;align-items:center;justify-content:center;color:#CBD5E1;transition:all var(--t-fast)}
.footer-social a:hover{background:var(--primary);color:#fff;transform:translateY(-2px)}
.footer-bottom{border-top:1px solid rgba(255,255,255,.08);padding-top:var(--sp-6);text-align:center;font-size:var(--fs-xs);color:#64748B}

/* ============== TABLES ============== */
.table-wrap{background:#fff;border:1px solid var(--border);border-radius:var(--r-xl);overflow:hidden;box-shadow:var(--shadow-xs)}
.table-wrap.table-flat{border:none;box-shadow:none;border-radius:0}
table{width:100%;border-collapse:collapse}
table th,table td{padding:14px 18px;text-align:left;border-bottom:1px solid var(--border);font-size:var(--fs-sm)}
table th{background:var(--bg-soft);font-weight:600;font-size:var(--fs-xs);color:var(--text-soft);
  text-transform:uppercase;letter-spacing:.05em;white-space:nowrap}
table tbody tr:last-child td{border-bottom:none}
table tbody tr:hover{background:var(--bg-soft)}
table tbody tr.is-highlighted{background:var(--primary-100)}

/* ============== BADGES ============== */
.badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:var(--r-full);
  font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.04em;line-height:1}
.badge-success{background:var(--success-light);color:var(--success-dark)}
.badge-warning{background:var(--warning-light);color:var(--warning-dark)}
.badge-danger{background:var(--danger-light);color:var(--danger-dark)}
.badge-info{background:var(--primary-100);color:var(--primary-800)}
.badge-mute{background:var(--bg-mute);color:var(--text-soft)}
.badge-dot::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor}

/* ============== AUTH ============== */
.auth-page{min-height:100vh;display:flex;align-items:center;justify-content:center;
  padding:var(--sp-10) var(--sp-5);background:linear-gradient(135deg,#F0F9FF,#DBEAFE)}
.auth-box{background:#fff;border-radius:var(--r-2xl);padding:var(--sp-10);width:100%;max-width:480px;
  box-shadow:var(--shadow-xl);position:relative;animation:fadeUp .6s var(--ease-out)}
.auth-box h2{text-align:center;font-size:var(--fs-2xl);margin-bottom:var(--sp-2)}
.auth-box .subtitle{text-align:center;color:var(--text-soft);margin-bottom:var(--sp-8);font-size:var(--fs-sm)}
.auth-divider{text-align:center;margin:var(--sp-5) 0;color:var(--text-mute);font-size:var(--fs-xs);
  position:relative;text-transform:uppercase;letter-spacing:.1em}
.auth-divider::before,.auth-divider::after{content:'';position:absolute;top:50%;width:42%;height:1px;background:var(--border)}
.auth-divider::before{left:0}.auth-divider::after{right:0}

/* ============== ALERTS ============== */
.alert{padding:14px 18px;border-radius:var(--r-md);margin-bottom:var(--sp-4);font-size:var(--fs-sm);
  display:flex;align-items:flex-start;gap:var(--sp-3);animation:slideDown .3s var(--ease-out)}
.alert-success{background:var(--success-light);color:var(--success-dark);border:1px solid #A7F3D0}
.alert-danger{background:var(--danger-light);color:var(--danger-dark);border:1px solid #FCA5A5}
.alert-warning{background:var(--warning-light);color:var(--warning-dark);border:1px solid #FCD34D}
.alert-info{background:var(--primary-100);color:var(--primary-800);border:1px solid #BFDBFE}
.alert .icon{flex-shrink:0;margin-top:2px}

/* ============== TOASTS ============== */
.toast-container{position:fixed;top:20px;right:20px;z-index:var(--z-toast);display:flex;flex-direction:column;gap:10px;max-width:380px}
.toast{background:#fff;border:1px solid var(--border);border-radius:var(--r-lg);padding:14px 16px;
  box-shadow:var(--shadow-lg);display:flex;align-items:flex-start;gap:12px;
  animation:slideInR .3s var(--ease-out);position:relative;border-left:4px solid var(--primary)}
.toast.toast-success{border-left-color:var(--success)}
.toast.toast-danger{border-left-color:var(--danger)}
.toast.toast-warning{border-left-color:var(--warning)}
.toast .toast-icon{flex-shrink:0;color:var(--primary)}
.toast.toast-success .toast-icon{color:var(--success)}
.toast.toast-danger .toast-icon{color:var(--danger)}
.toast.toast-warning .toast-icon{color:var(--warning)}
.toast .toast-body{flex:1;font-size:var(--fs-sm)}
.toast .toast-close{background:none;border:none;color:var(--text-mute);cursor:pointer;padding:0}

/* ============== MODAL ============== */
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.65);backdrop-filter:blur(4px);
  z-index:var(--z-modal-bg);align-items:center;justify-content:center;padding:var(--sp-5);
  animation:fadeIn .2s var(--ease-out)}
.modal-backdrop.show{display:flex}
.modal{background:#fff;border-radius:var(--r-2xl);max-width:520px;width:100%;max-height:90vh;
  overflow-y:auto;box-shadow:var(--shadow-xl);animation:scaleIn .25s var(--spring);position:relative}
.modal-lg{max-width:720px}
.modal-xl{max-width:920px}
.modal-header{padding:var(--sp-6) var(--sp-6) var(--sp-4);border-bottom:1px solid var(--border);
  display:flex;justify-content:space-between;align-items:center;gap:var(--sp-4)}
.modal-title{font-size:var(--fs-lg);font-weight:700;margin:0}
.modal-close{background:var(--bg-mute);border-radius:var(--r-md);width:32px;height:32px;display:flex;
  align-items:center;justify-content:center;color:var(--text-soft);transition:all var(--t-fast)}
.modal-close:hover{background:var(--danger-light);color:var(--danger)}
.modal-body{padding:var(--sp-6)}
.modal-footer{padding:var(--sp-4) var(--sp-6);border-top:1px solid var(--border);
  display:flex;justify-content:flex-end;gap:var(--sp-3)}

/* ============== SIDEBAR LAYOUT (panels) ============== */
.layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
.sidebar{background:linear-gradient(180deg,#0F172A,#1E293B);color:#CBD5E1;
  position:sticky;top:0;height:100vh;overflow-y:auto;padding:var(--sp-5) 0;
  display:flex;flex-direction:column}
.sidebar-logo{padding:0 var(--sp-5) var(--sp-5);border-bottom:1px solid rgba(255,255,255,.08);
  margin-bottom:var(--sp-3);display:flex;align-items:center;gap:var(--sp-3);color:#fff;font-weight:700}
.sidebar-menu{list-style:none;padding:var(--sp-2) var(--sp-3);flex:1}
.sidebar-menu li{margin-bottom:2px}
.sidebar-menu a{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:var(--r-md);
  color:#94A3B8;font-size:var(--fs-sm);font-weight:500;transition:all var(--t-fast)}
.sidebar-menu a:hover{background:rgba(255,255,255,.05);color:#fff}
.sidebar-menu a.active{background:var(--primary);color:#fff;box-shadow:var(--shadow-primary)}
.sidebar-menu a .icon{flex-shrink:0;opacity:.9}
.sidebar-bottom{padding:var(--sp-3) var(--sp-3);border-top:1px solid rgba(255,255,255,.08)}
.sidebar-user{padding:var(--sp-3);background:rgba(255,255,255,.04);border-radius:var(--r-md);
  display:flex;align-items:center;gap:var(--sp-3);margin-bottom:var(--sp-2)}
.sidebar-user-info{flex:1;overflow:hidden}
.sidebar-user-name{color:#fff;font-size:var(--fs-sm);font-weight:600;text-overflow:ellipsis;overflow:hidden;white-space:nowrap}
.sidebar-user-role{color:#94A3B8;font-size:var(--fs-xs)}
.main{padding:var(--sp-6) var(--sp-8);background:var(--bg-soft);overflow-x:auto;min-width:0}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--sp-6);
  flex-wrap:wrap;gap:var(--sp-3)}
.page-title{font-size:var(--fs-2xl);font-weight:700;margin:0;display:flex;align-items:center;gap:var(--sp-3)}
.page-subtitle{color:var(--text-soft);font-size:var(--fs-sm);margin-top:4px}

/* ============== STAT CARDS ============== */
.stat-card{padding:var(--sp-5);border-radius:var(--r-xl);background:#fff;border:1px solid var(--border);
  transition:all var(--t-base);position:relative;overflow:hidden}
.stat-card:hover{border-color:var(--primary-200);box-shadow:var(--shadow-sm);transform:translateY(-1px)}
.stat-card .stat-icon{width:44px;height:44px;border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;
  background:var(--primary-light);color:var(--primary);margin-bottom:var(--sp-3)}
.stat-card .stat-icon.success{background:var(--success-light);color:var(--success-dark)}
.stat-card .stat-icon.warning{background:var(--warning-light);color:var(--warning-dark)}
.stat-card .stat-icon.danger{background:var(--danger-light);color:var(--danger-dark)}
.stat-card .stat-icon.purple{background:#FCE7F3;color:#9F1239}
.stat-card .value{font-size:var(--fs-2xl);font-weight:800;line-height:1.1}
.stat-card .label{color:var(--text-soft);font-size:var(--fs-xs);margin-top:4px;font-weight:500}
.stat-card .trend{font-size:var(--fs-xs);margin-top:6px;display:flex;align-items:center;gap:4px;font-weight:600}
.stat-card .trend.up{color:var(--success-dark)}
.stat-card .trend.down{color:var(--danger-dark)}

/* ============== PAGINATION ============== */
.pagination{display:flex;gap:6px;justify-content:center;margin-top:var(--sp-8);flex-wrap:wrap}
.pagination a,.pagination span{min-width:40px;height:40px;display:inline-flex;align-items:center;
  justify-content:center;border-radius:var(--r-md);background:#fff;border:1px solid var(--border);
  color:var(--text);font-weight:600;font-size:var(--fs-sm);padding:0 12px;transition:all var(--t-fast)}
.pagination a:hover{border-color:var(--primary);color:var(--primary)}
.pagination .active{background:var(--primary);color:#fff;border-color:var(--primary)}

/* ============== PROGRESS BAR ============== */
.progress{width:100%;height:8px;background:var(--bg-mute);border-radius:var(--r-full);overflow:hidden}
.progress-bar{height:100%;background:linear-gradient(90deg,var(--primary),var(--primary-700));
  border-radius:var(--r-full);transition:width var(--t-slow)}
.progress-success .progress-bar{background:linear-gradient(90deg,var(--success),#059669)}
.progress-warning .progress-bar{background:linear-gradient(90deg,var(--warning),#D97706)}
.progress-danger .progress-bar{background:linear-gradient(90deg,var(--danger),#DC2626)}

/* ============== TABS ============== */
.tabs{display:flex;gap:4px;background:var(--bg-mute);padding:4px;border-radius:var(--r-md);overflow-x:auto;flex-wrap:nowrap}
.tabs a,.tabs button{padding:8px 16px;border-radius:var(--r-sm);font-size:var(--fs-sm);font-weight:600;
  color:var(--text-soft);white-space:nowrap;transition:all var(--t-fast);cursor:pointer;background:none;border:none}
.tabs a:hover,.tabs button:hover{color:var(--text)}
.tabs a.active,.tabs button.active{background:#fff;color:var(--primary);box-shadow:var(--shadow-xs)}

/* ============== SKELETON ============== */
.skeleton{background:linear-gradient(90deg,var(--bg-mute) 25%,var(--bg-hover) 50%,var(--bg-mute) 75%);
  background-size:200% 100%;animation:skeletonShimmer 1.5s infinite;border-radius:var(--r-sm)}
.skeleton-text{height:14px;margin-bottom:8px}
.skeleton-text:last-child{width:60%}
.skeleton-circle{border-radius:50%}

/* ============== EMPTY STATES ============== */
.empty-state{padding:var(--sp-12) var(--sp-5);text-align:center;color:var(--text-soft)}
.empty-state .icon{width:80px;height:80px;margin:0 auto var(--sp-4);color:var(--text-mute);opacity:.5}
.empty-state h3{color:var(--text);margin-bottom:var(--sp-2);font-size:var(--fs-lg)}
.empty-state p{margin-bottom:var(--sp-5);font-size:var(--fs-sm)}

/* ============== ANIMATIONS ============== */
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes fadeOut{from{opacity:1}to{opacity:0}}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeDown{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px);max-height:0}to{opacity:1;transform:translateY(0);max-height:200px}}
@keyframes slideInR{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}
@keyframes scaleIn{from{opacity:0;transform:scale(.92)}to{opacity:1;transform:scale(1)}}
@keyframes modalSlideOut{from{opacity:1;transform:translateY(0) scale(1)}to{opacity:0;transform:translateY(20px) scale(.96)}}
@keyframes floatY{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
@keyframes skeletonShimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
@keyframes confetti{0%{transform:translateY(0) rotate(0);opacity:1}100%{transform:translateY(100vh) rotate(720deg);opacity:0}}
@keyframes ripple{from{transform:scale(0);opacity:.6}to{transform:scale(2.5);opacity:0}}
.fade-up{animation:fadeUp .6s var(--ease-out) both}
.fade-in{animation:fadeIn .4s var(--ease-out) both}
.pulse{animation:pulse 2s ease-in-out infinite}
.scale-in{animation:scaleIn .3s var(--spring) both}

/* Stagger animation */
.stagger > *{animation:fadeUp .5s var(--ease-out) both}
.stagger > *:nth-child(1){animation-delay:0s}
.stagger > *:nth-child(2){animation-delay:.06s}
.stagger > *:nth-child(3){animation-delay:.12s}
.stagger > *:nth-child(4){animation-delay:.18s}
.stagger > *:nth-child(5){animation-delay:.24s}
.stagger > *:nth-child(6){animation-delay:.3s}

/* ============== UTILITIES ============== */
.text-center{text-align:center}.text-right{text-align:right}.text-left{text-align:left}
.font-bold{font-weight:700}.font-semibold{font-weight:600}.font-medium{font-weight:500}
.mt-1{margin-top:var(--sp-2)}.mt-2{margin-top:var(--sp-4)}.mt-3{margin-top:var(--sp-6)}.mt-4{margin-top:var(--sp-8)}.mt-5{margin-top:var(--sp-10)}
.mb-1{margin-bottom:var(--sp-2)}.mb-2{margin-bottom:var(--sp-4)}.mb-3{margin-bottom:var(--sp-6)}.mb-4{margin-bottom:var(--sp-8)}.mb-5{margin-bottom:var(--sp-10)}
.flex{display:flex}.inline-flex{display:inline-flex}.grid{display:grid}.block{display:block}.hidden{display:none}
.gap-1{gap:var(--sp-1)}.gap-2{gap:var(--sp-2)}.gap-3{gap:var(--sp-3)}.gap-4{gap:var(--sp-4)}.gap-5{gap:var(--sp-5)}.gap-6{gap:var(--sp-6)}
.justify-between{justify-content:space-between}.justify-center{justify-content:center}.justify-end{justify-content:flex-end}
.items-center{align-items:center}.items-start{align-items:flex-start}.items-end{align-items:flex-end}
.flex-wrap{flex-wrap:wrap}.flex-1{flex:1}.flex-shrink-0{flex-shrink:0}
.w-full{width:100%}.h-full{height:100%}
.rounded{border-radius:var(--r-md)}.rounded-lg{border-radius:var(--r-lg)}.rounded-full{border-radius:var(--r-full)}
.shadow-sm{box-shadow:var(--shadow-sm)}.shadow{box-shadow:var(--shadow)}.shadow-lg{box-shadow:var(--shadow-lg)}
.cursor-pointer{cursor:pointer}.relative{position:relative}.absolute{position:absolute}
.overflow-hidden{overflow:hidden}.overflow-auto{overflow:auto}

/* ============== RESPONSIVE ============== */
@media (max-width: 1024px){
  :root{--container:100%}
  .hero h1{font-size:var(--fs-3xl)}
  .hero-grid{grid-template-columns:1fr;gap:var(--sp-6)}
  .hero-image{max-width:380px;margin:0 auto;font-size:120px}
  .grid-4,.grid-3{grid-template-columns:repeat(2,1fr)}
  .footer-grid{grid-template-columns:1fr 1fr;gap:var(--sp-8)}
  .layout{grid-template-columns:1fr}
  .sidebar{position:fixed;left:-280px;width:260px;top:0;transition:left .3s;z-index:var(--z-modal);height:100vh}
  .sidebar.open{left:0}
  .pricing-card.popular{transform:none}
  .main{padding:var(--sp-5)}
}
@media (max-width: 640px){
  :root{--fs-3xl:28px;--fs-4xl:36px}
  .nav-menu{position:fixed;top:64px;left:0;right:0;background:#fff;flex-direction:column;
    padding:var(--sp-5);border-bottom:1px solid var(--border);transform:translateY(-200%);
    transition:transform .3s;gap:var(--sp-4);box-shadow:var(--shadow-lg)}
  .nav-menu.open{transform:translateY(0)}
  .menu-toggle{display:inline-flex}
  .grid-4,.grid-3,.grid-2{grid-template-columns:1fr}
  .footer-grid{grid-template-columns:1fr}
  .hero{padding:var(--sp-12) 0 var(--sp-10)}
  .hero h1{font-size:30px}
  .section{padding:var(--sp-12) 0}
  .section-title{font-size:var(--fs-2xl)}
  .auth-box{padding:var(--sp-6) var(--sp-5)}
  .main{padding:var(--sp-4)}
  table th,table td{padding:10px 12px}
  .modal-header,.modal-body,.modal-footer{padding:var(--sp-4)}
  .toast-container{top:10px;right:10px;left:10px;max-width:none}
  .stat-card .value{font-size:var(--fs-xl)}
}

/* ============== PRINT ============== */
@media print {
  .header,.footer,.sidebar,.modal-backdrop,.btn{display:none!important}
  .layout{display:block}
  .main{padding:0;background:#fff}
}

/* ============== ACCESSIBILITY ============== */
@media (prefers-reduced-motion: reduce) {
  *,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important;animation-iteration-count:1!important}
}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
:focus-visible{outline:2px solid var(--primary);outline-offset:3px;border-radius:4px}

/* ============== ENHANCED ANIMATIONS (v2.1) ============== */

/* Smooth page transitions */
@keyframes pageEnter{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
main, .container > section:first-child{animation:pageEnter .5s var(--ease-out) both}

/* Ripple effect */
@keyframes ripple{from{transform:scale(0);opacity:.6}to{transform:scale(2.5);opacity:0}}
.btn{position:relative;overflow:hidden;isolation:isolate}
.btn .ripple{position:absolute;border-radius:50%;background:currentColor;opacity:.3;
  animation:ripple .6s ease-out;pointer-events:none;z-index:0}

/* Floating glow */
@keyframes glowPulse{
  0%,100%{box-shadow:0 0 0 0 rgba(59,130,246,.4)}
  50%{box-shadow:0 0 24px 8px rgba(59,130,246,.15)}
}
.btn-primary.btn-glow{animation:glowPulse 2.5s ease-in-out infinite}

/* Smooth slide reveals */
@keyframes slideRevealLeft{from{opacity:0;transform:translateX(-30px);clip-path:inset(0 100% 0 0)}to{opacity:1;transform:translateX(0);clip-path:inset(0 0 0 0)}}
@keyframes slideRevealRight{from{opacity:0;transform:translateX(30px);clip-path:inset(0 0 0 100%)}to{opacity:1;transform:translateX(0);clip-path:inset(0 0 0 0)}}
@keyframes slideRevealUp{from{opacity:0;transform:translateY(30px);clip-path:inset(100% 0 0 0)}to{opacity:1;transform:translateY(0);clip-path:inset(0 0 0 0)}}
.reveal-left{animation:slideRevealLeft .8s var(--ease-out) both}
.reveal-right{animation:slideRevealRight .8s var(--ease-out) both}
.reveal-up{animation:slideRevealUp .8s var(--ease-out) both}

/* Hover lift with shadow */
.lift{transition:transform .3s var(--spring), box-shadow .3s var(--ease-out)}
.lift:hover{transform:translateY(-6px);box-shadow:var(--shadow-lg)}

/* Scale-up hover for cards */
.zoom-hover{transition:transform .4s var(--spring)}
.zoom-hover:hover{transform:scale(1.02)}
.zoom-hover img{transition:transform .6s var(--ease-out)}
.zoom-hover:hover img{transform:scale(1.08)}

/* Shake on error */
@keyframes shake{0%,100%{transform:translateX(0)}10%,30%,50%,70%,90%{transform:translateX(-6px)}20%,40%,60%,80%{transform:translateX(6px)}}
.shake, .alert-danger{animation:shake .5s var(--ease-out)}

/* Bounce */
@keyframes bounce{0%,100%{transform:translateY(0)}40%{transform:translateY(-12px)}60%{transform:translateY(-6px)}}
.bounce{animation:bounce 1s infinite}

/* Gradient text */
.gradient-text{background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 50%,#8B5CF6 100%);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
  background-size:200% auto;animation:gradientShift 4s linear infinite}
@keyframes gradientShift{0%{background-position:0% 50%}100%{background-position:200% 50%}}

/* Number counter animation pulse */
.stat-num,.value{transition:transform .2s var(--spring)}
.stat-num.counting{transform:scale(1.05)}

/* Reveal on scroll (used with IntersectionObserver) */
.reveal-on-scroll{opacity:0;transform:translateY(30px);transition:opacity .8s var(--ease-out),transform .8s var(--ease-out)}
.reveal-on-scroll.visible{opacity:1;transform:translateY(0)}

/* Parallax-style hover for hero */
.hero-image{will-change:transform}

/* Smooth icon transitions */
.icon{transition:transform .3s var(--spring)}
.btn:hover .icon{transform:translateX(2px)}
.btn-outline:hover .icon, .btn-light:hover .icon{transform:translateX(0) rotate(0)}

/* Pulse dot for live indicators */
.live-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--success);
  position:relative;margin-right:6px;vertical-align:middle}
.live-dot::after{content:'';position:absolute;inset:-2px;border-radius:50%;background:var(--success);
  animation:livePulse 1.5s ease-out infinite;opacity:0}
@keyframes livePulse{0%{transform:scale(.8);opacity:.6}100%{transform:scale(2.4);opacity:0}}

/* Smoother modal */
.modal-backdrop{transition:opacity .25s ease}
.modal{transform-origin:center}

/* Floating action button (FAB) */
.fab{position:fixed;right:20px;bottom:20px;width:56px;height:56px;border-radius:50%;
  background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;
  display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow-primary-lg);
  border:none;cursor:pointer;z-index:80;transition:all .3s var(--spring)}
.fab:hover{transform:scale(1.1) rotate(15deg);box-shadow:0 16px 40px rgba(59,130,246,.5)}
.fab:active{transform:scale(.95)}

/* Toast improvements */
.toast{transition:transform .3s var(--spring), opacity .3s ease}

/* Form input float label (alternative style) */
.form-float{position:relative}
.form-float .form-control{padding-top:22px;padding-bottom:8px}
.form-float .form-label{position:absolute;left:14px;top:14px;margin:0;pointer-events:none;
  transition:all .2s var(--ease-out);font-size:14px;color:var(--text-mute)}
.form-float .form-control:focus + .form-label,
.form-float .form-control:not(:placeholder-shown) + .form-label{
  top:6px;font-size:11px;color:var(--primary);font-weight:600
}

/* Card with sheen effect */
.card-sheen{position:relative;overflow:hidden;isolation:isolate}
.card-sheen::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.6),transparent);
  transition:left .8s var(--ease-out);z-index:1}
.card-sheen:hover::before{left:100%}

/* Improved skeleton */
@keyframes skeletonShimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}

/* Smooth dropdown */
.dropdown{position:relative}
.dropdown-menu{position:absolute;top:100%;right:0;background:#fff;border:1px solid var(--border);
  border-radius:var(--r-md);box-shadow:var(--shadow-lg);min-width:200px;padding:6px;
  opacity:0;visibility:hidden;transform:translateY(-8px) scale(.96);
  transition:all .2s var(--ease-out);z-index:var(--z-dropdown);transform-origin:top right}
.dropdown.open .dropdown-menu{opacity:1;visibility:visible;transform:translateY(4px) scale(1)}
.dropdown-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--r-sm);
  font-size:var(--fs-sm);color:var(--text);cursor:pointer;transition:background .15s}
.dropdown-item:hover{background:var(--bg-soft);color:var(--primary)}

/* ============== MOBILE-FIRST IMPROVEMENTS (v2.1) ============== */

/* Better touch targets on mobile */
@media (max-width: 720px) {
  .btn{min-height:44px}
  .btn-sm{min-height:36px}
  .btn-icon{min-width:44px;min-height:44px}
  .form-control,.form-select{min-height:44px;font-size:16px} /* iOS zoom oldini olish */
  .nav-menu a{min-height:44px;display:flex;align-items:center}
  table th,table td{padding:12px 10px}
  .lang-switch a{min-height:32px;display:flex;align-items:center}
}

/* Mobile bottom navigation */
@media (max-width: 720px) {
  .bottom-nav{position:fixed;bottom:0;left:0;right:0;background:rgba(255,255,255,.95);
    backdrop-filter:saturate(180%) blur(20px);-webkit-backdrop-filter:saturate(180%) blur(20px);
    border-top:1px solid var(--border);display:flex;justify-content:space-around;padding:8px 4px;
    z-index:var(--z-sticky);padding-bottom:max(8px, env(safe-area-inset-bottom))}
  .bottom-nav a{display:flex;flex-direction:column;align-items:center;gap:4px;padding:8px 12px;
    min-width:60px;color:var(--text-mute);font-size:11px;font-weight:500;
    border-radius:var(--r-md);transition:all .2s}
  .bottom-nav a.active{color:var(--primary);background:var(--primary-light)}
  .bottom-nav a .icon{margin-bottom:2px}
  body.has-bottom-nav{padding-bottom:80px}
  .bottom-nav-show{display:flex !important}
}
.bottom-nav{display:none}

/* Mobile-friendly tables (stacked cards) */
@media (max-width: 640px) {
  .table-responsive{display:block}
  .table-responsive thead{display:none}
  .table-responsive tbody, .table-responsive tr{display:block}
  .table-responsive td{display:flex;justify-content:space-between;align-items:center;
    padding:10px 14px;border-bottom:1px solid var(--border);text-align:right}
  .table-responsive td::before{content:attr(data-label);font-weight:600;color:var(--text-soft);
    font-size:12px;text-transform:uppercase;text-align:left;margin-right:8px}
  .table-responsive tr{margin-bottom:14px;background:#fff;border:1px solid var(--border);
    border-radius:var(--r-md);overflow:hidden}
  .table-responsive td:last-child{border-bottom:none}
}

/* Sidebar improvements on mobile */
@media (max-width: 992px) {
  .sidebar-overlay{position:fixed;inset:0;background:rgba(15,23,42,.5);backdrop-filter:blur(4px);
    z-index:calc(var(--z-modal) - 1);opacity:0;visibility:hidden;transition:all .3s}
  .sidebar.open + .sidebar-overlay{opacity:1;visibility:visible}
  .sidebar{box-shadow:8px 0 32px rgba(15,23,42,.2)}
}

/* Mobile-optimized hero */
@media (max-width: 480px) {
  .hero{padding:48px 0 32px}
  .hero h1{font-size:28px;line-height:1.2}
  .hero p.lead{font-size:15px;margin-bottom:20px}
  .hero-stats{grid-template-columns:1fr 1fr 1fr;gap:8px}
  .stat-box{padding:12px 8px}
  .stat-num{font-size:22px}
  .stat-label{font-size:10px}
  .hero-image{max-width:240px;font-size:100px}
}

/* Mobile sections */
@media (max-width: 480px) {
  .section{padding:40px 0}
  .section-title{font-size:24px;margin-bottom:8px}
  .section-subtitle{font-size:14px;margin-bottom:32px}
  .pricing-card{padding:28px 20px}
  .pricing-price{font-size:36px}
  .container{padding:0 16px}
}

/* Improved scrollbar on desktop */
@media (min-width: 1024px) {
  ::-webkit-scrollbar{width:10px;height:10px}
  ::-webkit-scrollbar-track{background:var(--bg-soft)}
  ::-webkit-scrollbar-thumb{background:var(--border-strong);border-radius:5px}
  ::-webkit-scrollbar-thumb:hover{background:var(--text-mute)}
}

/* Tablet sweet-spot */
@media (min-width: 641px) and (max-width: 1024px) {
  .grid-3{grid-template-columns:repeat(2,1fr)}
  .grid-4{grid-template-columns:repeat(2,1fr)}
  .footer-grid{grid-template-columns:1fr 1fr;gap:32px}
}

/* Landscape orientation on phones */
@media (max-height: 480px) and (orientation: landscape) {
  .hero{padding:32px 0}
  .hero-grid{grid-template-columns:1fr 1fr}
  .auth-page{align-items:flex-start;padding-top:24px}
}

/* Print styles */
@media print {
  .header,.footer,.sidebar,.modal-backdrop,.btn,.nav-actions,.menu-toggle,.bottom-nav,.fab{display:none!important}
  .layout{display:block}
  .main{padding:0;background:#fff}
  body{background:#fff;color:#000}
  a{color:inherit;text-decoration:underline}
  .card{box-shadow:none;border:1px solid #000;break-inside:avoid}
}

/* Dark mode preparation (theme switch keyin yoqiladi) */
@media (prefers-color-scheme: dark) {
  /* Saqlangan, lekin majburiy emas */
}

/* High DPI screens */
@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
  /* Bular avtomatik retina-ready */
}

/* Container queries support (modern brauzerlar uchun) */
@container (max-width: 480px) {
  .grid-3{grid-template-columns:1fr}
}

/* Smooth scrolling globally */
html{scroll-behavior:smooth;scroll-padding-top:80px}
@media (prefers-reduced-motion: reduce){
  html{scroll-behavior:auto}
}

/* Selection color */
::selection{background:var(--primary-200);color:var(--primary-900)}
::-moz-selection{background:var(--primary-200);color:var(--primary-900)}

/* ============== NOTIFICATIONS ============== */
.notif-wrap{position:relative;display:inline-block}
.notif-bell{position:relative}
.notif-badge{position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;padding:0 5px;
  background:var(--danger);color:#fff;border-radius:9px;font-size:10px;font-weight:700;
  display:flex;align-items:center;justify-content:center;border:2px solid #fff;
  animation:notifBadgePop .4s var(--ease-back) both}
@keyframes notifBadgePop{0%{transform:scale(0)}60%{transform:scale(1.2)}100%{transform:scale(1)}}
.notif-dropdown{position:absolute;top:calc(100% + 8px);right:0;width:380px;max-width:calc(100vw - 32px);
  background:#fff;border:1px solid var(--border);border-radius:var(--r-lg);
  box-shadow:0 12px 40px rgba(15,23,42,.15);
  opacity:0;visibility:hidden;transform:translateY(-8px) scale(.96);
  transition:all .25s var(--ease-soft);z-index:var(--z-dropdown);overflow:hidden}
.notif-wrap.open .notif-dropdown{opacity:1;visibility:visible;transform:translateY(0) scale(1)}
.notif-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;
  justify-content:space-between;align-items:center;font-size:14px}
.notif-list{max-height:400px;overflow-y:auto;padding:8px}
.notif-loading{padding:8px}
.notif-item{display:flex;gap:12px;padding:12px;border-radius:var(--r-md);cursor:pointer;
  transition:background .2s ease;text-decoration:none;color:inherit;align-items:flex-start;position:relative}
.notif-item:hover{background:var(--bg-soft)}
.notif-item.unread{background:var(--primary-50)}
.notif-item.unread::before{content:'';position:absolute;left:4px;top:50%;width:6px;height:6px;
  border-radius:50%;background:var(--primary);transform:translateY(-50%)}
.notif-icon-wrap{flex-shrink:0;width:38px;height:38px;border-radius:var(--r-md);
  display:flex;align-items:center;justify-content:center}
.notif-icon-wrap.success{background:var(--success-light);color:var(--success-dark)}
.notif-icon-wrap.danger{background:var(--danger-light);color:var(--danger-dark)}
.notif-icon-wrap.warning{background:var(--warning-light);color:var(--warning-dark)}
.notif-icon-wrap.info{background:var(--primary-light);color:var(--primary-dark)}
.notif-body{flex:1;min-width:0}
.notif-title{font-size:13px;font-weight:600;color:var(--text);margin-bottom:2px}
.notif-msg{font-size:12px;color:var(--text-soft);line-height:1.45;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.notif-time{font-size:11px;color:var(--text-mute);margin-top:4px}
.notif-empty{padding:32px 16px;text-align:center;color:var(--text-mute);font-size:13px}

@media(max-width:520px){
  .notif-dropdown{position:fixed;top:60px;right:8px;left:8px;width:auto;max-height:80vh}
}

/* ============================================================
   PREMIUM ANIMATIONS v2.3 — Smoother, more refined
   ============================================================ */

/* Refined easing curves */
:root{
  --ease-soft:cubic-bezier(.22,.61,.36,1);
  --ease-fluid:cubic-bezier(.4,.0,.2,1);
  --ease-back:cubic-bezier(.34,1.56,.64,1);
  --ease-snap:cubic-bezier(.4,.0,.6,1);
  --ease-bounce:cubic-bezier(.68,-.55,.265,1.55);
}

/* Smoother fade-up with refined timing */
@keyframes fadeUpRefined{
  0%{opacity:0;transform:translate3d(0,24px,0) scale(.96);filter:blur(4px)}
  60%{filter:blur(0)}
  100%{opacity:1;transform:translate3d(0,0,0) scale(1);filter:blur(0)}
}
.fade-up{animation:fadeUpRefined .8s var(--ease-soft) both;will-change:transform,opacity}

/* Improved stagger */
.stagger > *{animation:fadeUpRefined .7s var(--ease-soft) both;will-change:transform,opacity}
.stagger > *:nth-child(1){animation-delay:0s}
.stagger > *:nth-child(2){animation-delay:.07s}
.stagger > *:nth-child(3){animation-delay:.14s}
.stagger > *:nth-child(4){animation-delay:.21s}
.stagger > *:nth-child(5){animation-delay:.28s}
.stagger > *:nth-child(6){animation-delay:.35s}
.stagger > *:nth-child(7){animation-delay:.42s}
.stagger > *:nth-child(8){animation-delay:.49s}
.stagger > *:nth-child(n+9){animation-delay:.56s}

/* Smoother card hover */
.card-hover{transition:transform .4s var(--ease-soft), box-shadow .4s var(--ease-soft), border-color .25s ease}
.card-hover:hover{transform:translate3d(0,-4px,0);box-shadow:var(--shadow-md);border-color:var(--primary-200)}

/* Refined button interactions */
.btn{transition:transform .15s var(--ease-snap), box-shadow .25s var(--ease-soft),
                background .2s ease, color .2s ease, border-color .2s ease}
.btn-primary{transition:all .25s var(--ease-soft)}
.btn-primary:hover{transform:translate3d(0,-2px,0)}
.btn-primary:active{transform:translate3d(0,1px,0);transition-duration:.1s}

/* Smooth icon rotation on hover */
.btn:hover .icon{transition:transform .35s var(--ease-back)}

/* Refined modal entry */
@keyframes modalSlide{
  0%{opacity:0;transform:translate3d(0,40px,0) scale(.92);filter:blur(8px)}
  100%{opacity:1;transform:translate3d(0,0,0) scale(1);filter:blur(0)}
}
.modal{animation:modalSlide .45s var(--ease-soft) both}
.modal-backdrop.show{animation:fadeIn .25s var(--ease-fluid) both}

/* Refined toast slide */
@keyframes toastSlide{
  0%{opacity:0;transform:translate3d(120%,0,0) scale(.9)}
  60%{transform:translate3d(-8px,0,0) scale(1.02)}
  100%{opacity:1;transform:translate3d(0,0,0) scale(1)}
}
.toast{animation:toastSlide .55s var(--ease-back) both}

/* Smooth page transition */
@keyframes pageEnterSmooth{
  0%{opacity:0;transform:translate3d(0,12px,0)}
  100%{opacity:1;transform:translate3d(0,0,0)}
}
main, .container > section:first-child{animation:pageEnterSmooth .6s var(--ease-soft) both}

/* Heading reveal */
@keyframes headingReveal{
  0%{opacity:0;transform:translate3d(0,16px,0);clip-path:inset(0 100% 0 0)}
  100%{opacity:1;transform:translate3d(0,0,0);clip-path:inset(0 0 0 0)}
}
.hero h1{animation:headingReveal 1s var(--ease-soft) .1s both}
.hero p.lead{animation:fadeUpRefined .9s var(--ease-soft) .3s both}
.hero .flex.gap-3{animation:fadeUpRefined .9s var(--ease-soft) .5s both}
.hero-stats{animation:fadeUpRefined .9s var(--ease-soft) .7s both}

/* Refined hover-image effect */
img.zoom-in{transition:transform .6s var(--ease-soft)}
.hover-zoom:hover img.zoom-in,
.hover-zoom:hover > img{transform:scale(1.05)}

/* Smooth progress animation */
.progress-bar{transition:width 1.2s var(--ease-soft)}

/* Refined sidebar transition */
.sidebar{transition:transform .4s var(--ease-soft), left .4s var(--ease-soft)}

/* Better link hover */
a:not(.btn){transition:color .2s ease, opacity .2s ease}

/* Smoother form interactions */
.form-control,.form-select,.form-textarea{
  transition:border-color .2s ease, box-shadow .25s var(--ease-soft), background .2s ease;
  will-change:border-color, box-shadow
}

/* Floating label refined */
.form-float .form-label{transition:all .25s var(--ease-soft)}

/* Smooth checkbox/radio */
input[type=checkbox], input[type=radio]{transition:all .15s ease;cursor:pointer}

/* Tab transition */
.tabs a, .tabs button{transition:background .25s var(--ease-soft), color .2s ease, box-shadow .25s var(--ease-soft)}

/* Pricing card hover refined */
.pricing-card{transition:transform .5s var(--ease-soft), box-shadow .4s var(--ease-soft), border-color .3s ease;will-change:transform}
.pricing-card:hover{transform:translate3d(0,-8px,0);box-shadow:var(--shadow-lg)}
.pricing-card.popular:hover{transform:scale(1.05) translate3d(0,-8px,0)}

/* Stat card hover refined */
.stat-card{transition:transform .35s var(--ease-soft), box-shadow .3s ease, border-color .25s ease;will-change:transform}
.stat-card:hover{transform:translate3d(0,-3px,0)}

/* Smoother count animation */
.stat-num.counting{transition:transform .15s var(--ease-back)}

/* Loading skeleton refined */
@keyframes skeletonShimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
.skeleton{
  background:linear-gradient(110deg, var(--bg-mute) 8%, var(--bg-hover) 18%, var(--bg-mute) 33%);
  background-size:200% 100%;
  animation:skeletonShimmer 1.6s ease-in-out infinite
}

/* Smoother accordion */
.faq-item{transition:border-color .25s ease, box-shadow .3s ease}
.faq-q{transition:background .25s ease, color .2s ease}
.faq-a{transition:max-height .45s var(--ease-soft), padding .35s var(--ease-soft)}
.faq-q .icon{transition:transform .35s var(--ease-back), color .2s ease}

/* Smoother nav menu */
.nav-menu a{transition:color .2s ease, opacity .2s ease}
.nav-menu a::after{transition:transform .3s var(--ease-soft);transform-origin:center}
.nav-menu a.active::after, .nav-menu a:hover::after{transform:scaleX(1.1)}

/* Lang switch smoother */
.lang-switch a{transition:all .25s var(--ease-soft);will-change:background, color}

/* Footer link smooth */
.footer a{transition:color .25s ease, transform .2s ease}
.footer a:hover{transform:translateX(2px)}

/* Smoother social icons */
.footer-social a{transition:transform .35s var(--ease-back), background .25s ease, color .2s ease;will-change:transform}
.footer-social a:hover{transform:translate3d(0,-3px,0) rotate(-5deg) scale(1.05)}

/* Sidebar menu refined */
.sidebar-menu a{transition:background .25s ease, color .2s ease, padding-left .25s var(--ease-soft);position:relative;overflow:hidden}
.sidebar-menu a::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--primary-300);
  transform:translateX(-100%);transition:transform .3s var(--ease-soft)}
.sidebar-menu a:hover{padding-left:18px}
.sidebar-menu a:hover::before{transform:translateX(0)}
.sidebar-menu a.active::before{transform:translateX(0);background:#fff}

/* Smoother table rows */
table tbody tr{transition:background .2s ease, transform .2s ease}

/* Image lazy fade */
img[loading="lazy"]{transition:opacity .5s var(--ease-soft);opacity:1}
img[loading="lazy"][data-loading]{opacity:0}

/* Will-change cleanup (after animation) */
.fade-up.animation-end{will-change:auto}

/* Refined ripple */
@keyframes rippleSmooth{
  0%{transform:scale(0);opacity:.5}
  100%{transform:scale(2.8);opacity:0}
}
.btn .ripple{animation:rippleSmooth .65s var(--ease-fluid)}

/* Smoother dropdown */
.dropdown-menu{transition:opacity .25s var(--ease-soft), transform .3s var(--ease-soft), visibility 0s linear .25s}
.dropdown.open .dropdown-menu{transition:opacity .25s var(--ease-soft), transform .3s var(--ease-back), visibility 0s linear 0s}

/* ============================================================
   ENHANCED RESPONSIVENESS
   ============================================================ */

/* Smoother mobile hero */
@media(max-width:480px){
  .hero h1{animation-duration:.8s}
  .hero p.lead{animation-duration:.7s;animation-delay:.2s}
  .hero-stats{animation-delay:.4s}
}

/* Reduce motion hover effects on touch devices */
@media (hover:none){
  .card-hover:hover, .pricing-card:hover, .stat-card:hover{transform:none}
  .btn-primary:hover{transform:none}
}

/* Disable will-change on slow devices */
@media (max-width:480px) and (prefers-reduced-motion:no-preference){
  .pricing-card, .stat-card, .card-hover{will-change:auto}
}

/* Smooth horizontal scroll */
.carousel-track{
  scroll-behavior:smooth;
  -webkit-overflow-scrolling:touch;
  overscroll-behavior:contain;
}

/* Better focus styles */
:focus-visible{outline:2px solid var(--primary);outline-offset:3px;border-radius:4px;
  transition:outline-offset .15s ease}

/* Print refinements */
@media print {
  *,*::before,*::after{
    background:transparent !important;
    color:#000 !important;
    box-shadow:none !important;
    text-shadow:none !important;
    animation:none !important;
    transition:none !important;
  }
}

/* Smooth scrollbar fade */
*::-webkit-scrollbar-thumb{background:transparent;transition:background .3s ease}
*:hover::-webkit-scrollbar-thumb{background:var(--border-strong)}
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
      <button class="menu-toggle" onclick="document.getElementById('navMenu').classList.toggle('open')" aria-label="Menu">
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

// Sidebar toggle
function toggleSidebar(){ document.querySelector('.sidebar')?.classList.toggle('open'); }

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

// Form button loading state
document.addEventListener('submit', e => {
  const form = e.target;
  if (form.tagName !== 'FORM' || form.dataset.noLoading) return;
  const btn = form.querySelector('button[type="submit"]:not([data-no-loading])');
  if (btn && !btn.classList.contains('btn-loading')) {
    btn.classList.add('btn-loading');
    btn.disabled = true;
    // Avto-restore agar form 15 soniyada javob qaytarmasa
    setTimeout(() => {
      btn.classList.remove('btn-loading');
      btn.disabled = false;
    }, 15000);
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

// Mobile sidebar overlay
(function(){
  const sidebar = document.querySelector('.sidebar');
  if (!sidebar) return;
  let overlay = document.querySelector('.sidebar-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    sidebar.parentNode.insertBefore(overlay, sidebar.nextSibling);
  }
  overlay.addEventListener('click', () => sidebar.classList.remove('open'));
  // Close on link click (mobile)
  sidebar.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => {
      if (window.innerWidth <= 992) sidebar.classList.remove('open');
    });
  });
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
<aside class="sidebar">
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
