<?php
namespace App\Service;

use PDO;

final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }

        $host = getenv('MYSQLHOST') ?: 'localhost';
        $db   = getenv('MYSQLDATABASE') ?: 'webshop';
        $user = getenv('MYSQLUSER') ?: 'root';
        $pass = getenv('MYSQLPASSWORD') ?: '';
        $port = getenv('MYSQLPORT') ?: '3306';

        // Railway usually requires SSL. mysqlnd supports ssl-mode DSN.
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4;ssl-mode=REQUIRED";

        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return self::$pdo;
    }
}