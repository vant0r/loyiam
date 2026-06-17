<?php
/**
 * ScrapeGuard — anti-scraping va anti-data-harvesting himoya
 *
 * Vazifalari:
 *   1. Bot/scraper user-agent'larni bloklash (curl, wget, python-requests, ...)
 *   2. Header anomaliyalarini aniqlash (Accept yo'q, Cookie yo'q, va h.k.)
 *   3. Honeypot URL traps
 *   4. Per-IP burst limiting (qisqa vaqtda juda ko'p so'rov yuborgan IP'larni ban)
 *   5. JS-side himoya: copy/cut/paste/right-click/F12/view-source bloklash
 *   6. Rasm drag/save bloklash
 *   7. Text selection cheklash (sezgir kontent uchun)
 *
 * Public sahifalar uchun yumshoqroq, admin/test sahifalar uchun qattiqroq.
 */

class ScrapeGuard {

    /** Botlar va scrape vositalari (qattiq blok) */
    private const BLOCKED_UA = [
        'curl', 'wget', 'libwww-perl', 'python-requests', 'python-urllib',
        'go-http-client', 'java/', 'okhttp', 'apache-httpclient',
        'httpclient', 'httpunit', 'phantomjs', 'headlesschrome',
        'selenium', 'webdriver', 'puppeteer', 'playwright',
        'scrapy', 'screaming frog', 'crawler', 'spider', 'scraper',
        'mj12bot', 'ahrefsbot', 'semrushbot', 'dotbot', 'rogerbot',
        'sogou', 'exabot', 'baiduspider', 'yandex.com/bots',
        'megaindex', 'serpstatbot', 'dataforseobot', 'petalbot',
        'mauibot', 'bytedance', 'amazonbot', 'gpt-3', 'gptbot',
        'chatgpt', 'claudebot', 'anthropic-ai', 'cohere-ai',
        'ccbot', 'common crawl', 'commoncrawl',
        'http_request', 'fasthttp', 'nimbostratus', 'embedly',
        'wp-rocket', 'siteliner', 'sitemap', 'feedfetcher-google',
        'simpletest', 'phpcrawl', 'masscan', 'nmap', 'sqlmap',
        'nikto', 'wpscan', 'acunetix', 'nessus', 'qualys',
        'rest-client', 'restsharp', 'guzzlehttp', 'reqwest',
    ];

    /** Yaxshi botlar — ruxsat beriladi (SEO foyda) */
    private const ALLOWED_BOTS = [
        'googlebot', 'bingbot', 'duckduckbot', 'yandexbot',
        'telegrambot', 'twitterbot', 'facebookexternalhit',
        'whatsapp', 'linkedinbot', 'slackbot', 'discordbot',
    ];

    /** Sezgir endpoint'lar — qattiqroq tekshiriladi */
    private const SENSITIVE_PATHS = ['/api/', '/user/', '/admin/', '/developer/'];

