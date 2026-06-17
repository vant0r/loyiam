<?php
/**
 * VatanParvar Yaypan — O'rnatish ustasi (Installation Wizard)
 *
 * 5 bosqichli sehrgar:
 *   1. Talablar — PHP versiyasi, kengaytmalar, fayl ruxsatlari
 *   2. Database — ulanish + jadval yaratish
 *   3. Admin — birinchi admin akkaunt
 *   4. Sayt sozlamalari — nom, kontakt
 *   5. Tugadi — yo'nalish berish
 *
 * Tugatilgach `.installed` fayli yaratiladi va install.php bloklanadi.
 */

@ini_set('display_errors', 1);
error_reporting(E_ALL);
@set_time_limit(0);

if (session_status() === PHP_SESSION_NONE) session_start();

define('INSTALL_BASE', __DIR__);
define('INSTALL_LOCK', INSTALL_BASE . '/.installed');
define('CONFIG_FILE',  INSTALL_BASE . '/includes/config.php');
define('SQL_FILE',     INSTALL_BASE . '/sql/database.sql');

// ==========================================================
// AGAR ALLAQACHON O'RNATILGAN BO'LSA — bloklash
// ==========================================================
$alreadyInstalled = is_file(INSTALL_LOCK);
if ($alreadyInstalled && !isset($_GET['force'])) {
    show_already_installed();
    exit;
}

// ==========================================================
// ASOSIY BOSQICH ROUTING
// ==========================================================
$step = max(1, min(5, (int)($_GET['step'] ?? 1)));
$error = '';
$success = '';

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
    }
}

// ==========================================================
// HANDLERS
// ==========================================================

function handle_database(): void {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';

    if (!$host || !$name || !$user) {
        throw new RuntimeException("Barcha maydonlarni to'ldiring (parol bo'sh bo'lishi mumkin)");
    }

    // Ulanishni tekshirish
    try {
        $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (PDOException $e) {
        // DB mavjud emas? Yaratishga harakat qilamiz
        try {
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name`
                        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$name`");
        } catch (PDOException $e2) {
            throw new RuntimeException("DB ulanish xatosi: " . $e2->getMessage());
        }
    }

    // SQL faylini ishga tushirish
    if (!is_file(SQL_FILE)) {
        throw new RuntimeException("sql/database.sql fayli topilmadi");
    }

    $sql = file_get_contents(SQL_FILE);
    if (!$sql) throw new RuntimeException("SQL faylni o'qib bo'lmadi");

    // CREATE DATABASE va USE liniyalarni o'tkazib yuboramiz (bizda allaqachon ulangan)
    $sql = preg_replace('/CREATE\s+DATABASE.*?;/is', '', $sql);
    $sql = preg_replace('/USE\s+`?[a-z0-9_]+`?\s*;/is', '', $sql);

    // ; orqali bo'lish
    $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql)));

    $errors = [];
    $executed = 0;
    foreach ($statements as $stmt) {
        if (!$stmt || str_starts_with(ltrim($stmt), '--')) continue;
        try {
            $pdo->exec($stmt);
            $executed++;
        } catch (PDOException $e) {
            // Duplicate, IF EXISTS, allaqachon mavjud — e'tiborsiz qoldiramiz
            $code = $e->getCode();
            if (!in_array($code, ['42S01','42000','HY000'], true)) {
                $errors[] = mb_substr($e->getMessage(), 0, 200);
            }
        }
    }

    // Konfiguratsiya faylini yangilash
    if (!update_config_db($host, $name, $user, $pass)) {
        throw new RuntimeException("includes/config.php faylini yangilab bo'lmadi");
    }

    // Sessiyaga saqlaymiz keyingi bosqichlar uchun
    $_SESSION['install'] = [
        'db_host' => $host, 'db_name' => $name,
        'db_user' => $user, 'db_pass' => $pass,
    ];

    if (!empty($errors)) {
        // Faqat birinchi 3 ta xatoni ko'rsatamiz
        throw new RuntimeException(
            "Ba'zi SQL bayonotlar bajarilmadi:\n• " . implode("\n• ", array_slice($errors, 0, 3))
        );
    }

    header('Location: install.php?step=3');
    exit;
}

