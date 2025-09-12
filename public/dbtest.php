<?php
$host = getenv('MYSQLHOST');
$db   = getenv('MYSQLDATABASE');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');
$port = getenv('MYSQLPORT');

echo "HOST=$host\n";
echo "PORT=$port\n";
echo "DB=$db\n";
echo "USER=$user\n";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    echo "DB OK\n";
} catch (Throwable $e) {
    echo "DB FAIL: " . $e->getMessage();
}
