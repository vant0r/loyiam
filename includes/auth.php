<?php
/**
 * Foydalanuvchi autentifikatsiya logikasi (security.php bilan integratsiya)
 *
 * v3.0 — Audit fixes:
 *   - demoPasswordCheck() OLIB TASHLANDI (CRITICAL backdoor edi)
 *   - vp_remember cookie'da random token + DB lookup (oldin raw user_id edi)
 *   - Per-account rate limit (login_user_*) IP-only'ga qo'shimcha
 *   - reset_code endi MASSIVDA QAYTARMAYDI (faqat audit log)
 *   - Login/register'da session fingerprint o'rnatadi
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/notifications.php';

class Auth {

    /** Email yoki telefon orqali kirish */
    public static function login(string $login, string $password, bool $remember = false): array {
        $login = Security::clean($login, 100);
        if (empty($login) || empty($password)) {
            return ['ok' => false, 'msg' => t('fill_required')];
        }

        // Rate limit (IP — qattiq, va per-login — yumshoqroq)
        $ipRl = Security::rate_limit('login_ip_' . Security::client_ip(), 12, 900);
        if (!$ipRl['allowed']) {
            audit('login_blocked', "IP rate limit: " . Security::client_ip(), 'warning');
            return ['ok' => false, 'msg' => t('too_many_attempts')];
        }

        // Per-account rate limit (botnet bilan ham buzilmasin)
        $loginNorm = strtolower(trim($login));
        $accRl = Security::rate_limit('login_user_' . hash('sha256', $loginNorm), 6, 1800);
        if (!$accRl['allowed']) {
            audit('login_blocked', "Account rate limit: $loginNorm", 'warning');
            // Generic xabar — username enumeration oldini olish
            return ['ok' => false, 'msg' => t('too_many_attempts')];
        }

        $user = db()->fetch(
            "SELECT * FROM users WHERE (email = ? OR phone = ?) LIMIT 1",
            [$login, Security::normalize_phone($login)]
        );
        if (!$user) {
            $user = db()->fetch("SELECT * FROM users WHERE email = ? OR phone = ?", [$login, $login]);
        }
        if (!$user) {
            audit('login_failed', "Noma'lum login", 'warning');
            // Constant-time'ish: timing attack oldini olish uchun fake hash check
            password_verify($password, '$2y$12$' . str_repeat('a', 53));
            return ['ok' => false, 'msg' => t('user_not_found')];
        }
        if ($user['status'] !== 'active') {
            audit('login_blocked', "Akkaunt bloklangan", 'warning', $user['id']);
            return ['ok' => false, 'msg' => t('account_blocked')];
        }
        if (!password_verify($password, $user['password'])) {
            audit('login_failed', "Noto'g'ri parol", 'warning', $user['id']);
            return ['ok' => false, 'msg' => t('wrong_password')];
        }

        // Muvaffaqiyatli kirish
        Security::session_regenerate();
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['lang']      = $user['language'] ?? 'uz_latin';
        $_SESSION['login_time']= time();

        // Session fingerprint o'rnatamiz
        unset($_SESSION['fingerprint']);
        Security::session_fingerprint_check();

        // Remember-me cookie — XAVFSIZ versiya:
        // user_id+random_token saqlaymiz, token DB'da hashlangan holda turadi
        if ($remember) {
            self::set_remember_cookie((int)$user['id']);
        }

        db()->execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
        audit('login', "Foydalanuvchi tizimga kirdi", 'info', $user['id']);

        // Bcrypt cost yangilangan bo'lsa, hash'ni yangilaymiz
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            db()->execute("UPDATE users SET password = ? WHERE id = ?",
                [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        return ['ok' => true, 'user' => $user, 'redirect' => self::redirectByRole($user['role'])];
    }

    /** Ro'yxatdan o'tish */
    public static function register(array $data): array {
        $first = Security::clean($data['first_name'] ?? '', 50);
        $last  = Security::clean($data['last_name'] ?? '', 50);
        $email = Security::clean($data['email'] ?? '', 100);
        $phone = Security::clean($data['phone'] ?? '', 20);
        $pass  = $data['password']  ?? '';
        $pass2 = $data['password2'] ?? '';
        $agree = !empty($data['agree']);
        $referral = Security::clean($data['referral'] ?? '', 20);

        // Rate limit — qattiq (mass-account farming oldini olish)
        $rl = Security::rate_limit('register_' . Security::client_ip(), 3, 3600);
        if (!$rl['allowed']) {
            return ['ok' => false, 'msg' => t('too_many_attempts')];
        }

        if (!$first || !$last || (!$email && !$phone)) {
            return ['ok' => false, 'msg' => t('fill_required')];
        }
        if ($email && !Security::valid_email($email)) {
            return ['ok' => false, 'msg' => t('invalid_email')];
        }
        if ($phone && !Security::valid_phone($phone)) {
            return ['ok' => false, 'msg' => t('invalid_phone')];
        }
        $pwCheck = Security::validate_password($pass);
        if (!$pwCheck['ok']) {
            return ['ok' => false, 'msg' => $pwCheck['errors'][0] ?? t('password_min')];
        }
        if ($pass !== $pass2) {
            return ['ok' => false, 'msg' => t('passwords_dont_match')];
        }
        if (!$agree) {
            return ['ok' => false, 'msg' => t('agree_terms')];
        }

        $phoneNorm = $phone ? Security::normalize_phone($phone) : null;
        $exists = db()->fetch("SELECT id FROM users WHERE email = ? OR phone = ?", [$email, $phoneNorm]);
        if ($exists) {
            return ['ok' => false, 'msg' => t('email_exists')];
        }

        $referrerId = null;
        if ($referral) {
            $r = db()->fetch("SELECT id FROM users WHERE referral_code = ?", [$referral]);
            if ($r) $referrerId = $r['id'];
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));

        $ok = db()->execute(
            "INSERT INTO users (first_name,last_name,email,phone,password,role,status,referral_code,referred_by)
             VALUES (?,?,?,?,?,'user','active',?,?)",
            [$first, $last, $email ?: null, $phoneNorm, $hash, $code, $referrerId]
        );
        if (!$ok) return ['ok' => false, 'msg' => t('register_error')];

        $id = db()->lastInsertId();

        if ($referrerId) {
            db()->execute(
                "INSERT INTO referrals (referrer_id, referred_id, bonus_amount, status) VALUES (?,?,?,'pending')",
                [$referrerId, $id, 5000]
            );
        }

        Security::session_regenerate();
        $_SESSION['user_id']   = $id;
        $_SESSION['user_role'] = 'user';
        $_SESSION['login_time']= time();
        unset($_SESSION['fingerprint']);
        Security::session_fingerprint_check();

        audit('register', "Yangi foydalanuvchi", 'info', $id);

        Notify::send($id, 'welcome',
            "Xush kelibsiz, " . htmlspecialchars($first) . "!",
            "VatanParvar Yaypan platformasiga muvaffaqiyatli qo'shildingiz. Birinchi testingizni boshlang!",
            ['link' => '/user/testlar.php', 'icon' => 'star']);

        return ['ok' => true, 'redirect' => '/user/'];
    }

    public static function logout(): void {
        if (is_logged_in()) audit('logout', "Tizimdan chiqdi");
        self::clear_remember_cookie();
        do_logout();
    }

    /** Parolni o'zgartirish (profil sahifasi uchun) */
    public static function change_password(int $userId, string $oldPass, string $newPass, string $newPass2): array {
        $user = db()->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        if (!$user) return ['ok' => false, 'msg' => t('user_not_found')];

        if (!password_verify($oldPass, $user['password'])) {
            return ['ok' => false, 'msg' => "Eski parol noto'g'ri"];
        }
        $check = Security::validate_password($newPass);
        if (!$check['ok']) return ['ok' => false, 'msg' => $check['errors'][0] ?? t('password_min')];
        if ($newPass !== $newPass2) return ['ok' => false, 'msg' => t('passwords_dont_match')];

        db()->execute("UPDATE users SET password = ? WHERE id = ?",
            [password_hash($newPass, PASSWORD_DEFAULT), $userId]);

        // Parol o'zgargach barcha remember tokenlarni invalidate qilamiz
        self::revoke_all_remember_tokens($userId);
        Security::session_regenerate();

        audit('password_changed', '', 'info', $userId);
        return ['ok' => true, 'msg' => t('updated_success')];
    }

    /**
     * Parolni tiklash kodini yaratish — XAVFSIZ versiya:
     * Kod TIZIM TASHIDA yuboriladi (SMS/email). Response'ga qaytarmaymiz.
     * Demo kod faqat APP_DEBUG=1 bo'lganda ko'rinadi.
     */
    public static function create_reset_code(string $login): array {
        $rl = Security::rate_limit('reset_' . Security::client_ip(), 3, 1800);
        if (!$rl['allowed']) {
            return ['ok' => false, 'msg' => t('too_many_attempts')];
        }

        // Per-account rate limit
        $accRl = Security::rate_limit('reset_user_' . hash('sha256', strtolower($login)), 3, 3600);
        if (!$accRl['allowed']) {
            return ['ok' => false, 'msg' => t('too_many_attempts')];
        }

        $user = db()->fetch("SELECT * FROM users WHERE email = ? OR phone = ?",
            [$login, Security::normalize_phone($login)]);

        // Generic javob — username enumeration oldini olish
        // (foydalanuvchi mavjudmi yoki yo'qmi — bilib bo'lmaydi)
        $genericMsg = "Agar bu akkaunt mavjud bo'lsa, tiklash kodi SMS yoki email orqali yuboriladi";

        if (!$user) {
            audit('reset_attempted_unknown', mb_substr($login, 0, 32), 'warning');
            return ['ok' => true, 'msg' => $genericMsg];
        }

        $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['reset_code']   = password_hash($code, PASSWORD_DEFAULT);
        $_SESSION['reset_user']   = $user['id'];
        $_SESSION['reset_expire'] = time() + 600;
        $_SESSION['reset_attempts'] = 0;

        audit('reset_code_sent', "User #" . $user['id'], 'info', $user['id']);

        // SMS/Email integratsiyasi mavjud bo'lsa shu yerda chaqirish kerak.
        // Hozircha kod faqat APP_DEBUG=1 bo'lganda response'ga qaytadi.
        $resp = ['ok' => true, 'msg' => $genericMsg];
        if (defined('APP_DEBUG') && APP_DEBUG) {
            $resp['debug_code'] = $code; // FAQAT debug rejimida
        }
        return $resp;
    }

    public static function verify_reset_code(string $code): bool {
        if (empty($_SESSION['reset_code']) || empty($_SESSION['reset_expire'])) return false;
        if (time() > $_SESSION['reset_expire']) return false;
        // Brute-force qarshi: 5 marta noto'g'ri urinishdan keyin invalidate
        if (!isset($_SESSION['reset_attempts'])) $_SESSION['reset_attempts'] = 0;
        $_SESSION['reset_attempts']++;
        if ($_SESSION['reset_attempts'] > 5) {
            unset($_SESSION['reset_code'], $_SESSION['reset_user'], $_SESSION['reset_expire'], $_SESSION['reset_attempts']);
            return false;
        }
        return password_verify($code, $_SESSION['reset_code']);
    }

    public static function reset_password(string $code, string $newPass, string $newPass2): array {
        if (!self::verify_reset_code($code)) {
            return ['ok' => false, 'msg' => 'Tiklash kodi noto\'g\'ri yoki muddati o\'tgan'];
        }
        $check = Security::validate_password($newPass);
        if (!$check['ok']) return ['ok' => false, 'msg' => $check['errors'][0] ?? t('password_min')];
        if ($newPass !== $newPass2) return ['ok' => false, 'msg' => t('passwords_dont_match')];

        $userId = (int)$_SESSION['reset_user'];
        db()->execute("UPDATE users SET password = ? WHERE id = ?",
            [password_hash($newPass, PASSWORD_DEFAULT), $userId]);

        unset($_SESSION['reset_code'], $_SESSION['reset_user'], $_SESSION['reset_expire'], $_SESSION['reset_attempts']);
        self::revoke_all_remember_tokens($userId);

        audit('password_reset', '', 'info', $userId);
        return ['ok' => true, 'msg' => 'Parol yangilandi. Endi kirishingiz mumkin.'];
    }

    private static function redirectByRole(string $role): string {
        return match($role) {
            'admin'     => '/admin/',
            'developer' => '/developer/',
            default     => '/user/',
        };
    }

    /** Backwards compat */
    public static function log(?int $userId, string $action, string $desc = '', string $level = 'info'): void {
        Security::audit($action, $desc, $level, $userId);
    }

    // ============================================================
    // Remember-me cookie (XAVFSIZ — random token + DB hash)
    // ============================================================

    private static function set_remember_cookie(int $userId): void {
        $token = bin2hex(random_bytes(32));   // 64 belgi, 256-bit
        $tokenHash = hash('sha256', $token);
        $expires = time() + 60 * 60 * 24 * 30; // 30 kun

        // remember_tokens jadvali (migration tomonidan yaratiladi)
        try {
            db()->execute(
                "INSERT INTO remember_tokens (user_id, token_hash, expires_at, user_agent, ip_address)
                 VALUES (?, ?, FROM_UNIXTIME(?), ?, ?)",
                [$userId, $tokenHash, $expires,
                 substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
                 Security::client_ip()]
            );
        } catch (\Throwable $e) {
            // Jadval mavjud bo'lmasa, cookie qo'ymaymiz (xavfsiz fail)
            return;
        }

        // Cookie: "user_id|token"
        $cookieValue = $userId . '|' . $token;
        setcookie('vp_remember', $cookieValue, [
            'expires'  => $expires,
            'path'     => '/',
            'domain'   => '',
            'secure'   => defined('IS_HTTPS') && IS_HTTPS,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clear_remember_cookie(): void {
        if (!empty($_COOKIE['vp_remember'])) {
            $parts = explode('|', $_COOKIE['vp_remember'], 2);
            if (count($parts) === 2) {
                $hash = hash('sha256', $parts[1]);
                try {
                    db()->execute("DELETE FROM remember_tokens WHERE token_hash = ?", [$hash]);
                } catch (\Throwable $e) {}
            }
            setcookie('vp_remember', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => defined('IS_HTTPS') && IS_HTTPS,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function revoke_all_remember_tokens(int $userId): void {
        try {
            db()->execute("DELETE FROM remember_tokens WHERE user_id = ?", [$userId]);
        } catch (\Throwable $e) {}
    }

    /** Sahifa yuklanganda chaqiriladi — agar cookie mavjud va sessiya yo'q bo'lsa */
    public static function try_remember_login(): void {
        if (is_logged_in()) return;
        if (empty($_COOKIE['vp_remember'])) return;

        $parts = explode('|', $_COOKIE['vp_remember'], 2);
        if (count($parts) !== 2) {
            self::clear_remember_cookie();
            return;
        }
        [$uid, $token] = $parts;
        $uid = (int)$uid;
        if (!$uid || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            self::clear_remember_cookie();
            return;
        }
        $hash = hash('sha256', $token);

        try {
            $row = db()->fetch(
                "SELECT t.*, u.id user_id, u.role, u.language, u.status
                 FROM remember_tokens t JOIN users u ON u.id = t.user_id
                 WHERE t.user_id = ? AND t.token_hash = ? AND t.expires_at > NOW() LIMIT 1",
                [$uid, $hash]
            );
        } catch (\Throwable $e) {
            return;
        }

        if (!$row || $row['status'] !== 'active') {
            self::clear_remember_cookie();
            return;
        }

        // Token rotation — har bir foydalanishdan so'ng yangi token
        Security::session_regenerate();
        $_SESSION['user_id']   = $row['user_id'];
        $_SESSION['user_role'] = $row['role'];
        $_SESSION['lang']      = $row['language'] ?? 'uz_latin';
        $_SESSION['login_time']= time();
        unset($_SESSION['fingerprint']);
        Security::session_fingerprint_check();

        // Eski tokenni o'chirib, yangisini qo'yamiz (rotation)
        try {
            db()->execute("DELETE FROM remember_tokens WHERE id = ?", [$row['id']]);
        } catch (\Throwable $e) {}
        self::set_remember_cookie((int)$row['user_id']);

        audit('login_via_remember', '', 'info', $row['user_id']);
    }
}

// Avto: agar cookie bilan kirish mumkin bo'lsa, kirib qo'yamiz
Auth::try_remember_login();
