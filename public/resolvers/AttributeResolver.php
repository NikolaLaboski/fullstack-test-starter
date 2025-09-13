<?php 
// AttributeResolver.php
// Data access for attribute sets and their items for a given product.
// Notes:
// - Uses raw PDO with a local MySQL connection (credentials inline).
// - Returns plain associative arrays suitable for GraphQL field resolvers.
// - On error: logs to error_log.txt and returns an empty array (non-fatal).

class AttributeResolver
{
    /**
     * Create a new PDO connection to the webshop database.
     * Reads DSN/credentials from ENV in production, falls back to local defaults.
     *
     * @return \PDO
     */
    private static function connect()
    {
        $host = getenv('MYSQLHOST') ?: 'localhost';
        $db   = getenv('MYSQLDATABASE') ?: 'webshop';
        $user = getenv('MYSQLUSER') ?: 'root';
        $pass = getenv('MYSQLPASSWORD') ?: '';
        $port = getenv('MYSQLPORT') ?: '3306';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }

    /**
     * Fetch attributes and their items for a product.
     * Shape:
     *  [
     *    ['id'=>int, 'name'=>string, 'type'=>string|null, 'items'=>[
     *        ['id'=>int, 'displayValue'=>string, 'value'=>string], ...
     *    ]],
     *    ...
     *  ]
     *
     * @param int|string $productId Product ID as stored in DB.
     * @return array<int, array<string, mixed>> Attribute objects with 'items' populated.
     */
    public static function getAttributesForProduct($productId)
    {
        try {
            $db = self::connect();

            // Fetch attribute rows for the given product.
            $stmt = $db->prepare("SELECT id, name, type FROM attributes WHERE product_id = ?");
            $stmt->execute([$productId]);
            $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // For each attribute, fetch its items.
            foreach ($attributes as &$attribute) {
                $stmtItems = $db->prepare("SELECT id, displayValue, value FROM attribute_items WHERE attribute_id = ?");
                $stmtItems->execute([$attribute['id']]);
                $attribute['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
            }

            return $attributes;

        } catch (\Throwable $e) {
            // Log and fail soft: return empty attribute list instead of throwing.
            $log = "[" . date('Y-m-d H:i:s') . "] AttributeResolver error: " . $e->getMessage() . "\n";
            file_put_contents(__DIR__ . '/../error_log.txt', $log, FILE_APPEND);
            return [];
        }
    }
}
