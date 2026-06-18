<?php
/**
 * ScrapeGuard — to'liq PHP-based anti-scraping va xavfsizlik middleware
 *
 * Bu fayl .htaccess'dan murakkab logikani o'ziga oldi:
 *   - User-Agent bloklash (50+ bot/scraper)
 *   - Honeypot URL traps (.env, .git, wp-admin, va h.k.)
 *   - Per-IP burst rate limit
 *   - SQL/XSS query probe detection
 *   - Hotlink himoyasi rasm fayllar uchun
 *   - JS-side himoya (copy/paste/F12/devtools)
 *
 * Foydalanish:
 *   includes/functions.php avtomatik chaqiradi:
 *     ScrapeGuard::guard();   // request boshida
 *     ScrapeGuard::js_protection();  // footer'da JS+CSS chiqaradi
 */

class ScrapeGuard {

    /** Botlar va scraper vositalari (qattiq blok) */
    private const BLOCKED_UA = [
        // HTTP klientlar
        'curl/', 'wget/', 'libwww-perl', 'lwp::simple',
        'python-requests', 'python-urllib', 'urllib/',
        'go-http-client', 'java/', 'okhttp/', 'apache-httpclient',
        'httpclient', 'rest-client', 'restsharp', 'guzzlehttp',
        'reqwest/', 'fasthttp', 'http_request', 'simpletest',

        // Headless brauzerlar
        'phantomjs', 'headlesschrome', 'puppeteer', 'playwright',
        'selenium', 'webdriver',

        // Scraper-frameworklar
        'scrapy', 'screaming frog', 'phpcrawl', 'crawler', 'spider',
        'scraper', 'extract', 'fetch', 'harvest',

        // SEO botlar (bizga foyda yo'q, faqat resurs sarflaydi)
        'mj12bot', 'ahrefsbot', 'semrushbot', 'dotbot', 'rogerbot',
        'sogou', 'exabot', 'baiduspider', 'yandex.com/bots',
        'megaindex', 'serpstatbot', 'dataforseobot', 'petalbot',
        'mauibot', 'bytedance', 'amazonbot',

        // AI scraper'lar
        'gpt-3', 'gptbot', 'chatgpt-user', 'oai-searchbot',
        'claudebot', 'anthropic-ai', 'cohere-ai',
        'ccbot', 'common crawl', 'commoncrawl',
        'perplexitybot', 'google-extended', 'applebot-extended',

        // Pentest vositalari (xavfli)
        'masscan', 'nmap', 'sqlmap', 'nikto', 'wpscan',
        'acunetix', 'nessus', 'qualys', 'burpsuite', 'zaproxy',
        'dirbuster', 'gobuster', 'wfuzz', 'arachni',
    ];

    /** Yaxshi botlar — ruxsat etiladi (SEO uchun foydali) */
    private const ALLOWED_BOTS = [
        'googlebot', 'bingbot', 'duckduckbot', 'yandexbot',
        'telegrambot', 'twitterbot', 'facebookexternalhit',
        'whatsapp', 'linkedinbot', 'slackbot', 'discordbot',
        'applebot/', 'pingdom',
    ];

    /** Sezgir endpoint'lar — qattiqroq tekshirish */
    private const SENSITIVE_PATHS = ['/api/', '/user/', '/admin/', '/developer/'];

    /** Honeypot URL'lar — tegsa darhol ban */
    private const HONEYPOT_PATTERNS = [
        '#^/?wp-admin#i', '#^/?wp-login#i', '#^/?wp-content#i', '#^/?wp-includes#i',
        '#^/?xmlrpc\.php#i', '#^/?wlwmanifest\.xml#i',
        '#^/?administrator/?#i', '#^/?phpmyadmin#i', '#^/?pma/?#i',
        '#^/?adminer\.php#i', '#^/?config\.php\.bak#i',
        '#\.env\b#', '#\.git/#', '#\.aws/#', '#\.docker/#', '#\.svn/#',
        '#\.well-known/security#i',
        '#/database\.sql\b#i', '#/dump\.sql#i', '#/backup\b#i',
        '#/console/?$#i', '#/admin\.php\b#i', '#/shell\.php\b#i',
        '#\.(sql|tar|gz|zip|7z|rar)$#i',
    ];