    /**
     * Asosiy himoya — har bir public sahifa boshida chaqiriladi.
     */
    public static function guard(): void {
        // CLI rejimida (cron) tekshirmaymiz
        if (php_sapi_name() === 'cli') return;

        $ua  = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $ip  = self::ip();

        // 1) Public payment webhook'lar (Click/Payme) bot detection'sis ishlashi kerak
        if (str_starts_with($uri, '/api/click.php') || str_starts_with($uri, '/api/payme.php')) {
            return;
        }

        // 2) IP ban tekshiruvi (security.php auto-handles, lekin yana qaraymiz)
        if (Security::is_ip_banned($ip)) {
            self::deny(403, 'Sizning IP manzilingiz vaqtincha bloklangan');
        }

        // 3) Honeypot trap — bot bo'lsa darhol ban
        if (preg_match('#^/(wp-admin|wp-login|administrator|phpmyadmin|\.env|\.git|\.aws|/admin/config\.php|admin\.php|backup\.|database\.sql)#i', $uri)) {
            Security::ban_ip($ip, 1440); // 24 soat
            Security::audit('honeypot_hit', "URI: $uri, UA: " . substr($ua, 0, 100), 'critical');
            self::deny(404, 'Not Found');
        }

        // 4) UA bloklash (yomon bot)
        $isBlocked = false;
        foreach (self::BLOCKED_UA as $needle) {
            if ($needle && str_contains($ua, $needle)) {
                $isBlocked = true;
                break;
            }
        }

        // 5) Yaxshi botlarni istisno qilamiz (faqat public sahifalar)
        $isGoodBot = false;
        foreach (self::ALLOWED_BOTS as $good) {
            if (str_contains($ua, $good)) {
                $isGoodBot = true;
                break;
            }
        }

        // 6) UA umuman yo'q — botlar shu yo'l bilan kelishadi
        if (empty($ua) || strlen($ua) < 10) {
            $isBlocked = true;
        }

        if ($isBlocked && !$isGoodBot) {
            Security::audit('scraper_blocked', "UA: " . substr($ua, 0, 100) . " IP: $ip", 'warning');
            self::deny(403, 'Access denied');
        }

        // 7) Header anomaly — Accept yo'q yoki Accept-Language yo'q
        // Faqat sensitive sahifalarda tekshiramiz (false-positive oldini olish uchun)
        $isSensitive = false;
        foreach (self::SENSITIVE_PATHS as $p) {
            if (str_starts_with($uri, $p)) { $isSensitive = true; break; }
        }
        if ($isSensitive && !$isGoodBot) {
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            if (empty($accept) || $accept === '*/*') {
                Security::audit('scraper_no_accept', "UA: " . substr($ua, 0, 100), 'warning');
                self::deny(403, 'Access denied');
            }
        }

        // 8) Burst rate limit — har bir IP juda ko'p so'rov yuborsa
        // 30 sekundda 60+ so'rov = bot
        $burst = Security::rate_limit('burst_' . $ip, 60, 30);
        if (!$burst['allowed']) {
            Security::ban_ip($ip, 60); // 1 soat
            Security::audit('burst_ban', "IP: $ip, UA: " . substr($ua, 0, 80), 'warning');
            self::deny(429, 'Too many requests');
        }
    }

    private static function deny(int $code, string $msg): void {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        echo "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><title>$code</title>";
        echo "<style>body{font-family:system-ui;text-align:center;padding:80px 20px;color:#475569;background:#F8FAFC}";
        echo "h1{font-size:64px;margin:0;color:#0F172A}p{font-size:14px;color:#94A3B8}</style></head><body>";
        echo "<h1>$code</h1><p>" . htmlspecialchars($msg) . "</p></body></html>";
        exit;
    }

    private static function ip(): string {
        return Security::client_ip();
    }

