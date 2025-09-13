<?php
// Minimal router + health + GraphQL entry

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

// --- DB diagnostic: /dbtest ---
if ($path === '/dbtest') {
    header('Content-Type: text/plain; charset=utf-8');

    // ENV with hardcoded fallback (за да нема забуна)
    $host = getenv('MYSQLHOST') ?: 'caboose.proxy.rlwy.net';
    $port = (int) (getenv('MYSQLPORT') ?: 30319);
    $db   = getenv('MYSQLDATABASE') ?: 'railway';
    $user = getenv('MYSQLUSER') ?: 'root';
    $pass = getenv('MYSQLPASSWORD') ?: 'adWUVTiqMHkvVkeLeoSUmKcQLQOowQVB';

    echo "HOST=$host\nPORT=$port\nDB=$db\nUSER=$user\n";

    // DNS
    $ip = gethostbyname($host);
    echo "RESOLVED_IP=$ip\n";

    // Raw TCP test
    $errno = 0; $errstr = '';
    $t0 = microtime(true);
    $sock = @fsockopen($host, $port, $errno, $errstr, 3.0);
    $t1 = microtime(true);
    if ($sock) {
        echo "SOCKET=OK (".number_format(($t1-$t0)*1000,1)." ms)\n";
        fclose($sock);
    } else {
        echo "SOCKET=FAIL ($errno) $errstr\n";
    }

    // PDO test (со SSL CA за Railway proxy)
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
            PDO::MYSQL_ATTR_SSL_CA       => '/etc/ssl/certs/ca-certificates.crt',
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ]);
        $pdo->query('SELECT 1');
        echo "PDO=OK\n";
    } catch (Throwable $e) {
        echo "PDO=FAIL: ".$e->getMessage()."\n";
    }
    exit;
}

// --- GraphQL endpoint: /graphql ---
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

// --- 404 fallback ---
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not Found: {$path}";
