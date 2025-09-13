<?php
// Minimal router + health + GraphQL + deep DB diagnostics

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

// --- DB diagnostic (multi-try) at "/dbtest"
if ($path === '/dbtest') {
    header('Content-Type: text/plain; charset=utf-8');

    // Read from ENV with fallback to your public proxy creds
    $host = getenv('MYSQLHOST')      ?: 'caboose.proxy.rlwy.net';
    $port = (int)(getenv('MYSQLPORT') ?: 30319);
    $db   = getenv('MYSQLDATABASE')  ?: 'railway'; // CONFIRM this matches "Connect" screen!
    $user = getenv('MYSQLUSER')      ?: 'root';
    $pass = getenv('MYSQLPASSWORD')  ?: 'adWUVTiqMHkvVkeLeoSUmKcQLQOowQVB';

    echo "HOST=$host\nPORT=$port\nDB=$db\nUSER=$user\n";
    echo "PHP_EXT: pdo_mysql=".(extension_loaded('pdo_mysql')?'yes':'no')." openssl=".(extension_loaded('openssl')?'yes':'no')." mysqli=".(function_exists('mysqli_init')?'yes':'no')."\n";

    // DNS + raw socket ping
    $ip = gethostbyname($host);
    echo "RESOLVED_IP=$ip\n";
    $errno = 0; $errstr = '';
    $t0 = microtime(true);
    $sock = @fsockopen($host, $port, $errno, $errstr, 3.0);
    $t1 = microtime(true);
    if ($sock) { echo "SOCKET=OK (".number_format(($t1-$t0)*1000,1)." ms)\n"; fclose($sock); }
    else { echo "SOCKET=FAIL ($errno) $errstr\n"; }

    // 1) PDO no-SSL
    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
            $user, $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]
        );
        $pdo->query('SELECT 1');
        echo "PDO_NO_SSL=OK\n";
    } catch (Throwable $e) {
        echo "PDO_NO_SSL=FAIL: ".$e->getMessage()."\n";
    }

    // 2) PDO with SSL CA (typical for Railway public proxy)
    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
            $user, $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
                PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            ]
        );
        $pdo->query('SELECT 1');
        echo "PDO_SSL_CA=OK\n";
    } catch (Throwable $e) {
        echo "PDO_SSL_CA=FAIL: ".$e->getMessage()."\n";
    }

    // 3) mysqli with SSL (гивс мори-деталд ерорс)
    if (function_exists('mysqli_init')) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $mysqli = mysqli_init();
        if (method_exists($mysqli, 'ssl_set')) {
            $mysqli->ssl_set(null, null, '/etc/ssl/certs/ca-certificates.crt', null, null);
        }
        $ok = @$mysqli->real_connect($host, $user, $pass, $db, $port, null, MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT);
        if ($ok) {
            $res = $mysqli->query('SELECT 1');
            echo "MYSQLI_SSL=OK\n";
            $mysqli->close();
        } else {
            echo "MYSQLI_SSL=FAIL: ".mysqli_connect_error()."\n";
        }
    } else {
        echo "MYSQLI: not available\n";
    }

    echo "NOTE: Ensure MYSQLDATABASE matches the exact DB name shown in Railway → Database → Connect.\n";
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

// --- 404 fallback ---
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not Found: {$path}";