    /**
     * JS himoya skripti — render_footer() ichidan inject qilinadi.
     * Quyidagilarni bloklash:
     *   - O'ng tugma (context menu)
     *   - F12, Ctrl+U, Ctrl+S, Ctrl+P, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C
     *   - Copy/Cut event'lari (faqat .protect class'ida)
     *   - Image drag
     *   - DevTools detection (rasm chiqaradi)
     *
     * Tushuntirish: Bu klient-side himoya. Texnik foydalanuvchi hammasi
     * bypass qilinadi, lekin oddiy copy-paste va casual scraping'ni 90%
     * to'sib qo'yadi. Server-side rate-limit + UA blok asosiy himoya.
     */
    public static function js_protection(): string {
        return <<<'JS'
<script>
(function(){
  'use strict';
  // O'ng tugma kontekst menyusini bloklash (faqat .protect bo'lgan bloklarda)
  document.addEventListener('contextmenu', function(e){
    if (e.target.closest('.protect, [data-protect], img:not([data-allow-save]), .test-main, .answer-list, .q-text, .q-image')) {
      e.preventDefault();
      return false;
    }
  }, false);

  // Klaviatura yorliqlari — copy/save/view-source/devtools
  document.addEventListener('keydown', function(e){
    var key = e.key || '';
    var k = key.toLowerCase();
    var ctrl = e.ctrlKey || e.metaKey;
    var shift = e.shiftKey;

    // F12 — DevTools
    if (key === 'F12') { e.preventDefault(); return false; }
    // Ctrl+U — view source
    if (ctrl && k === 'u') { e.preventDefault(); return false; }
    // Ctrl+S — save page
    if (ctrl && k === 's') { e.preventDefault(); return false; }
    // Ctrl+P — print (test sahifasida)
    if (ctrl && k === 'p' && document.querySelector('.protect, .test-main')) {
      e.preventDefault(); return false;
    }
    // Ctrl+Shift+I/J/C — DevTools shortcut
    if (ctrl && shift && (k === 'i' || k === 'j' || k === 'c')) {
      e.preventDefault(); return false;
    }
    // Cmd+Option+I (Mac)
    if (e.metaKey && e.altKey && k === 'i') {
      e.preventDefault(); return false;
    }
  }, false);

  // Copy/Cut event'lari — sezgir bloklarda
  ['copy','cut','paste'].forEach(function(ev){
    document.addEventListener(ev, function(e){
      var t = e.target;
      // Inputs/textareas — ruxsat beramiz (foydalanuvchi paste qiladi)
      if (t.matches('input, textarea, [contenteditable="true"]')) return;
      // .protect bloklarida bloklaymiz
      if (t.closest('.protect, [data-protect], .test-main, .answer-list, .q-text')) {
        e.preventDefault();
        if (window.toast) toast('Nusxa olish ruxsat etilmagan', 'warning', 1500);
        return false;
      }
    }, false);
  });

  // Drag — rasm va matn drag'ni bloklaymiz protected bloklarda
  document.addEventListener('dragstart', function(e){
    if (e.target.closest('.protect, [data-protect], img:not([data-allow-save]), .test-main')) {
      e.preventDefault();
      return false;
    }
  }, false);

  // Selection — sezgir test/savol bloklarida text selection bloklaymiz
  document.addEventListener('selectstart', function(e){
    if (e.target.closest('.no-select, [data-no-select], .answer-list .answer-item')) {
      e.preventDefault();
      return false;
    }
  }, false);

  // DevTools detection (rasm chiqaradi — texnik foydalanuvchi bypass qiladi,
  // lekin casual zo'rlashga to'sqinlik qiladi)
  var devtoolsDetected = false;
  function checkDevtools(){
    var threshold = 160;
    var widthDiff = window.outerWidth - window.innerWidth;
    var heightDiff = window.outerHeight - window.innerHeight;
    if (widthDiff > threshold || heightDiff > threshold) {
      if (!devtoolsDetected) {
        devtoolsDetected = true;
        if (window.toast) toast('DevTools aniqlandi. Ba\'zi funksiyalar cheklangan.', 'warning', 2500);
      }
    }
  }
  setInterval(checkDevtools, 1500);

  // Console banner
  try {
    var styles = 'font-size:18px;font-weight:bold;color:#EF4444;';
    console.log('%c⛔ Diqqat!', styles);
    console.log('%cBu konsoldan foydalanish orqali sayt ma\'lumotlarini yig\'ish yoki o\'g\'irlash taqiqlanadi. Barcha amallar log qilinadi va IP\'ngiz bloklanishi mumkin.', 'font-size:13px;color:#475569');
  } catch(e) {}

  // Image protection — dragging, save dialog blokirovkasi
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('img').forEach(function(img){
      if (img.dataset.allowSave) return;
      img.addEventListener('contextmenu', function(e){ e.preventDefault(); });
      img.draggable = false;
      img.style.userSelect = 'none';
      img.style.webkitUserDrag = 'none';
    });
  });
})();
</script>
<style>
/* Sezgir bloklarda text selection'ni bloklaymiz */
.protect, [data-protect], .test-main .q-text, .test-main .q-image, .answer-list, .no-select, [data-no-select]{
  -webkit-user-select:none;
  -moz-user-select:none;
  -ms-user-select:none;
  user-select:none;
  -webkit-touch-callout:none;
}
/* Inputlar va textareas tabiiy ishlasin */
.protect input, .protect textarea, .protect [contenteditable="true"]{
  -webkit-user-select:text !important;
  -moz-user-select:text !important;
  user-select:text !important;
}
/* Rasm dragging */
img:not([data-allow-save]){
  -webkit-user-drag:none;
  -khtml-user-drag:none;
  -moz-user-drag:none;
  -o-user-drag:none;
  user-drag:none;
  pointer-events:auto;
}
/* Print bloklash sezgir bloklar uchun */
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
