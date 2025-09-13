<?php

require_once __DIR__ . '/../resolvers/AttributeResolver.php';

class ProductResolver
{
    private static function connect(): PDO
    {
        $host = getenv('MYSQLHOST') ?: 'localhost';
        $db   = getenv('MYSQLDATABASE') ?: 'webshop';
        $user = getenv('MYSQLUSER') ?: 'root';
        $pass = getenv('MYSQLPASSWORD') ?: '';
        $port = getenv('MYSQLPORT') ?: '3306';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function getAllProducts(): array
    {
        $db = self::connect();
        $products = $db->query("SELECT * FROM products")->fetchAll() ?: [];

        foreach ($products as &$product) {
            try {
                if (!isset($product['inStock']) && array_key_exists('in_stock', $product)) {
                    $product['inStock'] = (bool)$product['in_stock'];
                } elseif (isset($product['inStock'])) {
                    $product['inStock'] = (bool)$product['inStock'];
                } else {
                    $product['inStock'] = null;
                }

                $product['attributes'] = AttributeResolver::getAttributesForProduct($product['id']);
                $product['prices']     = self::getPricesForProduct($product['id']);

                $gallery = self::getGalleryForProduct($product['id']);
                if (empty($gallery) && !empty($product['image'])) {
                    $gallery = [$product['image']];
                }
                $product['gallery'] = $gallery;

                $product['price'] = isset($product['prices'][0]['amount'])
                    ? (float)$product['prices'][0]['amount'] : null;

            } catch (\Throwable $e) {
                $product['attributes'] = [];
                $product['prices']     = [];
                $product['gallery']    = [];
                @file_put_contents(__DIR__ . '/../error_log.txt',
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
        $product = $stmt->fetch();

        if (!$product) return null;

        try {
            if (!isset($product['inStock']) && array_key_exists('in_stock', $product)) {
                $product['inStock'] = (bool)$product['in_stock'];
            } elseif (isset($product['inStock'])) {
                $product['inStock'] = (bool)$product['inStock'];
            } else {
                $product['inStock'] = null;
            }

            $product['attributes'] = AttributeResolver::getAttributesForProduct($product['id']);
            $product['prices']     = self::getPricesForProduct($product['id']);

            $gallery = self::getGalleryForProduct($product['id']);
            if (empty($gallery) && !empty($product['image'])) {
                $gallery = [$product['image']];
            }
            $product['gallery'] = $gallery;

            $product['price'] = isset($product['prices'][0]['amount'])
                ? (float)$product['prices'][0]['amount'] : null;

        } catch (\Throwable $e) {
            $product['attributes'] = [];
            $product['prices']     = [];
            $product['gallery']    = [];
            @file_put_contents(__DIR__ . '/../error_log.txt',
                "[ERROR] Product ID {$product['id']}: {$e->getMessage()}\n",
                FILE_APPEND
            );
        }

        return $product;
    }

    private static function getGalleryForProduct($productId): array
    {
        $db = self::connect();
        try {
            $st = $db->prepare("SELECT url FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
            $st->execute([$productId]);
            $rows = $st->fetchAll(PDO::FETCH_COLUMN);
            return $rows ?: [];
        } catch (\Throwable $e1) {
            try {
                $st = $db->prepare("SELECT image_url AS url FROM product_images WHERE product_id = ? ORDER BY id ASC");
                $st->execute([$productId]);
                $rows = $st->fetchAll(PDO::FETCH_COLUMN);
                return $rows ?: [];
            } catch (\Throwable $e2) {
                @file_put_contents(__DIR__ . '/../error_log.txt',
                    "[ERROR] Gallery {$productId}: {$e1->getMessage()} / {$e2->getMessage()}\n",
                    FILE_APPEND
                );
                return [];
            }
        }
    }

    private static function getPricesForProduct($productId): array
    {
        $db = self::connect();

        // Try flat schema: prices(amount, currency_label, currency_symbol)
        try {
            $st = $db->prepare("SELECT amount, currency_label, currency_symbol FROM prices WHERE product_id = ?");
            $st->execute([$productId]);
            $rows = $st->fetchAll();
            if ($rows) return $rows;
        } catch (\Throwable $e) {
            // fall through
        }

        // Try normalized schema: prices(currency_id) + currencies(label, symbol)
        try {
            $sql = "SELECT p.amount, c.label AS currency_label, c.symbol AS currency_symbol
                    FROM prices p
                    JOIN currencies c ON c.id = p.currency_id
                    WHERE p.product_id = ?";
            $st = $db->prepare($sql);
            $st->execute([$productId]);
            $rows = $st->fetchAll();
            if ($rows) return $rows;
        } catch (\Throwable $e2) {
            // fall through
        }

        // Last fallback: amount only
        try {
            $st = $db->prepare("SELECT amount FROM prices WHERE product_id = ?");
            $st->execute([$productId]);
            $amts = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
            return array_map(fn($a) => ['amount' => (float)$a, 'currency_label' => null, 'currency_symbol' => null], $amts);
        } catch (\Throwable $e3) {
            @file_put_contents(__DIR__ . '/../error_log.txt',
                "[ERROR] Prices {$productId}: {$e3->getMessage()}\n",
                FILE_APPEND
            );
            return [];
        }
    }
}