    /** Asosiy himoya — har bir public sahifa boshida chaqiriladi */
    public static function guard(): void {
        // CLI rejimida (cron) tekshirmaymiz
        if (php_sapi_name() === 'cli') return;

        $ua  = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $ip  = self::ip();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // 1) Webhook'larni umuman tekshirmaymiz (Click/Payme to'lov gateway'lari)
        if (str_starts_with($uri, '/api/click.php') ||
            str_starts_with($uri, '/api/payme.php') ||
            str_starts_with($uri, '/telegram/')) {
            return;
        }

        // 2) IP ban check
        if (Security::is_ip_banned($ip)) {
            self::deny(403, 'Sizning IP manzilingiz vaqtincha bloklangan');
        }

        // 3) Honeypot — 24 soat ban
        foreach (self::HONEYPOT_PATTERNS as $pat) {
            if (preg_match($pat, $uri)) {
                Security::ban_ip($ip, 1440);
                Security::audit('honeypot_hit', "URI: " . substr($uri, 0, 200), 'critical');
                self::deny(404, 'Not Found');
            }
        }

        // 4) Query string'da SQL/XSS probe (URL-decode qilingan)
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        if ($qs) {
            // Raw + decoded ikkala variantni tekshiramiz
            $decoded = urldecode($qs);
            if (self::is_malicious_query($qs) || self::is_malicious_query($decoded)) {
                Security::ban_ip($ip, 240); // 4 soat
                Security::audit('malicious_query', "QS: " . substr($decoded, 0, 200), 'critical');
                self::deny(403, 'Bad request');
            }
        }

        // 5) UA bloklash — yomon bot
        $isBlocked = false;
        foreach (self::BLOCKED_UA as $needle) {
            if ($needle && str_contains($ua, $needle)) {
                $isBlocked = true;
                break;
            }
        }

        // Yaxshi botlarni ajratamiz
        $isGoodBot = false;
        foreach (self::ALLOWED_BOTS as $good) {
            if (str_contains($ua, $good)) {
                $isGoodBot = true;
                break;
            }
        }

        // Bo'sh yoki juda qisqa UA = bot
        if (empty($ua) || strlen($ua) < 10) {
            $isBlocked = true;
        }

        if ($isBlocked && !$isGoodBot) {
            Security::audit('scraper_blocked', "UA: " . substr($ua, 0, 100), 'warning');
            self::deny(403, 'Access denied');
        }

        // 6) Header anomaly — sezgir sahifalarda Accept yo'q (bot belgi)
        $isSensitive = false;
        foreach (self::SENSITIVE_PATHS as $p) {
            if (str_starts_with($uri, $p)) { $isSensitive = true; break; }
        }
        if ($isSensitive && !$isGoodBot) {
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            if (empty($accept)) {
                Security::audit('scraper_no_accept', "UA: " . substr($ua, 0, 100), 'warning');
                self::deny(403, 'Access denied');
            }
        }

        // 7) Burst rate limit — 30 sekundda 60+ so'rov = bot
        $burst = Security::rate_limit('burst_' . $ip, 60, 30);
        if (!$burst['allowed']) {
            Security::ban_ip($ip, 60);
            Security::audit('burst_ban', "IP: $ip", 'warning');
            self::deny(429, 'Too many requests');
        }
    }

