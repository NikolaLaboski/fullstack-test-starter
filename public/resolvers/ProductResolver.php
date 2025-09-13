<?php
// ProductResolver.php
// Provides read operations for products, including attributes, prices, stock flags,
// normalized gallery arrays, and a convenience 'price' (first price amount).

require_once __DIR__ . '/../resolvers/AttributeResolver.php';

class ProductResolver
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

    private static function getGalleryForProduct($productId)
    {
        $db = self::connect();
        $stmt = $db->prepare("SELECT url FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $rows ?: [];
    }

    public static function getAllProducts()
    {
        $db = self::connect();
        $stmt = $db->query("SELECT * FROM products");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as &$product) {
            try {
                $product['attributes'] = AttributeResolver::getAttributesForProduct($product['id']);
                $product['prices'] = self::getPricesForProduct($product['id']);
                $product['inStock'] = (bool)$product['inStock'];

                $gallery = self::getGalleryForProduct($product['id']);
                if (empty($gallery) && !empty($product['image'])) {
                    $gallery = [$product['image']];
                }
                $product['gallery'] = $gallery;

                $product['price'] = isset($product['prices'][0]['amount'])
                    ? (float)$product['prices'][0]['amount'] : null;

            } catch (\Throwable $e) {
                $product['attributes'] = [];
                $product['prices'] = [];
                $product['gallery'] = [];
                file_put_contents(__DIR__ . '/../error_log.txt',
                    "[ERROR] Product ID {$product['id']}: {$e->getMessage()}\n",
                    FILE_APPEND
                );
            }
        }

        return $products;
    }

    public static function getProductById($id)
    {
        $db = self::connect();
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            try {
                $product['attributes'] = AttributeResolver::getAttributesForProduct($product['id']);
                $product['prices'] = self::getPricesForProduct($product['id']);
                $product['inStock'] = (bool)$product['inStock'];

                $gallery = self::getGalleryForProduct($product['id']);
                if (empty($gallery) && !empty($product['image'])) {
                    $gallery = [$product['image']];
                }
                $product['gallery'] = $gallery;

                $product['price'] = isset($product['prices'][0]['amount'])
                    ? (float)$product['prices'][0]['amount'] : null;

            } catch (\Throwable $e) {
                $product['attributes'] = [];
                $product['prices'] = [];
                $product['gallery'] = [];
                file_put_contents(__DIR__ . '/../error_log.txt',
                    "[ERROR] Product ID {$product['id']}: {$e->getMessage()}\n",
                    FILE_APPEND
                );
            }
        }

        return $product;
    }

    private static function getPricesForProduct($productId)
    {
        $db = self::connect();
        $stmt = $db->prepare("SELECT amount, currency_label, currency_symbol FROM prices WHERE product_id = ?");
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
