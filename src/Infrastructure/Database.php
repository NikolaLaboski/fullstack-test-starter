<?php
namespace App\Infrastructure;

use PDO;

final class Database
{
    public static function pdo(): PDO
    {
        $host = getenv('MYSQLHOST') ?: 'localhost';
        $db   = getenv('MYSQLDATABASE') ?: 'webshop';
        $user = getenv('MYSQLUSER') ?: 'root';
        $pass = getenv('MYSQLPASSWORD') ?: '';
        $port = getenv('MYSQLPORT') ?: '3306';

        // Railway requires TLS
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4;ssl-mode=REQUIRED";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdo;
    }
}