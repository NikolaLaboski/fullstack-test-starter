// --- DB diagnostic (extended) ---
if ($path === '/dbtest') {
    header('Content-Type: text/plain; charset=utf-8');

    $host = getenv('MYSQLHOST');
    $port = (int) getenv('MYSQLPORT');
    $db   = getenv('MYSQLDATABASE');
    $user = getenv('MYSQLUSER');
    $pass = getenv('MYSQLPASSWORD');

    echo "HOST=$host\nPORT=$port\nDB=$db\nUSER=$user\n";

    // 1) DNS resolve
    $ip = gethostbyname($host);
    echo "RESOLVED_IP=$ip\n";

    // 2) Raw TCP socket test (без SSL, само да видиме дали се отвора портот)
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

    // 3) PDO со краток timeout
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
            // Ако серверот форсира SSL и клиентот без SSL — може да те ритне.
            // Овие две линии му кажуваат "не верификувај сертификат".
            // Ако Railway не бара SSL, ќе ги игнорира.
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            PDO::MYSQL_ATTR_SSL_CA => null,
        ]);
        $pdo->query('SELECT 1');
        echo "PDO=OK\n";
    } catch (Throwable $e) {
        echo "PDO=FAIL: ".$e->getMessage()."\n";
    }

    exit;
}