    /**
     * SQL/XSS probe detection — query string'da xavfli pattern qidiradi
     */
    private static function is_malicious_query(string $qs): bool {
        $patterns = [
            // SQL injection
            '/(union\s+select|select\s+.*\s+from|insert\s+into|drop\s+table|update\s+.*\s+set)/i',
            '/(\bor\s+1\s*=\s*1\b|\band\s+1\s*=\s*1\b)/i',
            '/(load_file\s*\(|outfile\s|into\s+dumpfile)/i',
            // XSS
            '/(<|%3c)\s*script/i',
            '/javascript\s*:/i',
            '/on(load|error|click|mouseover|focus)\s*(=|%3d)/i',
            '/(<|%3c)\s*iframe/i',
            // Path traversal
            '/\.\.\/|\.\.\\\\/',
            '/\/etc\/passwd|\/proc\/self/i',
            // PHP RCE
            '/(eval|system|exec|passthru|shell_exec|popen|proc_open)\s*\(/i',
            '/php:\/\/(input|filter)/i',
            // Local file inclusion
            '/file:\/\/|expect:\/\//i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $qs)) return true;
        }
        return false;
    }

    /**
     * Hotlink protection — rasm direct request bo'lsa, REFERER tekshiradi
     */
    private static function check_hotlink(): void {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (empty($referer)) return; // Direct request — ruxsat (link share, etc.)

        $myHost = $_SERVER['HTTP_HOST'] ?? '';
        $parsed = parse_url($referer);
        $refHost = $parsed['host'] ?? '';
        if (empty($refHost)) return;

        // Bizning host
        if (strcasecmp($refHost, $myHost) === 0) return;
        // www variant
        if (strcasecmp($refHost, 'www.' . $myHost) === 0) return;
        if (strcasecmp('www.' . $refHost, $myHost) === 0) return;

        // Allowed: search/social
        $allowed = ['google.', 'bing.', 'duckduckgo.', 'yandex.', 'yahoo.',
                    't.me', 'telegram.', 'facebook.', 'twitter.', 'instagram.'];
        foreach ($allowed as $a) {
            if (str_contains($refHost, $a)) return;
        }

        // Boshqa joydan keldi — 403
        http_response_code(403);
        exit;
    }

