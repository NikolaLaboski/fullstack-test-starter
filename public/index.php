<?php
// Router: health, GraphQL, dbtest

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path    = rawurldecode($rawPath);

// normalize
$path = rtrim($path, " \t\n\r\0\x0B");
if ($path !== '/') {
    $path = rtrim($path, '/');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- CORS ---
$allowed = ['https://scweb-shop.netlify.app', 'http://localhost:5173'];
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Apollo-Require-Preflight');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Health check ---
if ($path === '/' && $method === 'GET') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "OK";
    exit;
}

// --- GraphQL endpoint ---
if ($path === '/graphql') {
    if ($method === 'GET') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "GraphQL endpoint ready. Use POST with JSON body.";
        exit;
    }
    if ($method !== 'POST') {
        header('Allow: POST, GET, OPTIONS');
        http_response_code(405);
        echo "Method Not Allowed";
        exit;
    }

    require_once __DIR__ . '/../vendor/autoload.php';
    echo App\Controller\GraphQL::handle();
    exit;
}

// --- DB diagnostic (optional) ---
if ($path === '/dbtest') {
    header('Content-Type: text/plain; charset=utf-8');
    try {
        $pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('MYSQLHOST'),
                getenv('MYSQLPORT'),
                getenv('MYSQLDATABASE')
            ),
            getenv('MYSQLUSER'),
            getenv('MYSQLPASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo "✅ DB connection OK\n";
    } catch (Throwable $e) {
        echo "❌ DB connection failed: " . $e->getMessage() . "\n";
    }
    exit;
}

// --- 404 fallback ---
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not Found: {$path}";
