<?php
/**
 * Xavfsizlik moduli — CSRF, rate limit, audit log, secure upload, password policy
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

    public static function csrf_check(?string $token = null): bool {
        $token = $token ?? ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($token) || empty($_SESSION['csrf_token'])) return false;
        if ((time() - ($_SESSION['csrf_token_time'] ?? 0)) > 7200) return false;
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function csrf_require(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !self::csrf_check()) {
            http_response_code(419);
            die('<h1>419 — CSRF token noto\'g\'ri</h1><p>Sahifani yangilang va qayta urinib ko\'ring.</p>');
        }
    }

    // ============================================================
    // Rate limiting (login, register, contact uchun)
    // ============================================================

    /**
     * @return array{allowed: bool, remaining: int, reset_at: int}
     */
    public static function rate_limit(string $key, int $max = 5, int $window = 900): array {
        $cache_dir = __DIR__ . '/../cache/ratelimit';
        if (!is_dir($cache_dir)) @mkdir($cache_dir, 0755, true);
        $file = $cache_dir . '/' . md5($key) . '.json';

        $now = time();
        $data = ['count' => 0, 'first_at' => $now];
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $existing = $raw ? json_decode($raw, true) : null;
            if (is_array($existing) && ($now - $existing['first_at']) < $window) {
                $data = $existing;
            }
        }

        $data['count']++;
        @file_put_contents($file, json_encode($data));

        $allowed = $data['count'] <= $max;
        return [
            'allowed'   => $allowed,
            'remaining' => max(0, $max - $data['count']),
            'reset_at'  => $data['first_at'] + $window,
        ];
    }

    public static function client_ip(): string {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
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
            @file_put_contents($f, json_encode($list));
            return false;
        }
        return true;
    }

    public static function ban_ip(string $ip, int $minutes = 60): void {
        $f = __DIR__ . '/../cache/banned_ips.json';
        if (!is_dir(dirname($f))) @mkdir(dirname($f), 0755, true);
        $list = is_file($f) ? (json_decode(@file_get_contents($f), true) ?: []) : [];
        $list[$ip] = time() + ($minutes * 60);
        @file_put_contents($f, json_encode($list));
    }

    // ============================================================
    // Audit log (DB ga yozish)
    // ============================================================

    public static function audit(string $action, string $description = '', string $level = 'info', ?int $userId = null): void {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        db()->execute(
            "INSERT INTO logs (user_id, action, description, ip_address, user_agent, level)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $userId,
                $action,
                $description,
                self::client_ip(),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
                $level,
            ]
        );
    }

    // ============================================================
    // Parol siyosati (Password policy)
    // ============================================================

    /**
     * @return array{ok: bool, errors: array<string>, score: int}  // score 0-4
     */
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
        if (preg_match('/\d/', $password)) {
            $score++;
        }
        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $score++;
        }
        // Eng keng tarqalgan parollar
        $common = ['password','12345678','qwerty123','admin123','password123','iloveyou','welcome1'];
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
        // Frontend uchun JS+CSS — formaga bir marta qo'shiladi
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
    // Secure file upload
    // ============================================================

    /**
     * @return array{ok: bool, path?: string, url?: string, error?: string}
     */
    public static function upload_image(array $file, string $prefix = 'img', int $maxSize = 5242880): array {
        if (!isset($file['tmp_name']) || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Fayl yuklanmadi'];
        }
        if ($file['size'] > $maxSize) {
            return ['ok' => false, 'error' => 'Fayl hajmi katta (max ' . round($maxSize/1024/1024, 1) . ' MB)'];
        }

        // MIME type tekshirish (extension'ga ishonmaymiz)
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
        $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');
        if ($finfo) finfo_close($finfo);

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'image/svg+xml' => 'svg',
        ];
        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'error' => 'Faqat rasmlar ruxsat etiladi (jpg, png, webp, gif, svg)'];
        }

        // Image validity check (SVG dan tashqari)
        if ($mime !== 'image/svg+xml') {
            $check = @getimagesize($file['tmp_name']);
            if ($check === false) {
                return ['ok' => false, 'error' => 'Fayl haqiqiy rasm emas'];
            }
        } else {
            // SVG ichida script bo'lmasligi kerak
            $svg = @file_get_contents($file['tmp_name']);
            if (preg_match('/<script|onload=|onerror=|javascript:/i', $svg)) {
                return ['ok' => false, 'error' => 'Xavfli SVG'];
            }
        }

        if (!is_dir(UPLOAD_PATH)) @mkdir(UPLOAD_PATH, 0755, true);
        $name = self::sanitize_filename($prefix) . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        $dest = UPLOAD_PATH . '/' . $name;

        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            return ['ok' => false, 'error' => 'Faylni saqlashda xatolik'];
        }

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
    // Security headers (response yuborilishidan oldin)
    // ============================================================

    public static function send_security_headers(): void {
        if (headers_sent()) return;
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        // CSP — frame-ancestors clickjacking himoyasi
        $csp = "default-src 'self'; "
             . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google.com https://www.gstatic.com; "
             . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
             . "font-src 'self' https://fonts.gstatic.com data:; "
             . "img-src 'self' data: https:; "
             . "connect-src 'self'; "
             . "frame-src 'self' https://www.google.com; "
             . "frame-ancestors 'self'";
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
        $current = md5(($_SERVER['HTTP_USER_AGENT'] ?? '') . self::client_ip());
        if (empty($_SESSION['fingerprint'])) {
            $_SESSION['fingerprint'] = $current;
            return true;
        }
        return $_SESSION['fingerprint'] === $current;
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

// Auto-send security headers
Security::send_security_headers();
