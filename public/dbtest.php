<?php
header('Content-Type: text/plain; charset=utf-8');
echo "HOST=".getenv('MYSQLHOST')."\n";
echo "PORT=".getenv('MYSQLPORT')."\n";
echo "DB=".getenv('MYSQLDATABASE')."\n";
echo "USER=".getenv('MYSQLUSER')."\n";
try {
  $pdo = new PDO(
    "mysql:host=".getenv('MYSQLHOST').";port=".getenv('MYSQLPORT').";dbname=".getenv('MYSQLDATABASE').";charset=utf8mb4",
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
  );
  $pdo->query('SELECT 1');
  echo "DB OK\n";
} catch (Throwable $e) {
  echo "DB FAIL: ".$e->getMessage()."\n";
}
