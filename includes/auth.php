<?php
/**
 * Foydalanuvchi autentifikatsiya logikasi (security.php bilan integratsiya)
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';

class Auth {

    /** Email yoki telefon orqali kirish */
    public static function login(string $login, string $password, bool $remember = false): array {
        $login = Security::clean($login, 100);
        if (empty($login) || empty($password)) {
            return ['ok' => false, 'msg' => t('fill_required')];
        }

        // Rate limit (IP + login bo'yicha)
        $rl = Security::rate_limit('login_' . Security::client_ip(), 8, 900);
        if (!$rl['allowed']) {
            audit('login_blocked', "Rate limit oshib ketdi: $login", 'warning');
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
            audit('login_failed', "Noma'lum login: $login", 'warning');
            return ['ok' => false, 'msg' => t('user_not_found')];
        }
        if ($user['status'] !== 'active') {
            audit('login_blocked', "Akkaunt bloklangan: {$user['email']}", 'warning', $user['id']);
            return ['ok' => false, 'msg' => t('account_blocked')];
        }
        if (!password_verify($password, $user['password']) && !self::demoPasswordCheck($user, $password)) {
            audit('login_failed', "Noto'g'ri parol: {$user['email']}", 'warning', $user['id']);
            return ['ok' => false, 'msg' => t('wrong_password')];
        }

        // Muvaffaqiyatli kirish
        Security::session_regenerate();
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['lang']      = $user['language'] ?? 'uz_latin';

        if ($remember) {
            setcookie('vp_remember', $user['id'], time() + 60*60*24*30, '/', '', false, true);
        }

        db()->execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
        audit('login', "Foydalanuvchi tizimga kirdi", 'info', $user['id']);

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

        // Rate limit
        $rl = Security::rate_limit('register_' . Security::client_ip(), 5, 3600);
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

        // Referer bo'lsa
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

        // Referral bonus
        if ($referrerId) {
            db()->execute(
                "INSERT INTO referrals (referrer_id, referred_id, bonus_amount, status) VALUES (?,?,?,'pending')",
                [$referrerId, $id, 5000]
            );
        }

        Security::session_regenerate();
        $_SESSION['user_id']   = $id;
        $_SESSION['user_role'] = 'user';

        audit('register', "Yangi foydalanuvchi ro'yxatdan o'tdi", 'info', $id);
        return ['ok' => true, 'redirect' => '/user/'];
    }

    public static function logout(): void {
        if (is_logged_in()) audit('logout', "Foydalanuvchi tizimdan chiqdi");
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
        audit('password_changed', '', 'info', $userId);
        return ['ok' => true, 'msg' => t('updated_success')];
    }

    /** Parolni tiklash kodini yaratish */
    public static function create_reset_code(string $login): array {
        $user = db()->fetch("SELECT * FROM users WHERE email = ? OR phone = ?",
            [$login, Security::normalize_phone($login)]);
        if (!$user) return ['ok' => false, 'msg' => t('user_not_found')];

        $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['reset_code']   = password_hash($code, PASSWORD_DEFAULT);
        $_SESSION['reset_user']   = $user['id'];
        $_SESSION['reset_expire'] = time() + 600; // 10 daqiqa

        audit('reset_code_sent', "Reset code: $login", 'info', $user['id']);
        // Real loyihada SMS / email orqali yuboriladi
        return ['ok' => true, 'code' => $code, 'msg' => 'Tiklash kodi yuborildi'];
    }

    public static function verify_reset_code(string $code): bool {
        if (empty($_SESSION['reset_code']) || empty($_SESSION['reset_expire'])) return false;
        if (time() > $_SESSION['reset_expire']) return false;
        return password_verify($code, $_SESSION['reset_code']);
    }

    public static function reset_password(string $code, string $newPass, string $newPass2): array {
        if (!self::verify_reset_code($code)) {
            return ['ok' => false, 'msg' => 'Tiklash kodi noto\'g\'ri yoki muddati o\'tgan'];
        }
        $check = Security::validate_password($newPass);
        if (!$check['ok']) return ['ok' => false, 'msg' => $check['errors'][0] ?? t('password_min')];
        if ($newPass !== $newPass2) return ['ok' => false, 'msg' => t('passwords_dont_match')];

        $userId = $_SESSION['reset_user'];
        db()->execute("UPDATE users SET password = ? WHERE id = ?",
            [password_hash($newPass, PASSWORD_DEFAULT), $userId]);

        unset($_SESSION['reset_code'], $_SESSION['reset_user'], $_SESSION['reset_expire']);
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

    /** Demo: seed paroli bcrypt mos kelmasa, oddiy demo parollarni qabul qilamiz */
    private static function demoPasswordCheck(array $user, string $password): bool {
        $demo = [
            'admin@vatanparvar.uz' => 'admin123',
            'dev@vatanparvar.uz'   => 'dev123',
            'user@vatanparvar.uz'  => 'user123',
        ];
        if (isset($demo[$user['email']]) && $demo[$user['email']] === $password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            db()->execute("UPDATE users SET password = ? WHERE id = ?", [$hash, $user['id']]);
            return true;
        }
        return false;
    }

    /** Backwards compat */
    public static function log(?int $userId, string $action, string $desc = '', string $level = 'info'): void {
        Security::audit($action, $desc, $level, $userId);
    }
}
