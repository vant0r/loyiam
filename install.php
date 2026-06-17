<?php
/**
 * VatanParvar Yaypan — O'rnatish ustasi (Installation Wizard) v2.2
 *
 * Bosqichlar:
 *   1. Talablar — PHP versiyasi, kengaytmalar, fayl ruxsatlari
 *   2. Database — ulanish + smart SQL import
 *   3. Admin — birinchi admin akkaunt
 *   4. Sayt sozlamalari
 *   5. Tugadi
 *
 * Yangiliklar v2.2:
 *   - Smart SQL parser (yagona ; bo'yicha to'g'ri ajratish)
 *   - Real error reporting (bekitilmagan)
 *   - Partial recovery (qayta ishga tushirish)
 *   - DB jadvallar tekshiruvi
 *   - Hosting compatibility mode
 */

@ini_set('display_errors', 0); // Foydalanuvchiga raw PHP xato chiqmasin
error_reporting(E_ALL);
@set_time_limit(120);

if (session_status() === PHP_SESSION_NONE) session_start();
define('INSTALLER_RUNNING', true);

define('INSTALL_BASE', __DIR__);
define('INSTALL_LOCK', INSTALL_BASE . '/.installed');
define('CONFIG_FILE',  INSTALL_BASE . '/includes/config.php');
define('SQL_FILE',     INSTALL_BASE . '/sql/database.sql');

// Required tables list
$REQUIRED_TABLES = [
    'users','tariffs','tickets','questions','answers','test_attempts',
    'test_answers','payments','blog_posts','reviews','contact_messages',
    'referrals','settings','logs',
];

// ==========================================================
// AGAR ALLAQACHON O'RNATILGAN BO'LSA
// ==========================================================
$alreadyInstalled = is_file(INSTALL_LOCK);
if ($alreadyInstalled && !isset($_GET['force'])) {
    show_already_installed();
    exit;
}

$step    = max(1, min(5, (int)($_GET['step'] ?? 1)));
$error   = '';
$details = []; // {ok, errors[], created[], skipped[]}

// POST handlerlar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ((int)($_POST['step'] ?? 0)) {
            case 2: handle_database();   break;
            case 3: handle_admin();      break;
            case 4: handle_settings();   break;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $details = $GLOBALS['__install_details'] ?? [];
    }
}

// ==========================================================
// DATABASE HANDLER (yaxshilangan)
// ==========================================================
function handle_database(): void {
    global $REQUIRED_TABLES;

    $host = trim($_POST['db_host'] ?? 'localhost');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';

    if (!$host || !$name || !$user) {
        throw new RuntimeException("Host, DB nomi va foydalanuvchi nomi to'ldirilishi shart");
    }

    // 1) DB ga ulanish (avval yaratishga harakat qilamiz)
    $pdo = null;
    try {
        // Avval to'g'ridan-to'g'ri ulanishga urinamiz
        $pdo = new PDO(
            "mysql:host=$host;dbname=$name;charset=utf8mb4",
            $user, $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ]
        );
    } catch (PDOException $e) {
        // DB mavjud emas — yaratishga harakat qilamiz
        try {
            $rootPdo = new PDO(
                "mysql:host=$host;charset=utf8mb4", $user, $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `$name`
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e2) {
            throw new RuntimeException(
                "DB ulanish yoki yaratish xatosi:\n• " . $e2->getMessage() .
                "\n\nIltimos, x10/cPanel'da:\n1. DB nomini tekshiring (odatda `username_dbname` formatida)\n" .
                "2. DB foydalanuvchini yarating va parolini to'g'ri kiriting\n" .
                "3. Foydalanuvchiga DB ga ALL PRIVILEGES bering"
            );
        }
    }

    if (!$pdo) throw new RuntimeException("DB ga ulanib bo'lmadi");

    // 2) SQL faylni o'qish
    if (!is_file(SQL_FILE)) {
        throw new RuntimeException("sql/database.sql fayli topilmadi: " . SQL_FILE);
    }
    $sql = file_get_contents(SQL_FILE);
    if ($sql === false) throw new RuntimeException("SQL faylni o'qib bo'lmadi");

    // 3) Smart SQL split
    $statements = smart_sql_split($sql);

    // 4) Har bir statement ni alohida bajaramiz
    $result = [
        'created'        => [],
        'inserted'       => 0,
        'skipped_exists' => 0,
        'errors'         => [],
        'total_executed' => 0,
    ];

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) continue;

        try {
            $pdo->exec($stmt);
            $result['total_executed']++;

            if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?(\w+)/i', $stmt, $m)) {
                $result['created'][] = $m[1];
            } elseif (stripos($stmt, 'INSERT') === 0) {
                $result['inserted']++;
            }
        } catch (PDOException $e) {
            $msg = $e->getMessage();

            // "Already exists" type xatolari — bekorish
            if (stripos($msg, 'already exists') !== false ||
                stripos($msg, "Duplicate entry") !== false ||
                stripos($msg, 'Duplicate column') !== false ||
                stripos($msg, 'Duplicate key name') !== false) {
                $result['skipped_exists']++;
                continue;
            }

            // Real xato — saqlaymiz
            $stmtPreview = substr($stmt, 0, 100) . (strlen($stmt) > 100 ? '...' : '');
            $result['errors'][] = [
                'preview' => $stmtPreview,
                'message' => $msg,
            ];
        }
    }

    // 5) Tekshiramiz: barcha kerakli jadvallar mavjudmi?
    $existingTables = [];
    foreach ($pdo->query("SHOW TABLES") as $row) {
        $existingTables[] = $row[0];
    }

    $missing = array_diff($REQUIRED_TABLES, $existingTables);
    $result['existing_tables'] = $existingTables;
    $result['missing_tables']  = array_values($missing);

    if (!empty($missing)) {
        $GLOBALS['__install_details'] = $result;
        throw new RuntimeException(
            "Quyidagi jadvallar yaratilmadi:\n• " . implode(", ", $missing) .
            "\n\nXatolar (" . count($result['errors']) . " ta):\n" .
            implode("\n", array_map(fn($e) => "• " . substr($e['message'], 0, 200), array_slice($result['errors'], 0, 5)))
        );
    }

    // 6) Konfiguratsiyani yangilaymiz
    if (!update_config_db($host, $name, $user, $pass)) {
        throw new RuntimeException(
            "DB jadvallar yaratildi, lekin includes/config.php faylini yangilab bo'lmadi.\n" .
            "Quyidagilarni qo'lda kiriting:\n" .
            "DB_HOST = $host\nDB_NAME = $name\nDB_USER = $user\nDB_PASS = " . str_repeat('*', strlen($pass))
        );
    }

    // Sessiyaga saqlaymiz
    $_SESSION['install'] = [
        'db_host' => $host, 'db_name' => $name,
        'db_user' => $user, 'db_pass' => $pass,
    ];

    // Agar bekitilmagan xatolar bo'lsa ham, jadvallar mavjud — flash ko'rsatamiz
    if (!empty($result['errors'])) {
        $_SESSION['install_warnings'] = "DB yaratildi, lekin " . count($result['errors']) .
            " ta INSERT/ALTER xatosi bor. Bu odatda o'rganish uchun zararsiz.";
    }

    header('Location: install.php?step=3');
    exit;
}

