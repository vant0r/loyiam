<?php
/**
 * REST API — JSON endpoints
 *
 * Endpoints:
 *   GET  /api/?action=tariffs             → tariflar ro'yxati
 *   GET  /api/?action=tickets             → biletlar
 *   GET  /api/?action=stats               → umumiy statistika
 *   GET  /api/?action=top-rating          → top 100 reyting
 *   GET  /api/?action=blog&page=1         → bloglar
 *   POST /api/?action=contact             → aloqa formasi
 *   POST /api/?action=login               → kirish (JSON yoki form-data)
 *   GET  /api/?action=me                  → joriy user (sessiya bilan)
 *   POST /api/?action=check-invoice       → chek tasdiqlash hash bo'yicha
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$action = $_REQUEST['action'] ?? '';
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

// Body JSON support
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $body = json_decode(file_get_contents('php://input'), true);
    if (is_array($body)) $_POST = array_merge($_POST, $body);
}

function api_response($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode(['ok' => $status < 400] + (is_array($data) ? $data : ['data' => $data]),
        JSON_UNESCAPED_UNICODE);
    exit;
}

function api_error(string $msg, int $status = 400): void {
    api_response(['error' => $msg], $status);
}

// Rate limit (umumiy 60/daq)
$rl = Security::rate_limit('api_' . client_ip(), 60, 60);
if (!$rl['allowed']) {
    header('X-RateLimit-Reset: ' . $rl['reset_at']);
    api_error('Too many requests', 429);
}
header('X-RateLimit-Remaining: ' . $rl['remaining']);

// =============================================================
switch ($action) {

    case 'tariffs':
        $tariffs = db()->fetchAll("SELECT id, name_$lang_field name, description_$lang_field description,
            price, duration_days, features_$lang_field features, is_popular
            FROM tariffs WHERE status='active' ORDER BY sort_order");
        foreach ($tariffs as &$t) {
            $t['features'] = array_filter(array_map('trim', explode('|', $t['features'])));
            $t['price'] = (float)$t['price'];
            $t['is_popular'] = (bool)$t['is_popular'];
        }
        api_response(['tariffs' => $tariffs]);

    case 'tickets':
        $tickets = db()->fetchAll("SELECT id, title_$lang_field title, ticket_number,
            questions_count, time_minutes
            FROM tickets WHERE status='active' ORDER BY ticket_number");
        api_response(['tickets' => $tickets]);

    case 'stats':
        $cached = false;
        $cacheFile = __DIR__ . '/../cache/data/api_stats.cache';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
            $stats = unserialize(file_get_contents($cacheFile));
            $cached = true;
        } else {
            $stats = [
                'users_total'  => (int)(db()->fetch("SELECT COUNT(*) c FROM users WHERE role='user' AND status='active'")['c'] ?? 0),
                'tests_total'  => (int)(db()->fetch("SELECT COUNT(*) c FROM test_attempts WHERE status='completed'")['c'] ?? 0),
                'questions'    => (int)(db()->fetch("SELECT COUNT(*) c FROM questions WHERE status='active'")['c'] ?? 0),
                'tickets'      => (int)(db()->fetch("SELECT COUNT(*) c FROM tickets WHERE status='active'")['c'] ?? 0),
                'avg_score'    => (float)(db()->fetch("SELECT AVG(score_percent) c FROM test_attempts WHERE status='completed'")['c'] ?? 0),
            ];
            @file_put_contents($cacheFile, serialize($stats));
        }
        api_response(['stats' => $stats, 'cached' => $cached]);

    case 'top-rating':
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 100)));
        $period = $_GET['period'] ?? 'all'; // all | month
        $dateFilter = $period === 'month' ? "AND a.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" : '';

        $top = db()->fetchAll("SELECT u.id, u.first_name, u.last_name,
                COUNT(a.id) attempts,
                COALESCE(AVG(a.score_percent),0) avg_score,
                COALESCE(SUM(a.correct_answers),0) total_correct
            FROM users u LEFT JOIN test_attempts a ON a.user_id=u.id AND a.status='completed' $dateFilter
            WHERE u.role='user' AND u.status='active'
            GROUP BY u.id ORDER BY avg_score DESC, total_correct DESC LIMIT $limit");

        foreach ($top as $i => &$r) {
            $r['rank']  = $i + 1;
            $r['name']  = $r['first_name'].' '.mb_substr($r['last_name'], 0, 1).'.';
            $r['avg_score'] = round((float)$r['avg_score'], 1);
            unset($r['first_name'], $r['last_name']);
        }
        api_response(['top' => $top, 'period' => $period]);

    case 'blog':
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;
        $offset  = ($page-1) * $perPage;
        $posts = db()->fetchAll("SELECT id, slug, title_$lang_field title, excerpt_$lang_field excerpt,
            image, category, views, created_at
            FROM blog_posts WHERE status='published' ORDER BY created_at DESC
            LIMIT $perPage OFFSET $offset");
        $total = (int)(db()->fetch("SELECT COUNT(*) c FROM blog_posts WHERE status='published'")['c'] ?? 0);
        api_response([
            'posts' => $posts,
            'pagination' => [
                'page' => $page, 'per_page' => $perPage,
                'total' => $total, 'pages' => (int)ceil($total/$perPage),
            ],
        ]);

    case 'me':
        if (!is_logged_in()) api_error('Unauthorized', 401);
        $u = current_user();
        api_response(['user' => [
            'id' => (int)$u['id'],
            'first_name' => $u['first_name'],
            'last_name'  => $u['last_name'],
            'phone'  => $u['phone'],
            'email'  => $u['email'],
            'role'   => $u['role'],
            'avatar' => $u['avatar'],
            'language'  => $u['language'],
            'tariff_id' => $u['tariff_id'] ? (int)$u['tariff_id'] : null,
            'tariff_expires_at' => $u['tariff_expires_at'],
            'referral_code' => $u['referral_code'],
        ]]);

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_error('POST required', 405);
        $r = Auth::login(
            $_POST['login'] ?? '',
            $_POST['password'] ?? '',
            !empty($_POST['remember'])
        );
        if ($r['ok']) api_response(['user' => $r['user'], 'redirect' => $r['redirect']]);
        api_error($r['msg'], 401);

    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_error('POST required', 405);
        $r = Auth::register($_POST);
        if ($r['ok']) api_response(['redirect' => $r['redirect']]);
        api_error($r['msg'], 400);

    case 'contact':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_error('POST required', 405);
        $name = Security::clean($_POST['name'] ?? '', 100);
        $email = Security::clean($_POST['email'] ?? '', 100);
        $phone = Security::clean($_POST['phone'] ?? '', 30);
        $message = Security::clean($_POST['message'] ?? '', 2000);
        if (!$name || !$message) api_error('Name and message required');
        if ($email && !Security::valid_email($email)) api_error('Invalid email');

        db()->execute("INSERT INTO contact_messages (name, email, phone, message) VALUES (?,?,?,?)",
            [$name, $email ?: null, $phone ?: null, $message]);
        api_response(['message' => 'Sent']);

    case 'check-invoice':
        $code = strtoupper(trim($_REQUEST['code'] ?? ''));
        if (!preg_match('/^[A-F0-9]{12}$/', $code)) api_error('Invalid code format');

        // Hashni qayta yaratamiz: substr(md5($id.$created_at), 0, 12)
        $payments = db()->fetchAll("SELECT id, created_at, amount, status FROM payments");
        foreach ($payments as $p) {
            $h = strtoupper(substr(md5($p['id'].$p['created_at']), 0, 12));
            if ($h === $code) {
                api_response([
                    'invoice' => [
                        'id'     => (int)$p['id'],
                        'amount' => (float)$p['amount'],
                        'status' => $p['status'],
                        'date'   => $p['created_at'],
                    ],
                    'verified' => true,
                ]);
            }
        }
        api_error('Invoice not found', 404);

    case 'health':
        $db_ok = (bool)db()->pdo;
        api_response([
            'status' => $db_ok ? 'ok' : 'degraded',
            'time'   => date('c'),
            'db'     => $db_ok ? 'connected' : 'disconnected',
            'version' => '2.0.0',
        ]);

    default:
        api_response([
            'name'    => 'VatanParvar Yaypan API',
            'version' => '2.0.0',
            'endpoints' => [
                'GET /api/?action=tariffs',
                'GET /api/?action=tickets',
                'GET /api/?action=stats',
                'GET /api/?action=top-rating',
                'GET /api/?action=blog&page=1',
                'GET /api/?action=me',
                'POST /api/?action=login',
                'POST /api/?action=register',
                'POST /api/?action=contact',
                'GET /api/?action=check-invoice&code=XXXXXXXXXXXX',
                'GET /api/?action=health',
            ],
        ]);
}
