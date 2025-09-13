<?php
// public/index.php — health, /dbtest proxy (public/graphql/index.php)

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path    = rawurldecode($rawPath);
$path    = rtrim($path, " \t\n\r\0\x0B");
if ($path !== '/') { $path = rtrim($path, '/'); }
$method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ---------- CORS ---------- */
$allowed = ['https://scweb-shop.netlify.app', 'http://localhost:5173'];
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $allowed, true)) {
  header("Access-Control-Allow-Origin: $origin");
  header('Vary: Origin');
  header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Apollo-Require-Preflight');
if ($method === 'OPTIONS') { http_response_code(204); exit; }

/* ---------- Health ---------- */
if ($path === '/' && $method === 'GET') {
  header('Content-Type: text/plain; charset=utf-8');
  echo "OK";
  exit;
}

/* ---------- DB DIAGNOSTIC: /dbtest ---------- */
if ($path === '/dbtest') {
  header('Content-Type: text/plain; charset=utf-8');

  $host = getenv('MYSQLHOST') ?: '';
  $port = (int)(getenv('MYSQLPORT') ?: 3306);
  $db   = getenv('MYSQLDATABASE') ?: '';
  $user = getenv('MYSQLUSER') ?: '';
  $pass = getenv('MYSQLPASSWORD') ?: '';

  echo "HOST=$host\nPORT=$port\nDB=$db\nUSER=$user\n";
  echo 'PHP_EXT: pdo_mysql='.(extension_loaded('pdo_mysql')?'yes':'no')
     .' openssl='.(extension_loaded('openssl')?'yes':'no')
     .' mysqli='.(extension_loaded('mysqli')?'yes':'no')."\n";

  $ip = @gethostbyname($host);
  echo "RESOLVED_IP=$ip\n";

  $t0 = microtime(true);
  $errno = 0; $errstr = '';
  $sock = @fsockopen($host, $port, $errno, $errstr, 4.0);
  $t1 = microtime(true);
  if ($sock) { echo "SOCKET=OK (".number_format(($t1-$t0)*1000,1)." ms)\n"; fclose($sock); }
  else { echo "SOCKET=FAIL ($errno) $errstr\n"; }

  
  try {
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4;ssl-mode=REQUIRED";
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_TIMEOUT => 5,
    ]);
    $pdo->query('SELECT 1');
    echo "PDO_SSL_REQUIRED=OK\n";
    exit;
  } catch (Throwable $e) {
    echo "PDO_SSL_REQUIRED=FAIL: ".$e->getMessage()."\n";
  }

  
  try {
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_TIMEOUT => 5,
      PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ]);
    $pdo->query('SELECT 1');
    echo "PDO_NO_VERIFY=OK\n";
  } catch (Throwable $e) {
    echo "PDO_NO_VERIFY=FAIL: ".$e->getMessage()."\n";
  }

  echo "NOTE: Ensure MYSQLDATABASE matches the exact DB name in Railway.\n";
  exit;
}


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
  
  require __DIR__ . '/graphql/index.php';
  exit;
}

/* ---------- 404 ---------- */
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not Found: {$path}";