/**
 * Smart SQL splitter — strings ichidagi ; ni inobatga oladi
 */
function smart_sql_split(string $sql): array {
    // Comments olib tashlash (-- va /* */)
    $sql = preg_replace('/^\s*--[^\r\n]*/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    $statements = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $prev = $i > 0 ? $sql[$i-1] : '';

        if ($inString) {
            $current .= $ch;
            if ($ch === $stringChar && $prev !== '\\') {
                $inString = false;
            }
            continue;
        }

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $inString = true;
            $stringChar = $ch;
            $current .= $ch;
            continue;
        }

        if ($ch === ';') {
            $stmt = trim($current);
            if ($stmt !== '') $statements[] = $stmt;
            $current = '';
            continue;
        }

        $current .= $ch;
    }

    $stmt = trim($current);
    if ($stmt !== '') $statements[] = $stmt;

    return $statements;
}

// ==========================================================
// ADMIN HANDLER
// ==========================================================
function handle_admin(): void {
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (!$first || !$last || !$email || !$pass) {
        throw new RuntimeException("Barcha majburiy maydonlarni to'ldiring (yulduzcha bilan)");
    }
    if (strlen($pass) < 8) {
        throw new RuntimeException("Parol kamida 8 belgi bo'lishi kerak");
    }
    if ($pass !== $pass2) {
        throw new RuntimeException("Parollar mos kelmadi");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException("Email noto'g'ri formatda");
    }

    $pdo = get_pdo();
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

    // Agar shu email bilan user mavjud bo'lsa — yangilaymiz, bo'lmasa qo'shamiz
    $existing = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $existing->execute([$email]);
    $existingId = $existing->fetchColumn();

    if ($existingId) {
        $stmt = $pdo->prepare(
            "UPDATE users SET first_name=?, last_name=?, phone=?, password=?,
             role='admin', status='active' WHERE id=?"
        );
        $stmt->execute([$first, $last, $phone ?: null, $hash, $existingId]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO users (first_name, last_name, email, phone, password, role, status, referral_code)
             VALUES (?, ?, ?, ?, ?, 'admin', 'active', ?)"
        );
        $stmt->execute([$first, $last, $email, $phone ?: null, $hash, $code]);
    }

    $_SESSION['install']['admin_email'] = $email;
    header('Location: install.php?step=4');
    exit;
}