function handle_admin(): void {
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (!$first || !$last || !$email || !$pass) {
        throw new RuntimeException("Barcha majburiy maydonlarni to'ldiring");
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

    // Demo adminni o'chirib, yangisini qo'yamiz
    $pdo->prepare("DELETE FROM users WHERE email IN ('admin@vatanparvar.uz','dev@vatanparvar.uz','user@vatanparvar.uz') AND id <= 3")->execute();

    $stmt = $pdo->prepare(
        "INSERT INTO users (first_name, last_name, email, phone, password, role, status, referral_code)
         VALUES (?, ?, ?, ?, ?, 'admin', 'active', ?)
         ON DUPLICATE KEY UPDATE password = VALUES(password), role = 'admin', status='active'"
    );
    $stmt->execute([$first, $last, $email, $phone ?: null, $hash, $code]);

    $_SESSION['install']['admin_email'] = $email;
    header('Location: install.php?step=4');
    exit;
}

function handle_settings(): void {
    $site_name    = trim($_POST['site_name'] ?? 'VatanParvar Yaypan');
    $site_phone   = trim($_POST['site_phone'] ?? '');
    $site_email   = trim($_POST['site_email'] ?? '');
    $site_address = trim($_POST['site_address'] ?? '');
    $tg_token     = trim($_POST['telegram_bot_token'] ?? '');
    $tg_chat      = trim($_POST['telegram_admin_chat_id'] ?? '');
    $card         = trim($_POST['card_number'] ?? '');
    $card_holder  = trim($_POST['card_holder'] ?? '');

    $pdo = get_pdo();
    $kv = [
        'site_name'              => $site_name,
        'site_phone'             => $site_phone,
        'site_email'             => $site_email,
        'site_address'           => $site_address,
        'telegram_bot_token'     => $tg_token,
        'telegram_admin_chat_id' => $tg_chat,
        'card_number'            => $card,
        'card_holder'            => $card_holder,
    ];

    $stmt = $pdo->prepare(
        "INSERT INTO settings (setting_key, setting_value, setting_group)
         VALUES (?, ?, 'general')
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    foreach ($kv as $k => $v) {
        if ($v !== '') $stmt->execute([$k, $v]);
    }

    // Cache faylini tozalash
    @unlink(INSTALL_BASE . '/cache/data/settings.cache');

    // Lock fayl yaratish
    file_put_contents(INSTALL_LOCK, json_encode([
        'installed_at' => date('c'),
        'version'      => '2.0.0',
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
    if (empty($i['db_host'])) throw new RuntimeException("Avval DB sozlamalarini kiriting");
    $dsn = "mysql:host={$i['db_host']};dbname={$i['db_name']};charset=utf8mb4";
    return new PDO($dsn, $i['db_user'], $i['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

function check_requirements(): array {
    $php_ok    = version_compare(PHP_VERSION, '7.4.0', '>=');
    $php_8_ok  = version_compare(PHP_VERSION, '8.0.0', '>=');
    $exts = ['pdo','pdo_mysql','mbstring','json','session','fileinfo','curl','openssl'];
    $checks = [
        ['PHP versiyasi 7.4+', PHP_VERSION, $php_ok, 'critical'],
        ['PHP 8.0+ (tavsiya)', PHP_VERSION, $php_8_ok, 'warning'],
    ];
    foreach ($exts as $ext) {
        $loaded = extension_loaded($ext);
        $crit = in_array($ext, ['pdo','pdo_mysql','mbstring','json','session']) ? 'critical' : 'warning';
        $checks[] = ["$ext kengaytmasi", $loaded ? 'mavjud' : 'yo\'q', $loaded, $crit];
    }
    // Yozish ruxsatlari
    $writable = [
        '/cache/data'        => INSTALL_BASE . '/cache/data',
        '/cache/ratelimit'   => INSTALL_BASE . '/cache/ratelimit',
        '/assets/uploads'    => INSTALL_BASE . '/assets/uploads',
        '/includes/config.php' => CONFIG_FILE,
    ];
    foreach ($writable as $label => $path) {
        if (!file_exists($path)) {
            $created = is_dir(dirname($path)) ? @mkdir($path, 0755, true) : false;
        }
        $w = is_writable($path);
        $checks[] = ["$label yozilishi mumkin", $w ? 'OK' : 'Yo\'q', $w, 'critical'];
    }
    return $checks;
}

function show_already_installed(): void {
    render_layout('Allaqachon o\'rnatilgan', '<div class="card text-center" style="padding:48px 32px">
        <div class="install-icon success">✓</div>
        <h2>Loyiha allaqachon o\'rnatilgan</h2>
        <p class="text-soft">Qayta o\'rnatish uchun <code>.installed</code> faylini o\'chiring yoki <code>?force=1</code> qo\'shing.</p>
        <div class="actions">
          <a href="/" class="btn btn-primary">Bosh sahifa</a>
          <a href="/admin/" class="btn btn-outline">Admin panel</a>
        </div>
      </div>');
}

// Layout (umumiy HTML)
function render_layout(string $title, string $body): void {
    ?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?> — VP O'rnatish</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',-apple-system,sans-serif;background:#F8FAFC;color:#0F172A;
  min-height:100vh;line-height:1.6;padding:24px 16px;
  background-image:radial-gradient(ellipse at top right,rgba(59,130,246,.15),transparent 60%),
                   radial-gradient(ellipse at bottom left,rgba(168,85,247,.08),transparent 60%);
  background-attachment:fixed}
.wrap{max-width:680px;margin:0 auto}
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
.step.active::after{background:linear-gradient(90deg,#3B82F6,#E2E8F0)}
.step.done::after{background:#10B981}
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
.alert{padding:14px 18px;border-radius:12px;margin-bottom:20px;font-size:14px;display:flex;gap:10px;align-items:flex-start;animation:shake .4s}
.alert.error{background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5}
.alert.success{background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0}
.alert.info{background:#DBEAFE;color:#1E40AF;border:1px solid #BFDBFE}
.alert.warning{background:#FEF3C7;color:#92400E;border:1px solid #FCD34D}
@keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-4px)}75%{transform:translateX(4px)}}
.alert-icon{flex-shrink:0;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px}
.alert.error .alert-icon{background:#991B1B;color:#fff}
.alert.success .alert-icon{background:#065F46;color:#fff}
.alert.info .alert-icon{background:#1E40AF;color:#fff}
.alert.warning .alert-icon{background:#92400E;color:#fff}
.alert pre{white-space:pre-wrap;font-family:inherit;font-size:13px;margin-top:4px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-weight:600;margin-bottom:6px;font-size:13px;color:#475569}
.form-group label .req{color:#EF4444}
.form-control{width:100%;padding:12px 14px;border:1.5px solid #E2E8F0;border-radius:10px;
  font-size:14px;font-family:inherit;background:#fff;transition:all .2s}
.form-control:hover{border-color:#CBD5E1}
.form-control:focus{outline:none;border-color:#3B82F6;box-shadow:0 0 0 4px rgba(59,130,246,.1)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media (max-width:520px){.form-row{grid-template-columns:1fr}}
.help-text{font-size:12px;color:#94A3B8;margin-top:6px}
.actions{display:flex;gap:12px;margin-top:24px;justify-content:space-between;flex-wrap:wrap}
.btn{padding:13px 24px;border-radius:10px;font-weight:600;font-size:14px;border:none;cursor:pointer;
  display:inline-flex;align-items:center;gap:8px;text-decoration:none;font-family:inherit;
  transition:all .2s;white-space:nowrap}
.btn-primary{background:linear-gradient(135deg,#3B82F6,#2563EB);color:#fff;
  box-shadow:0 8px 20px rgba(59,130,246,.3)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(59,130,246,.4)}
.btn-primary:active{transform:translateY(0)}
.btn-outline{background:#fff;color:#3B82F6;border:1.5px solid #E2E8F0}
.btn-outline:hover{border-color:#3B82F6;background:#F0F9FF}
.btn-success{background:linear-gradient(135deg,#10B981,#059669);color:#fff;
  box-shadow:0 8px 20px rgba(16,185,129,.3)}
.btn-success:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(16,185,129,.4)}
.req-list{list-style:none;background:#F8FAFC;border-radius:12px;overflow:hidden;border:1px solid #E2E8F0}
.req-list li{padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;
  border-bottom:1px solid #E2E8F0;font-size:14px;animation:fadeIn .4s both}
.req-list li:nth-child(1){animation-delay:0s}.req-list li:nth-child(2){animation-delay:.05s}
.req-list li:nth-child(3){animation-delay:.1s}.req-list li:nth-child(4){animation-delay:.15s}
.req-list li:nth-child(5){animation-delay:.2s}.req-list li:nth-child(6){animation-delay:.25s}
.req-list li:nth-child(7){animation-delay:.3s}.req-list li:nth-child(8){animation-delay:.35s}
.req-list li:nth-child(9){animation-delay:.4s}.req-list li:nth-child(10){animation-delay:.45s}
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
.demo-creds{background:#F0F9FF;border:1px dashed #3B82F6;border-radius:12px;padding:14px 16px;font-size:13px;margin-top:14px}
.demo-creds strong{color:#1E40AF}
.demo-creds code{background:#fff;padding:2px 8px;border-radius:4px;font-family:'Courier New',monospace}
code{background:#F1F5F9;padding:2px 6px;border-radius:4px;font-family:'Courier New',monospace;font-size:12px}
.confetti{position:fixed;width:10px;height:10px;pointer-events:none;z-index:1000;border-radius:2px}
@keyframes confetti{0%{transform:translateY(-10vh) rotate(0);opacity:1}100%{transform:translateY(100vh) rotate(720deg);opacity:0}}
.lang-bar{display:flex;justify-content:flex-end;margin-bottom:14px;gap:6px}
.lang-bar a{padding:5px 10px;font-size:12px;border-radius:6px;background:#fff;border:1px solid #E2E8F0;color:#64748B;text-decoration:none}
.lang-bar a.active{background:#3B82F6;color:#fff;border-color:#3B82F6}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <div class="brand-icon">VP</div>
    <div>
      VatanParvar Yaypan
      <small>O'rnatish ustasi · v2.0</small>
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
        1 => ['Talablar', 'Tizim tekshiruvi'],
        2 => ['Database', 'MySQL ulanish'],
        3 => ['Admin', 'Akkaunt yaratish'],
        4 => ['Sozlama', 'Sayt sozlamalari'],
        5 => ['Tayyor', 'Tugatish'],
    ];
    echo '<div class="steps">';
    foreach ($steps as $n => [$label, $desc]) {
        $cls = $n < $current ? 'done' : ($n === $current ? 'active' : '');
        $icon = $n < $current ? '✓' : $n;
        echo "<div class='step $cls'>";
        echo "  <div class='step-num'>$icon</div>";
        echo "  <div class='step-label'>$label</div>";
        echo "</div>";
    }
    echo '</div>';
}

// ==========================================================
// SAHIFA RENDER
// ==========================================================

ob_start();

if ($error): ?>
<div class="alert error">
  <div class="alert-icon">!</div>
  <div><strong>Xatolik:</strong><pre><?= htmlspecialchars($error) ?></pre></div>
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
        <div>Davom etish mumkin emas. Yuqoridagi muhim talablarni bajaring va sahifani yangilang.</div>
      </div>
      <div class="actions">
        <span></span>
        <a href="?step=1" class="btn btn-outline">Yangilash</a>
      </div>
    <?php else: ?>
      <div class="alert success" style="margin-top:20px">
        <div class="alert-icon">✓</div>
        <div>Tizim talablari bajarildi. Keyingi bosqichga o'ting.</div>
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
    <p class="subtitle">MySQL/MariaDB ulanish ma'lumotlarini kiriting. Database mavjud bo'lmasa, biz uni yaratamiz.</p>

    <form method="post">
      <input type="hidden" name="step" value="2">
      <div class="form-group">
        <label>DB Host <span class="req">*</span></label>
        <input type="text" name="db_host" class="form-control" required
          value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>"
          placeholder="localhost">
        <div class="help-text">Odatda <code>localhost</code>. x10Hosting'da boshqa bo'lishi mumkin.</div>
      </div>
      <div class="form-group">
        <label>Database nomi <span class="req">*</span></label>
        <input type="text" name="db_name" class="form-control" required
          value="<?= htmlspecialchars($_POST['db_name'] ?? 'vatanparvar_yaypan') ?>"
          placeholder="vatanparvar_yaypan">
        <div class="help-text">Mavjud bo'lmasa avtomatik yaratiladi.</div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>DB Foydalanuvchi <span class="req">*</span></label>
          <input type="text" name="db_user" class="form-control" required
            value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>">
        </div>
        <div class="form-group">
          <label>DB Parol</label>
          <input type="password" name="db_pass" class="form-control"
            placeholder="bo'sh bo'lishi mumkin">
        </div>
      </div>

      <div class="alert info">
        <div class="alert-icon">i</div>
        <div>
          <strong>x10Hosting/cPanel uchun:</strong> DB nomi va user odatda <code>username_dbname</code> formatida bo'ladi.
          phpMyAdmin orqali avval DB yaratib, foydalanuvchini biriktiring.
        </div>
      </div>

      <div class="actions">
        <a href="?step=1" class="btn btn-outline">← Ortga</a>
        <button type="submit" class="btn btn-primary">Ulanishni tekshirish va o'rnatish →</button>
      </div>
    </form>
  </div>

<?php elseif ($step === 3): ?>
  <?php if (empty($_SESSION['install']['db_host'])) {
    header('Location: install.php?step=2'); exit;
  } ?>
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
        <div>Parolingizni xavfsiz joyda saqlang! Yo'qotgan taqdirda DB orqali tiklash kerak bo'ladi.</div>
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

      <h3 style="font-size:14px;font-weight:700;margin:24px 0 12px;color:#475569;text-transform:uppercase;letter-spacing:.05em">💳 To'lov</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Karta raqami</label>
          <input type="text" name="card_number" class="form-control"
            placeholder="8600 1234 5678 9012">
        </div>
        <div class="form-group">
          <label>Karta egasi</label>
          <input type="text" name="card_holder" class="form-control"
            placeholder="VATANPARVAR YAYPAN">
        </div>
      </div>

      <h3 style="font-size:14px;font-weight:700;margin:24px 0 12px;color:#475569;text-transform:uppercase;letter-spacing:.05em">✈️ Telegram (ixtiyoriy)</h3>
      <div class="form-group">
        <label>Bot Token</label>
        <input type="text" name="telegram_bot_token" class="form-control"
          placeholder="123456:ABC-DEF...">
        <div class="help-text">@BotFather'dan oling</div>
      </div>
      <div class="form-group">
        <label>Admin Chat ID</label>
        <input type="text" name="telegram_admin_chat_id" class="form-control"
          placeholder="123456789">
      </div>

      <div class="actions">
        <a href="?step=3" class="btn btn-outline">← Ortga</a>
        <button type="submit" class="btn btn-success">O'rnatishni yakunlash 🎉</button>
      </div>
    </form>
  </div>

<?php elseif ($step === 5): ?>
  <div class="card text-center" style="text-align:center">
    <div class="install-icon success">✓</div>
    <h1>O'rnatish muvaffaqiyatli yakunlandi! 🎉</h1>
    <p class="subtitle">VatanParvar Yaypan platformasi tayyor. Endi admin paneliga kirishingiz mumkin.</p>

    <div class="alert success" style="text-align:left;margin:24px 0">
      <div class="alert-icon">✓</div>
      <div>
        <strong>Bajarilgan ishlar:</strong>
        <ul style="margin:6px 0 0 18px;font-size:13px">
          <li>Database jadvallari yaratildi</li>
          <li>Admin akkaunti yaratildi</li>
          <li>Sayt sozlamalari saqlandi</li>
          <li><code>.installed</code> qulf fayli o'rnatildi</li>
        </ul>
      </div>
    </div>

    <div class="alert warning" style="text-align:left">
      <div class="alert-icon">!</div>
      <div>
        <strong>Xavfsizlik uchun muhim:</strong>
        <ul style="margin:6px 0 0 18px;font-size:13px">
          <li><code>install.php</code> faylini o'chiring yoki nomini o'zgartiring</li>
          <li>Cron job sozlang: <code>5 0 * * * php /path/cron/daily.php</code></li>
          <li>HTTPS sertifikat o'rnating (Let's Encrypt)</li>
          <li>Click/Payme integratsiyasini admin panelidan sozlang</li>
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