    private static function deny(int $code, string $msg): void {
        http_response_code($code);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            header('X-Robots-Tag: noindex, nofollow');
        }
        echo "<!DOCTYPE html><html lang=\"uz\"><head><meta charset=\"UTF-8\"><title>$code</title>";
        echo "<style>body{font-family:system-ui,-apple-system,sans-serif;text-align:center;padding:80px 20px;color:#475569;background:#F8FAFC;margin:0}";
        echo "h1{font-size:96px;margin:0;color:#0F172A;font-weight:900}p{font-size:14px;color:#64748B;margin-top:8px}</style></head><body>";
        echo "<h1>$code</h1><p>" . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</p></body></html>";
        exit;
    }

    private static function ip(): string {
        return Security::client_ip();
    }

    /**
     * JS himoya skripti — render_footer() ichidan inject qilinadi.
     *
     * Klient-side himoya (texnik foydalanuvchi bypass qiladi, lekin oddiy
     * copy-paste va casual scraping'ni 90% to'sib qo'yadi). Asosiy himoya
     * yuqoridagi server-side guard().
     */
    public static function js_protection(): string {
        return <<<'JS'
<script>
(function(){
  'use strict';
  // O'ng tugma kontekst menyusi bloklash (faqat .protect bo'lgan bloklarda)
  document.addEventListener('contextmenu', function(e){
    if (e.target.closest('.protect, [data-protect], .test-main, .answer-list, .q-text, .q-image')) {
      e.preventDefault();
      return false;
    }
  }, false);

  // Klaviatura yorliqlari
  document.addEventListener('keydown', function(e){
    var key = e.key || '';
    var k = key.toLowerCase();
    var ctrl = e.ctrlKey || e.metaKey;
    var shift = e.shiftKey;
    if (key === 'F12') { e.preventDefault(); return false; }
    if (ctrl && k === 'u') { e.preventDefault(); return false; }
    if (ctrl && k === 's') { e.preventDefault(); return false; }
    if (ctrl && k === 'p' && document.querySelector('.protect, .test-main')) {
      e.preventDefault(); return false;
    }
    if (ctrl && shift && (k === 'i' || k === 'j' || k === 'c')) {
      e.preventDefault(); return false;
    }
    if (e.metaKey && e.altKey && k === 'i') {
      e.preventDefault(); return false;
    }
  }, false);

  // Copy/Cut event'lari — sezgir bloklarda
  ['copy','cut'].forEach(function(ev){
    document.addEventListener(ev, function(e){
      var t = e.target;
      if (t.matches && t.matches('input, textarea, [contenteditable="true"]')) return;
      if (t.closest && t.closest('.protect, [data-protect], .test-main, .answer-list, .q-text')) {
        e.preventDefault();
        if (window.toast) toast('Nusxa olish ruxsat etilmagan', 'warning', 1500);
        return false;
      }
    }, false);
  });

  // Drag — rasm va matn drag'ni bloklash protected bloklarda
  document.addEventListener('dragstart', function(e){
    if (e.target.closest('.protect, [data-protect], .test-main') ||
        (e.target.tagName === 'IMG' && !e.target.dataset.allowSave)) {
      e.preventDefault();
      return false;
    }
  }, false);

  // Selection — sezgir bloklar
  document.addEventListener('selectstart', function(e){
    if (e.target.closest('.no-select, [data-no-select], .answer-list .answer-item')) {
      e.preventDefault();
      return false;
    }
  }, false);

  // DevTools detection
  var devtoolsOpen = false;
  function checkDevtools(){
    var threshold = 160;
    var widthDiff = window.outerWidth - window.innerWidth;
    var heightDiff = window.outerHeight - window.innerHeight;
    if (widthDiff > threshold || heightDiff > threshold) {
      if (!devtoolsOpen) {
        devtoolsOpen = true;
        if (window.toast) toast('DevTools aniqlandi', 'warning', 2000);
      }
    } else {
      devtoolsOpen = false;
    }
  }
  setInterval(checkDevtools, 1500);

  // Console banner
  try {
    console.log('%cDIQQAT', 'font-size:24px;font-weight:bold;color:#EF4444;background:#FEE2E2;padding:6px 14px;border-radius:6px');
    console.log('%cBu konsoldan saytdagi ma\'lumotlarni o\'g\'irlash yoki yig\'ish taqiqlanadi. Barcha amallar log qilinadi va IP\'ngiz bloklanishi mumkin.', 'font-size:13px;color:#64748B;line-height:1.5');
  } catch(e) {}

  // Image protection
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('img').forEach(function(img){
      if (img.dataset.allowSave) return;
      img.draggable = false;
      img.style.userSelect = 'none';
      img.style.webkitUserDrag = 'none';
    });
  });
})();
</script>
<style>
.protect, [data-protect], .test-main .q-text, .test-main .q-image, .answer-list, .no-select, [data-no-select]{
  -webkit-user-select:none;
  -moz-user-select:none;
  -ms-user-select:none;
  user-select:none;
  -webkit-touch-callout:none;
}
.protect input, .protect textarea, .protect [contenteditable="true"]{
  -webkit-user-select:text !important;
  -moz-user-select:text !important;
  user-select:text !important;
}
img:not([data-allow-save]){
  -webkit-user-drag:none;
  -khtml-user-drag:none;
  -moz-user-drag:none;
  -o-user-drag:none;
  user-drag:none;
}
@media print{
  .protect, [data-protect], .no-print, .test-main{display:none !important}
  body::before{
    content:"Bu kontent chop etish uchun ruxsat etilmagan.";
    display:block;text-align:center;padding:40px;font-size:24px;
  }
}
</style>
JS;
    }
}

// Auto-call: scrape_guard.php yuklangan zahoti himoya boshlanadi
ScrapeGuard::guard();