// ==========================================================
// SETTINGS HANDLER
// ==========================================================
function handle_settings(): void {
    $pdo = get_pdo();

    $kv = [
        'site_name'              => trim($_POST['site_name'] ?? 'VatanParvar Yaypan'),
        'site_phone'             => trim($_POST['site_phone'] ?? ''),
        'site_email'             => trim($_POST['site_email'] ?? ''),
        'site_address'           => trim($_POST['site_address'] ?? ''),
        'card_number'            => trim($_POST['card_number'] ?? ''),
        'card_holder'            => trim($_POST['card_holder'] ?? ''),
        'telegram_bot_token'     => trim($_POST['telegram_bot_token'] ?? ''),
        'telegram_admin_chat_id' => trim($_POST['telegram_admin_chat_id'] ?? ''),
    ];

    $stmt = $pdo->prepare(
        "INSERT INTO settings (setting_key, setting_value, setting_group)
         VALUES (?, ?, 'general')
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    foreach ($kv as $k => $v) {
        if ($v !== '') {
            try { $stmt->execute([$k, $v]); } catch (Throwable $e) { /* skip */ }
        }
    }

    // Cache'ni tozalash
    @unlink(INSTALL_BASE . '/cache/data/settings.cache');

    // Lock fayl
    @file_put_contents(INSTALL_LOCK, json_encode([
        'installed_at' => date('c'),
        'version'      => '2.2.0',
        'admin_email'  => $_SESSION['install']['admin_email'] ?? null,
        'php_version'  => PHP_VERSION,
    ]));
    @chmod(INSTALL_LOCK, 0644);

    header('Location: install.php?step=5');
    exit;
}

// ==========================================================
// HELPERS
// ==========================================================
function update_config_db(string $host, string $name, string $user, string $pass): bool {
    if (!is_file(CONFIG_FILE)) return false;
    if (!is_writable(CONFIG_FILE)) return false;

    $cfg = file_get_contents(CONFIG_FILE);
    if ($cfg === false) return false;

    $replacements = [
        '/define\(\s*\'DB_HOST\'\s*,\s*\'[^\']*\'\s*\);/' => "define('DB_HOST', " . var_export($host, true) . ");",
        '/define\(\s*\'DB_NAME\'\s*,\s*\'[^\']*\'\s*\);/' => "define('DB_NAME', " . var_export($name, true) . ");",
        '/define\(\s*\'DB_USER\'\s*,\s*\'[^\']*\'\s*\);/' => "define('DB_USER', " . var_export($user, true) . ");",
        '/define\(\s*\'DB_PASS\'\s*,\s*\'[^\']*\'\s*\);/' => "define('DB_PASS', " . var_export($pass, true) . ");",
    ];
    foreach ($replacements as $pat => $rep) {
        $cfg = preg_replace($pat, $rep, $cfg);
    }
    return file_put_contents(CONFIG_FILE, $cfg) !== false;
}

function get_pdo(): PDO {
    $i = $_SESSION['install'] ?? [];
    if (empty($i['db_host'])) {
        // Sessiya yo'qoldi — config.php dan o'qishga harakat
        if (is_file(CONFIG_FILE)) {
            $cfg = file_get_contents(CONFIG_FILE);
            if (preg_match("/'DB_HOST'\s*,\s*'([^']*)'/", $cfg, $h) &&
                preg_match("/'DB_NAME'\s*,\s*'([^']*)'/", $cfg, $n) &&
                preg_match("/'DB_USER'\s*,\s*'([^']*)'/", $cfg, $u) &&
                preg_match("/'DB_PASS'\s*,\s*'([^']*)'/", $cfg, $p)) {
                $i = [
                    'db_host' => $h[1], 'db_name' => $n[1],
                    'db_user' => $u[1], 'db_pass' => $p[1],
                ];
                $_SESSION['install'] = $i;
            }
        }
        if (empty($i['db_host'])) throw new RuntimeException("Avval DB sozlamalarini kiriting (Step 2)");
    }
    return new PDO(
        "mysql:host={$i['db_host']};dbname={$i['db_name']};charset=utf8mb4",
        $i['db_user'], $i['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

function check_requirements(): array {
    $php_ok    = version_compare(PHP_VERSION, '7.4.0', '>=');
    $php_8_ok  = version_compare(PHP_VERSION, '8.0.0', '>=');

    $checks = [
        ['PHP versiyasi 7.4+', PHP_VERSION, $php_ok, 'critical'],
        ['PHP 8.0+ (tavsiya)', PHP_VERSION, $php_8_ok, 'warning'],
    ];

    $exts = [
        'pdo' => 'critical', 'pdo_mysql' => 'critical',
        'mbstring' => 'critical', 'json' => 'critical',
        'session' => 'critical', 'fileinfo' => 'warning',
        'curl' => 'warning', 'openssl' => 'warning',
    ];
    foreach ($exts as $ext => $crit) {
        $loaded = extension_loaded($ext);
        $checks[] = ["$ext kengaytmasi", $loaded ? 'mavjud' : 'YO\'Q', $loaded, $crit];
    }

    $writable = [
        '/cache/data'          => INSTALL_BASE . '/cache/data',
        '/cache/ratelimit'     => INSTALL_BASE . '/cache/ratelimit',
        '/assets/uploads'      => INSTALL_BASE . '/assets/uploads',
        '/includes/config.php' => CONFIG_FILE,
    ];
    foreach ($writable as $label => $path) {
        if (!file_exists($path) && !str_contains($path, '.php')) {
            @mkdir($path, 0755, true);
        }
        $w = is_writable($path);
        $checks[] = ["$label yozilishi mumkin", $w ? 'OK' : 'YO\'Q', $w, 'critical'];
    }

    return $checks;
}

function show_already_installed(): void {
    render_layout('Allaqachon o\'rnatilgan',
        '<div class="card text-center" style="padding:48px 32px">
            <div class="install-icon success">✓</div>
            <h2>Loyiha allaqachon o\'rnatilgan</h2>
            <p class="text-soft">Qayta o\'rnatish uchun <code>.installed</code> faylini o\'chiring yoki URL\'ga <code>?force=1</code> qo\'shing.</p>
            <p class="text-soft" style="margin-top:14px;font-size:13px">⚠️ <strong>Xavfsizlik:</strong> Production muhitida <code>install.php</code> faylini o\'chirib tashlang!</p>
            <div class="actions" style="justify-content:center">
                <a href="/" class="btn btn-primary">Bosh sahifa</a>
                <a href="/login.php" class="btn btn-outline">Login</a>
                <a href="?force=1" class="btn btn-light">Qayta o\'rnatish</a>
            </div>
        </div>'
    );
}

// ==========================================================
// LAYOUT
// ==========================================================
function render_layout(string $title, string $body): void {
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#3B82F6">
<title><?= htmlspecialchars($title) ?> — VP O'rnatish</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:'Inter',-apple-system,sans-serif;background:#F8FAFC;color:#0F172A;
  min-height:100vh;line-height:1.6;padding:24px 16px;
  background-image:radial-gradient(ellipse at top right,rgba(59,130,246,.15),transparent 60%),
                   radial-gradient(ellipse at bottom left,rgba(168,85,247,.08),transparent 60%);
  background-attachment:fixed;-webkit-font-smoothing:antialiased}
.wrap{max-width:720px;margin:0 auto}
.brand{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:32px;
  font-size:18px;font-weight:800;color:#0F172A}
.brand-icon{width:48px;height:48px;background:linear-gradient(135deg,#3B82F6,#1E40AF);
  color:#fff;border-radius:14px;display:flex;align-items:center;justify-content:center;
  font-size:18px;font-weight:900;box-shadow:0 8px 24px rgba(59,130,246,.3)}
.brand small{display:block;font-size:11px;font-weight:500;color:#64748B;text-transform:uppercase;letter-spacing:.1em}
.steps{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;padding:0 8px}
.step{display:flex;flex-direction:column;align-items:center;gap:8px;flex:1;position:relative}
.step::after{content:'';position:absolute;top:18px;right:-50%;left:50%;height:2px;background:#E2E8F0;z-index:0}
.step:last-child::after{display:none}
.step.done::after{background:linear-gradient(90deg,#10B981,#10B981)}
.step-num{width:36px;height:36px;border-radius:50%;background:#fff;border:2px solid #E2E8F0;
  display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;
  color:#94A3B8;position:relative;z-index:1;transition:all .3s}
.step.active .step-num{background:#3B82F6;border-color:#3B82F6;color:#fff;box-shadow:0 4px 16px rgba(59,130,246,.3);
  animation:pulse 2s infinite}
.step.done .step-num{background:#10B981;border-color:#10B981;color:#fff}
.step-label{font-size:11px;font-weight:600;color:#94A3B8;text-align:center;display:none}
.step.active .step-label,.step.done .step-label{color:#0F172A}
@media (min-width:520px){.step-label{display:block}}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(59,130,246,.4)}50%{box-shadow:0 0 0 8px rgba(59,130,246,0)}}
.card{background:#fff;border-radius:20px;padding:36px;box-shadow:0 24px 60px rgba(15,23,42,.08);
  border:1px solid rgba(226,232,240,.5);animation:slideUp .5s cubic-bezier(.22,1,.36,1) both}
@keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
h1,h2{font-weight:800;letter-spacing:-.02em;line-height:1.2;margin-bottom:8px}
h1{font-size:28px}h2{font-size:24px}
.subtitle{color:#475569;margin-bottom:28px;font-size:15px}
.alert{padding:16px 18px;border-radius:12px;margin-bottom:20px;font-size:14px;
  display:flex;gap:12px;align-items:flex-start;animation:slideDown .4s}
.alert.error{background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5}
.alert.success{background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0}
.alert.info{background:#DBEAFE;color:#1E40AF;border:1px solid #BFDBFE}
.alert.warning{background:#FEF3C7;color:#92400E;border:1px solid #FCD34D}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.alert-icon{flex-shrink:0;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px}
.alert.error .alert-icon{background:#991B1B;color:#fff}
.alert.success .alert-icon{background:#065F46;color:#fff}
.alert.info .alert-icon{background:#1E40AF;color:#fff}
.alert.warning .alert-icon{background:#92400E;color:#fff}
.alert-body{flex:1;min-width:0}
.alert pre{white-space:pre-wrap;font-family:inherit;font-size:13px;margin-top:6px;line-height:1.5;word-break:break-word}
.alert ul{margin:6px 0 0 18px;font-size:13px}
.error-detail{background:rgba(255,255,255,.5);padding:8px 12px;border-radius:8px;margin-top:8px;font-size:12px;font-family:'Courier New',monospace;border:1px solid rgba(0,0,0,.08);max-height:200px;overflow-y:auto}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-weight:600;margin-bottom:6px;font-size:13px;color:#475569}
.form-group label .req{color:#EF4444}
.form-control{width:100%;padding:12px 14px;border:1.5px solid #E2E8F0;border-radius:10px;
  font-size:16px;font-family:inherit;background:#fff;transition:all .2s;-webkit-appearance:none}
.form-control:hover{border-color:#CBD5E1}
.form-control:focus{outline:none;border-color:#3B82F6;box-shadow:0 0 0 4px rgba(59,130,246,.1)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media (max-width:520px){.form-row{grid-template-columns:1fr}}
.help-text{font-size:12px;color:#94A3B8;margin-top:6px}
.actions{display:flex;gap:12px;margin-top:24px;justify-content:space-between;flex-wrap:wrap}
.btn{padding:13px 24px;border-radius:10px;font-weight:600;font-size:14px;border:none;cursor:pointer;
  display:inline-flex;align-items:center;gap:8px;text-decoration:none;font-family:inherit;
  transition:all .2s;white-space:nowrap;min-height:46px;-webkit-tap-highlight-color:transparent}
.btn-primary{background:linear-gradient(135deg,#3B82F6,#2563EB);color:#fff;
  box-shadow:0 8px 20px rgba(59,130,246,.3)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(59,130,246,.4)}
.btn-primary:active{transform:translateY(0)}
.btn-outline{background:#fff;color:#3B82F6;border:1.5px solid #E2E8F0}
.btn-outline:hover{border-color:#3B82F6;background:#F0F9FF}
.btn-light{background:#F1F5F9;color:#475569}
.btn-light:hover{background:#E2E8F0}
.btn-success{background:linear-gradient(135deg,#10B981,#059669);color:#fff;
  box-shadow:0 8px 20px rgba(16,185,129,.3)}
.btn-success:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(16,185,129,.4)}
.req-list{list-style:none;background:#F8FAFC;border-radius:12px;overflow:hidden;border:1px solid #E2E8F0}
.req-list li{padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;
  border-bottom:1px solid #E2E8F0;font-size:14px;animation:fadeIn .4s both}
.req-list li:last-child{border-bottom:none}
@keyframes fadeIn{from{opacity:0;transform:translateX(-8px)}to{opacity:1;transform:translateX(0)}}
.req-list .name{font-weight:500;flex:1;min-width:0}
.req-list .value{color:#64748B;font-size:12px;font-family:monospace}
.req-status{width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:12px;flex-shrink:0}
.req-status.ok{background:#D1FAE5;color:#065F46}
.req-status.warn{background:#FEF3C7;color:#92400E}
.req-status.fail{background:#FEE2E2;color:#991B1B}
.install-icon{width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-size:40px;font-weight:900;margin:0 auto 20px;animation:bounceIn .6s}
.install-icon.success{background:linear-gradient(135deg,#10B981,#059669);color:#fff;
  box-shadow:0 16px 40px rgba(16,185,129,.4)}
@keyframes bounceIn{0%{transform:scale(0)}60%{transform:scale(1.15)}100%{transform:scale(1)}}
code{background:#F1F5F9;padding:2px 6px;border-radius:4px;font-family:'Courier New',monospace;font-size:12px}
.confetti{position:fixed;width:10px;height:10px;pointer-events:none;z-index:1000;border-radius:2px}
@keyframes confetti{0%{transform:translateY(-10vh) rotate(0);opacity:1}100%{transform:translateY(100vh) rotate(720deg);opacity:0}}
.section-divider{margin:24px 0 14px;padding-bottom:8px;border-bottom:2px solid #F1F5F9;font-size:13px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;display:flex;align-items:center;gap:8px}

/* MOBILE-FIRST OVERRIDES v3.0 — install.php */
@media (max-width:720px){
  body{padding:16px 12px}
  .wrap{max-width:100%}
  .brand{margin-bottom:24px;font-size:16px;gap:10px}
  .brand-icon{width:42px;height:42px;font-size:15px;border-radius:12px}
  .brand small{font-size:10px}
  .steps{margin-bottom:24px;padding:0 4px}
  .step-num{width:32px;height:32px;font-size:12px}
  .step::after{top:16px}
  .card{padding:24px 18px;border-radius:16px}
  h1{font-size:22px}
  h2{font-size:20px}
  .subtitle{font-size:14px;margin-bottom:22px}
  .alert{padding:13px 14px;border-radius:10px;font-size:13px;gap:10px}
  .alert-icon{width:22px;height:22px;font-size:12px}
  .form-group{margin-bottom:14px}
  .form-group label{font-size:12px;margin-bottom:5px}
  .form-control{padding:12px 12px;border-radius:10px;font-size:16px;min-height:48px}
  .form-row{grid-template-columns:1fr;gap:0}
  .actions{flex-direction:column;gap:8px;margin-top:18px}
  .actions .btn{width:100%;justify-content:center}
  .btn{padding:13px 18px;font-size:13px;border-radius:10px;min-height:48px}
  .req-list li{padding:10px 14px;font-size:13px;gap:8px}
  .req-list .name{font-size:13px}
  .req-list .value{font-size:11px}
  .req-status{width:22px;height:22px;font-size:11px}
  .install-icon{width:64px;height:64px;font-size:32px;margin-bottom:16px}
}
@media (max-width:480px){
  body{padding:12px 8px}
  .card{padding:20px 14px;border-radius:14px}
  h1{font-size:20px}
  .step-label{display:none !important}
  .brand{font-size:15px}
}
@media (hover:none){
  .btn:hover,.btn-primary:hover,.btn-success:hover,.btn-outline:hover{
    transform:none !important;
  }
}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <div class="brand-icon">VP</div>
    <div>
      VatanParvar Yaypan
      <small>O'rnatish ustasi · v2.2</small>
    </div>
  </div>
  <?php render_steps((int)($_GET['step'] ?? 1)); ?>
  <?= $body ?>
</div>
</body>
</html>
<?php
}

function render_steps(int $current): void {
    $steps = [
        1 => 'Talablar', 2 => 'Database',
        3 => 'Admin', 4 => 'Sozlama', 5 => 'Tayyor',
    ];
    echo '<div class="steps">';
    foreach ($steps as $n => $label) {
        $cls = $n < $current ? 'done' : ($n === $current ? 'active' : '');
        $icon = $n < $current ? '✓' : $n;
        echo "<div class='step $cls'><div class='step-num'>$icon</div><div class='step-label'>$label</div></div>";
    }
    echo '</div>';
}

// ==========================================================
// SAHIFA RENDER
// ==========================================================
ob_start();

// Warning flash (DB step yakunlangach)
if (!empty($_SESSION['install_warnings']) && $step === 3): ?>
<div class="alert warning">
  <div class="alert-icon">!</div>
  <div class="alert-body"><?= htmlspecialchars($_SESSION['install_warnings']) ?></div>
</div>
<?php unset($_SESSION['install_warnings']); endif; ?>

<?php if ($error): ?>
<div class="alert error">
  <div class="alert-icon">!</div>
  <div class="alert-body">
    <strong>Xatolik:</strong>
    <pre><?= htmlspecialchars($error) ?></pre>

    <?php if (!empty($details['errors'])): ?>
    <details style="margin-top:10px">
      <summary style="cursor:pointer;font-weight:600;font-size:13px">Texnik tafsilotlar (<?= count($details['errors']) ?> ta xato)</summary>
      <div class="error-detail">
        <?php foreach (array_slice($details['errors'], 0, 10) as $i => $err): ?>
          <div style="margin-bottom:6px;padding-bottom:6px;border-bottom:1px solid rgba(0,0,0,.05)">
            <strong>#<?= $i+1 ?>.</strong> <?= htmlspecialchars($err['message']) ?><br>
            <small style="opacity:.7"><?= htmlspecialchars($err['preview']) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    </details>
    <?php endif; ?>

    <?php if (!empty($details['existing_tables'])): ?>
    <details style="margin-top:10px">
      <summary style="cursor:pointer;font-weight:600;font-size:13px">DB jadvallar holati</summary>
      <div class="error-detail">
        <strong>Mavjud (<?= count($details['existing_tables']) ?>):</strong> <?= implode(', ', $details['existing_tables']) ?><br>
        <?php if (!empty($details['missing_tables'])): ?>
          <strong style="color:#991B1B">Etishmaydi (<?= count($details['missing_tables']) ?>):</strong> <?= implode(', ', $details['missing_tables']) ?>
        <?php endif; ?>
      </div>
    </details>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($step === 1): ?>
  <?php $checks = check_requirements();
        $has_critical_fail = false;
        foreach ($checks as $c) if (!$c[2] && $c[3]==='critical') { $has_critical_fail = true; break; }
  ?>
  <div class="card">
    <h1>👋 Xush kelibsiz!</h1>
    <p class="subtitle">VatanParvar Yaypan platformasini o'rnatishni boshlaymiz. Avval tizim talablarini tekshiramiz.</p>

    <ul class="req-list">
      <?php foreach ($checks as $c):
        $cls = $c[2] ? 'ok' : ($c[3]==='warning' ? 'warn' : 'fail');
        $icon = $c[2] ? '✓' : ($c[3]==='warning' ? '!' : '✗');
      ?>
      <li>
        <span class="name"><?= htmlspecialchars($c[0]) ?></span>
        <span class="value"><?= htmlspecialchars($c[1]) ?></span>
        <span class="req-status <?= $cls ?>"><?= $icon ?></span>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($has_critical_fail): ?>
      <div class="alert error" style="margin-top:20px">
        <div class="alert-icon">!</div>
        <div class="alert-body">Davom etish mumkin emas. Yuqoridagi muhim talablarni bajaring va sahifani yangilang.</div>
      </div>
      <div class="actions">
        <span></span>
        <a href="?step=1" class="btn btn-outline">Yangilash</a>
      </div>
    <?php else: ?>
      <div class="alert success" style="margin-top:20px">
        <div class="alert-icon">✓</div>
        <div class="alert-body">Tizim talablari bajarildi. Keyingi bosqichga o'ting.</div>
      </div>
      <div class="actions">
        <span></span>
        <a href="?step=2" class="btn btn-primary">Davom etish →</a>
      </div>
    <?php endif; ?>
  </div>

<?php elseif ($step === 2): ?>
  <div class="card">
    <h2>🗄️ Ma'lumotlar bazasi</h2>
    <p class="subtitle">MySQL/MariaDB ulanish ma'lumotlarini kiriting. DB mavjud bo'lmasa, biz uni avtomatik yaratamiz.</p>

    <div class="alert info" style="margin-bottom:20px">
      <div class="alert-icon">i</div>
      <div class="alert-body">
        <strong>x10Hosting / cPanel uchun:</strong>
        <ol style="margin:6px 0 0 18px;font-size:13px;line-height:1.7">
          <li>cPanel → <strong>MySQL Databases</strong></li>
          <li>Yangi DB yarating (masalan: <code>avto</code>) — to'liq nom: <code>username_avto</code></li>
          <li>Yangi User yarating va <strong>kuchli parol</strong> tanlang</li>
          <li>User ni DB ga biriktiring va <strong>ALL PRIVILEGES</strong> bering</li>
          <li>To'liq nomni quyiga kiriting (prefix bilan!)</li>
        </ol>
      </div>
    </div>

    <form method="post">
      <input type="hidden" name="step" value="2">
      <div class="form-group">
        <label>DB Host <span class="req">*</span></label>
        <input type="text" name="db_host" class="form-control" required
          value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>"
          placeholder="localhost">
        <div class="help-text">Odatda <code>localhost</code></div>
      </div>
      <div class="form-group">
        <label>Database nomi <span class="req">*</span></label>
        <input type="text" name="db_name" class="form-control" required
          value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>"
          placeholder="username_avto">
        <div class="help-text">x10/cPanel'da prefix bilan: <code>username_dbname</code></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>DB Foydalanuvchi <span class="req">*</span></label>
          <input type="text" name="db_user" class="form-control" required
            value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>"
            placeholder="username_dbuser">
        </div>
        <div class="form-group">
          <label>DB Parol</label>
          <input type="password" name="db_pass" class="form-control"
            value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>"
            placeholder="parolingiz">
        </div>
      </div>

      <div class="actions">
        <a href="?step=1" class="btn btn-outline">← Ortga</a>
        <button type="submit" class="btn btn-primary">Ulanish + jadvallar yaratish →</button>
      </div>
    </form>
  </div>

<?php elseif ($step === 3): ?>
  <?php
    // Sessiya yo'qolsa — config.php dan o'qishga harakat
    if (empty($_SESSION['install']['db_host'])) {
        try { get_pdo(); } catch (Throwable $e) {
            header('Location: install.php?step=2'); exit;
        }
    }
  ?>
  <div class="card">
    <h2>👤 Admin akkaunti</h2>
    <p class="subtitle">Birinchi administrator akkauntini yarating. Bu akkaunt orqali admin paneliga kirasiz.</p>

    <form method="post">
      <input type="hidden" name="step" value="3">
      <div class="form-row">
        <div class="form-group">
          <label>Ism <span class="req">*</span></label>
          <input type="text" name="first_name" class="form-control" required
            value="<?= htmlspecialchars($_POST['first_name'] ?? 'Admin') ?>">
        </div>
        <div class="form-group">
          <label>Familiya <span class="req">*</span></label>
          <input type="text" name="last_name" class="form-control" required
            value="<?= htmlspecialchars($_POST['last_name'] ?? 'VatanParvar') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Email <span class="req">*</span></label>
        <input type="email" name="email" class="form-control" required
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          placeholder="admin@yourdomain.uz">
      </div>
      <div class="form-group">
        <label>Telefon</label>
        <input type="tel" name="phone" class="form-control"
          value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
          placeholder="+998 90 123 45 67">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Parol <span class="req">*</span></label>
          <input type="password" name="password" class="form-control" required minlength="8" id="pwd1">
          <div class="help-text">Kamida 8 belgi</div>
        </div>
        <div class="form-group">
          <label>Parolni tasdiqlang <span class="req">*</span></label>
          <input type="password" name="password2" class="form-control" required minlength="8" id="pwd2">
        </div>
      </div>

      <div class="alert warning">
        <div class="alert-icon">!</div>
        <div class="alert-body">Parolingizni xavfsiz joyda saqlang!</div>
      </div>

      <div class="actions">
        <a href="?step=2" class="btn btn-outline">← Ortga</a>
        <button type="submit" class="btn btn-primary">Davom etish →</button>
      </div>
    </form>
  </div>
  <script>
    document.getElementById('pwd2').addEventListener('input', function() {
      const p1 = document.getElementById('pwd1').value;
      this.style.borderColor = (this.value && p1 !== this.value) ? '#EF4444' : '';
    });
  </script>

<?php elseif ($step === 4): ?>
  <?php if (empty($_SESSION['install']['admin_email'])) {
    header('Location: install.php?step=3'); exit;
  } ?>
  <div class="card">
    <h2>⚙️ Sayt sozlamalari</h2>
    <p class="subtitle">Asosiy sayt ma'lumotlarini kiriting. Keyinroq admin paneldan o'zgartirish mumkin.</p>

    <form method="post">
      <input type="hidden" name="step" value="4">

      <div class="section-divider">🏢 Asosiy</div>
      <div class="form-group">
        <label>Sayt nomi <span class="req">*</span></label>
        <input type="text" name="site_name" class="form-control" required
          value="<?= htmlspecialchars($_POST['site_name'] ?? 'VatanParvar Yaypan') ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Telefon</label>
          <input type="text" name="site_phone" class="form-control"
            value="<?= htmlspecialchars($_POST['site_phone'] ?? '+998 90 123 45 67') ?>">
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="site_email" class="form-control"
            value="<?= htmlspecialchars($_POST['site_email'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Manzil</label>
        <input type="text" name="site_address" class="form-control"
          value="<?= htmlspecialchars($_POST['site_address'] ?? "Yaypan shahri, Farg'ona viloyati") ?>">
      </div>

      <div class="section-divider">💳 To'lov (ixtiyoriy)</div>
      <div class="form-row">
        <div class="form-group">
          <label>Karta raqami</label>
          <input type="text" name="card_number" class="form-control" placeholder="8600 1234 5678 9012">
        </div>
        <div class="form-group">
          <label>Karta egasi</label>
          <input type="text" name="card_holder" class="form-control" placeholder="ISMINGIZ">
        </div>
      </div>

      <div class="section-divider">✈️ Telegram (ixtiyoriy)</div>
      <div class="form-group">
        <label>Bot Token</label>
        <input type="text" name="telegram_bot_token" class="form-control" placeholder="123456:ABC-DEF...">
        <div class="help-text">@BotFather'dan oling</div>
      </div>
      <div class="form-group">
        <label>Admin Chat ID</label>
        <input type="text" name="telegram_admin_chat_id" class="form-control" placeholder="123456789">
      </div>

      <div class="actions">
        <a href="?step=3" class="btn btn-outline">← Ortga</a>
        <button type="submit" class="btn btn-success">O'rnatishni yakunlash 🎉</button>
      </div>
    </form>
  </div>

<?php elseif ($step === 5): ?>
  <div class="card" style="text-align:center">
    <div class="install-icon success">✓</div>
    <h1>Tabriklaymiz! 🎉</h1>
    <p class="subtitle">VatanParvar Yaypan platformasi muvaffaqiyatli o'rnatildi.</p>

    <div class="alert success" style="text-align:left">
      <div class="alert-icon">✓</div>
      <div class="alert-body">
        <strong>Bajarilgan ishlar:</strong>
        <ul>
          <li>14 ta DB jadvali yaratildi</li>
          <li>Boshlang'ich ma'lumotlar yuklandi</li>
          <li>Admin akkaunti yaratildi</li>
          <li>Sayt sozlamalari saqlandi</li>
          <li><code>.installed</code> qulf fayli o'rnatildi</li>
        </ul>
      </div>
    </div>

    <div class="alert warning" style="text-align:left">
      <div class="alert-icon">!</div>
      <div class="alert-body">
        <strong>⚠️ Xavfsizlik (juda muhim!):</strong>
        <ul>
          <li><code>install.php</code> faylini <strong>o'chiring</strong> yoki nomini o'zgartiring</li>
          <li>HTTPS sertifikat o'rnating (Let's Encrypt bepul)</li>
          <li>Cron job sozlang: <code>5 0 * * * php /home/.../cron/daily.php</code></li>
        </ul>
      </div>
    </div>

    <div class="actions" style="justify-content:center;margin-top:28px">
      <a href="/" class="btn btn-outline">Bosh sahifa</a>
      <a href="/login.php" class="btn btn-primary">Admin sifatida kirish →</a>
    </div>
  </div>
  <script>
    // Confetti
    const colors = ['#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899'];
    for (let i = 0; i < 100; i++) {
      setTimeout(() => {
        const c = document.createElement('div');
        c.className = 'confetti';
        c.style.left = Math.random() * 100 + 'vw';
        c.style.top = '-10px';
        c.style.background = colors[Math.floor(Math.random() * colors.length)];
        c.style.animation = `confetti ${2 + Math.random() * 2}s linear forwards`;
        c.style.animationDelay = Math.random() * 0.4 + 's';
        document.body.appendChild(c);
        setTimeout(() => c.remove(), 4500);
      }, i * 25);
    }
  </script>
<?php endif; ?>

<?php
$body = ob_get_clean();
render_layout('Step ' . $step, $body);
