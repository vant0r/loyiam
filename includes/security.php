<?php
/**
 * Xavfsizlik moduli — CSRF, rate limit, audit log, secure upload, password policy
 *
 * v3.0 — Audit fixes:
 *   - CSRF empty-token branch endi FAIL qiladi (oldin attacker-supplied token qabul qilardi)
 *   - SVG upload bloklandi (XSS vektori)
 *   - HSTS header HTTPS uchun
 *   - CSP'dan unsafe-eval olib tashlandi
 *   - Stronger session fingerprint enforcement
 *   - Atomic rate-limit (flock)
 *   - JSON-based cache helpers (unserialize o'rniga)
 */
require_once __DIR__ . '/database.php';

class Security {

    // ============================================================
    // CSRF (Cross-Site Request Forgery) himoyasi
    // ============================================================

    public static function csrf_token(): string {
        if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time'])
            || (time() - $_SESSION['csrf_token_time']) > 7200) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::csrf_token(), ENT_QUOTES) . '">';
    }

    /**
     * CSRF tokenni tekshirish — XAVFSIZ versiya:
     *   - Sessiyada token bo'lmasa FAIL qaytaradi (oldingi versiya attacker-supplied
     *     tokenni qabul qilardi — bu CRITICAL bypass edi)
     *   - Eski token muddati o'tgan bo'lsa FAIL
     *   - hash_equals timing-safe taqqoslash
     */
    public static function csrf_check(?string $token = null): bool {
        $token = $token ?? ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($token) || !is_string($token)) return false;

        // Sessiyada token YO'Q bo'lsa — REDDETAMIZ. Foydalanuvchi forma sahifasini
        // qaytadan ochishi kerak (token unda generatsiya qilinadi).
        if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time'])) {
            return false;
        }

        // Muddati o'tgan bo'lsa — REDDETAMIZ
        if ((time() - $_SESSION['csrf_token_time']) > 7200) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function csrf_require(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !self::csrf_check()) {
            http_response_code(419);
            die('<h1>419 — CSRF token noto\'g\'ri</h1><p>Sahifani yangilang va qayta urinib ko\'ring.</p>');
        }
    }

    // ============================================================
    // Rate limiting (atomic — flock bilan)
    // ============================================================

    /**
     * @return array{allowed: bool, remaining: int, reset_at: int}
     */
    public static function rate_limit(string $key, int $max = 5, int $window = 900): array {
        $cache_dir = __DIR__ . '/../cache/ratelimit';
        if (!is_dir($cache_dir)) @mkdir($cache_dir, 0755, true);
        $file = $cache_dir . '/' . hash('sha256', $key) . '.json';

        $now = time();
        $fp = @fopen($file, 'c+');
        if (!$fp) {
            // Fallback: cache yaroqsiz bo'lsa, ruxsat beramiz (DoS oldini olish)
            return ['allowed' => true, 'remaining' => $max, 'reset_at' => $now + $window];
        }

        @flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $data = ['count' => 0, 'first_at' => $now];

        if ($raw) {
            $existing = json_decode($raw, true);
            if (is_array($existing) && ($now - ($existing['first_at'] ?? 0)) < $window) {
                $data = $existing;
            }
        }

        $data['count']++;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        @flock($fp, LOCK_UN);
        fclose($fp);

        $allowed = $data['count'] <= $max;
        return [
            'allowed'   => $allowed,
            'remaining' => max(0, $max - $data['count']),
            'reset_at'  => $data['first_at'] + $window,
        ];
    }

    /**
     * Client IP — TRUSTED_PROXIES sozlamasida bo'lgan IP'lardan kelgan
     * X-Forwarded-For headerlarini qabul qilamiz, qolganlarida REMOTE_ADDR.
     */
    public static function client_ip(): string {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $trusted = explode(',', $_ENV['TRUSTED_PROXIES'] ?? '');
        $trusted = array_filter(array_map('trim', $trusted));

        // Agar so'rov ishonchli proxydan kelmasa — REMOTE_ADDR'ga ishonamiz
        if (!in_array($remote, $trusted, true)) {
            return filter_var($remote, FILTER_VALIDATE_IP) ?: '0.0.0.0';
        }

        // Ishonchli proxy ortidamiz — header'larni o'qiymiz
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return $remote;
    }

    // ============================================================
    // IP banlash
    // ============================================================

    public static function is_ip_banned(string $ip = ''): bool {
        $ip = $ip ?: self::client_ip();
        $f  = __DIR__ . '/../cache/banned_ips.json';
        if (!is_file($f)) return false;
        $list = json_decode(@file_get_contents($f), true) ?: [];
        $ban  = $list[$ip] ?? null;
        if (!$ban) return false;
        if ($ban < time()) {
            unset($list[$ip]);
            @file_put_contents($f, json_encode($list), LOCK_EX);
            return false;
        }
        return true;
    }

    public static function ban_ip(string $ip, int $minutes = 60): void {
        $f = __DIR__ . '/../cache/banned_ips.json';
        if (!is_dir(dirname($f))) @mkdir(dirname($f), 0755, true);
        $list = is_file($f) ? (json_decode(@file_get_contents($f), true) ?: []) : [];
        $list[$ip] = time() + ($minutes * 60);
        @file_put_contents($f, json_encode($list), LOCK_EX);
    }

    // ============================================================
    // Audit log (DB ga yozish)
    // ============================================================

    public static function audit(string $action, string $description = '', string $level = 'info', ?int $userId = null): void {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        try {
            db()->execute(
                "INSERT INTO logs (user_id, action, description, ip_address, user_agent, level)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    mb_substr($action, 0, 64),
                    mb_substr($description, 0, 500),
                    self::client_ip(),
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
                    $level,
                ]
            );
        } catch (\Throwable $e) {
            // Audit log yozilmasa ham, asosiy oqim to'xtamasligi kerak
            @error_log("audit failed: " . $e->getMessage());
        }
    }

    // ============================================================
    // Parol siyosati
    // ============================================================

    public static function validate_password(string $password, int $minLen = 8): array {
        $errors = [];
        $score = 0;
        if (strlen($password) < $minLen) {
            $errors[] = "Parol kamida $minLen belgi bo'lishi kerak";
        } else {
            $score++;
        }
        if (preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password)) {
            $score++;
        } elseif (!$errors) {
            $errors[] = "Parolda kichik va katta harflar bo'lishi tavsiya etiladi";
        }
        if (preg_match('/\d/', $password)) $score++;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $score++;

        $common = ['password','12345678','qwerty123','admin123','password123','iloveyou','welcome1','admin1234','user1234','test1234'];
        if (in_array(strtolower($password), $common, true)) {
            $errors[] = "Bu parol juda oddiy. Murakkabroq parol tanlang";
            $score = 0;
        }
        return [
            'ok'     => empty($errors) && $score >= 2,
            'errors' => $errors,
            'score'  => min(4, $score),
        ];
    }

    public static function password_strength_meter(): string {
        return <<<'HTML'
<div class="pw-strength" id="pwStrength" style="margin-top:8px;display:none">
  <div style="display:flex;gap:4px;margin-bottom:6px">
    <div class="pw-bar" style="flex:1;height:4px;border-radius:2px;background:var(--bg-mute);transition:background .2s"></div>
    <div class="pw-bar" style="flex:1;height:4px;border-radius:2px;background:var(--bg-mute);transition:background .2s"></div>
    <div class="pw-bar" style="flex:1;height:4px;border-radius:2px;background:var(--bg-mute);transition:background .2s"></div>
    <div class="pw-bar" style="flex:1;height:4px;border-radius:2px;background:var(--bg-mute);transition:background .2s"></div>
  </div>
  <div class="pw-text" style="font-size:12px;color:var(--text-mute)"></div>
</div>
<script>
(function(){
  const labels = ["Juda zaif","Zaif","O'rtacha","Yaxshi","Mukammal"];
  const colors = ["#EF4444","#F59E0B","#FBBF24","#84CC16","#10B981"];
  function check(p){
    let s=0;
    if(p.length>=8) s++;
    if(/[a-z]/.test(p)&&/[A-Z]/.test(p)) s++;
    if(/\d/.test(p)) s++;
    if(/[^a-zA-Z0-9]/.test(p)) s++;
    return Math.min(s,4);
  }
  document.querySelectorAll('input[type="password"][data-strength]').forEach(input => {
    input.addEventListener('input', () => {
      const wrap = document.getElementById('pwStrength');
      if (!wrap) return;
      if (!input.value) { wrap.style.display='none'; return; }
      wrap.style.display='block';
      const s = check(input.value);
      const bars = wrap.querySelectorAll('.pw-bar');
      bars.forEach((b,i) => b.style.background = i < s ? colors[s] : 'var(--bg-mute)');
      const txt = wrap.querySelector('.pw-text');
      txt.textContent = labels[s] || labels[0];
      txt.style.color = colors[s] || colors[0];
    });
  });
})();
</script>
HTML;
    }

    // ============================================================
    // Secure file upload (SVG BLOKLANDI — XSS vektori)
    // ============================================================

    /**
     * @return array{ok: bool, path?: string, url?: string, error?: string}
     */
    public static function upload_image(array $file, string $prefix = 'img', int $maxSize = 5242880): array {
        if (!isset($file['tmp_name']) || empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Fayl yuklanmadi'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Yaroqsiz upload'];
        }
        if ($file['size'] > $maxSize) {
            return ['ok' => false, 'error' => 'Fayl hajmi katta (max ' . round($maxSize/1024/1024, 1) . ' MB)'];
        }

        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
        $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');
        if ($finfo) finfo_close($finfo);

        // SVG BLOKLANDI — kiruvchi SVG'da onclick/animate/onbegin/foreignObject
        // kabi vektorlar bor. Faqat raster formatlarni qabul qilamiz.
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];
        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'error' => 'Faqat JPG, PNG, WebP yoki GIF rasm yuklash mumkin'];
        }

        $check = @getimagesize($file['tmp_name']);
        if ($check === false) {
            return ['ok' => false, 'error' => 'Fayl haqiqiy rasm emas'];
        }

        // Maksimal o'lchamlar
        if ($check[0] > 8000 || $check[1] > 8000) {
            return ['ok' => false, 'error' => 'Rasm hajmi juda katta (max 8000x8000)'];
        }

        if (!is_dir(UPLOAD_PATH)) @mkdir(UPLOAD_PATH, 0755, true);
        $name = self::sanitize_filename($prefix) . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        $dest = UPLOAD_PATH . '/' . $name;

        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            return ['ok' => false, 'error' => 'Faylni saqlashda xatolik'];
        }
        @chmod($dest, 0644);

        return [
            'ok'   => true,
            'path' => $dest,
            'url'  => UPLOAD_URL . '/' . $name,
            'mime' => $mime,
        ];
    }

    public static function sanitize_filename(string $name): string {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        return substr($name, 0, 50);
    }

    // ============================================================
    // Security headers
    // ============================================================

    public static function send_security_headers(): void {
        if (headers_sent()) return;
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=(), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        // X-XSS-Protection deprecated — to'liq o'chiramiz (Chrome guidance)
        header('X-XSS-Protection: 0');

        // HSTS faqat HTTPS'da
        if (defined('IS_HTTPS') && IS_HTTPS) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // CSP — unsafe-eval olib tashlandi. unsafe-inline'ni keyingi
        // versiyada nonce'ga ko'chiramiz; hozircha mavjud inline scriptlar bor.
        $csp = "default-src 'self'; "
             . "script-src 'self' 'unsafe-inline' https://accounts.google.com https://www.gstatic.com; "
             . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
             . "font-src 'self' https://fonts.gstatic.com data:; "
             . "img-src 'self' data: https:; "
             . "connect-src 'self' https://oauth2.googleapis.com; "
             . "frame-src 'self' https://www.google.com https://accounts.google.com; "
             . "frame-ancestors 'self'; "
             . "form-action 'self'; "
             . "base-uri 'self'; "
             . "object-src 'none'; "
             . "upgrade-insecure-requests";
        header("Content-Security-Policy: $csp");
    }

    // ============================================================
    // Helper: input sanitization
    // ============================================================

    public static function clean(string $str, int $maxLen = 255): string {
        $str = trim($str);
        $str = str_replace(["\0", "\r"], '', $str);
        return mb_substr($str, 0, $maxLen);
    }

    public static function valid_email(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function valid_phone(string $phone): bool {
        $digits = preg_replace('/\D/', '', $phone);
        return strlen($digits) >= 9 && strlen($digits) <= 15;
    }

    public static function normalize_phone(string $phone): string {
        $d = preg_replace('/\D/', '', $phone);
        if (strlen($d) === 9) $d = '998' . $d;
        return $d;
    }

    // ============================================================
    // Session security
    // ============================================================

    public static function session_regenerate(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function session_fingerprint_check(): bool {
        // User-Agent va IP'ning birinchi 3 oktetidan fingerprint quramiz
        // (mobile network IP almashishi mumkin — to'liq IP juda qattiq)
        $ip = self::client_ip();
        $ipParts = explode('.', $ip);
        $ipPrefix = count($ipParts) === 4 ? implode('.', array_slice($ipParts, 0, 3)) : $ip;
        $current = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . $ipPrefix);
        if (empty($_SESSION['fingerprint'])) {
            $_SESSION['fingerprint'] = $current;
            return true;
        }
        return hash_equals($_SESSION['fingerprint'], $current);
    }

    /**
     * Sessiya fingerprint'i mos kelmasa, foydalanuvchini tizimdan chiqaramiz.
     * is_logged_in() bo'lganda chaqirilsa kifoya.
     */
    public static function enforce_session_fingerprint(): void {
        if (empty($_SESSION['user_id'])) return;
        if (!self::session_fingerprint_check()) {
            self::audit('session_hijack_detected', 'Fingerprint mismatch', 'warning', $_SESSION['user_id'] ?? null);
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
        }
    }

    // ============================================================
    // JSON-based safe cache (unserialize O'RNIGA)
    // ============================================================

    public static function cache_read(string $name, int $ttl = 600) {
        $f = __DIR__ . '/../cache/data/' . preg_replace('/[^a-z0-9_]/i', '_', $name) . '.json';
        if (!is_file($f)) return null;
        if ((time() - filemtime($f)) > $ttl) return null;
        $raw = @file_get_contents($f);
        if (!$raw) return null;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    public static function cache_write(string $name, $value): bool {
        $dir = __DIR__ . '/../cache/data';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $f = $dir . '/' . preg_replace('/[^a-z0-9_]/i', '_', $name) . '.json';
        return (bool)@file_put_contents($f, json_encode($value, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    // ============================================================
    // Signed URL token (invoice'lar va boshqa public ID'lar uchun)
    // ============================================================

    /**
     * HMAC-SHA256 asosida signed token yaratadi. Brute-force imkoni yo'q.
     */
    public static function sign_token(string $payload): string {
        $secret = self::secret_key();
        return hash_hmac('sha256', $payload, $secret);
    }

    public static function verify_token(string $payload, string $token): bool {
        $expected = self::sign_token($payload);
        return hash_equals($expected, $token);
    }

    /**
     * APP_KEY — .env'dan o'qiydi yoki cache/data/app.key faylda saqlaydi.
     */
    public static function secret_key(): string {
        static $key = null;
        if ($key !== null) return $key;
        if (!empty($_ENV['APP_KEY'])) {
            $key = $_ENV['APP_KEY'];
            return $key;
        }
        $f = __DIR__ . '/../cache/data/app.key';
        if (is_file($f)) {
            $key = trim(@file_get_contents($f));
            if (strlen($key) >= 32) return $key;
        }
        // Yangi yaratamiz
        @mkdir(dirname($f), 0755, true);
        $key = bin2hex(random_bytes(32));
        @file_put_contents($f, $key, LOCK_EX);
        @chmod($f, 0600);
        return $key;
    }
}

// ============================================================
// Convenience aliases
// ============================================================
function csrf_token(): string  { return Security::csrf_token(); }
function csrf_field(): string  { return Security::csrf_field(); }
function csrf_check(): bool    { return Security::csrf_check(); }
function csrf_require(): void  { Security::csrf_require(); }
function audit(string $a, string $d = '', string $l = 'info'): void { Security::audit($a, $d, $l); }
function client_ip(): string   { return Security::client_ip(); }

// Auto: security headers + session fingerprint check + IP ban check
Security::send_security_headers();
Security::enforce_session_fingerprint();
if (Security::is_ip_banned()) {
    http_response_code(403);
    die('<h1>403 — Forbidden</h1><p>Sizning IP manzilingiz vaqtincha bloklangan.</p>');
}
