<?php
namespace App\Repository;

use App\Infrastructure\Database;
use App\Model\AbstractProduct;
use App\Model\TechProduct;
use App\Model\ClothingProduct;
use App\Model\AccessoryProduct;
use PDO;

final class ProductRepository
{
    private static function factory(array $row): AbstractProduct
    {
        $cat = strtolower((string)($row['category'] ?? ''));
        return match (true) {
            str_contains($cat, 'tech')   => new TechProduct($row),
            str_contains($cat, 'cloth')  => new ClothingProduct($row),
            str_contains($cat, 'access') => new AccessoryProduct($row),
            default                      => new TechProduct($row),
        };
    }

    /** @return AbstractProduct[] */
    public static function all(): array
    {
        $pdo  = Database::pdo();
        $rows = $pdo->query("SELECT * FROM products")->fetchAll() ?: [];

        return array_map(function (array $row) use ($pdo) {
            $p = self::factory($row);
            $p->setAttributes(self::attributes($pdo, $row['id']));
            $p->setPrices(self::prices($pdo, $row['id']));
            $p->setGallery(self::gallery($pdo, $row['id'], $row['image'] ?? null));
            return $p;
        }, $rows);
    }

    public static function find(string $id): ?AbstractProduct
    {
        $pdo = Database::pdo();
        $st  = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) return null;

        $p = self::factory($row);
        $p->setAttributes(self::attributes($pdo, $row['id']));
        $p->setPrices(self::prices($pdo, $row['id']));
        $p->setGallery(self::gallery($pdo, $row['id'], $row['image'] ?? null));
        return $p;
    }

    /** @return array<int, array<string,mixed>> */
    private static function attributes(PDO $pdo, string $productId): array
    {
        $st = $pdo->prepare("SELECT id, name, type FROM attributes WHERE product_id = ?");
        $st->execute([$productId]);
        $attrs = $st->fetchAll() ?: [];

        foreach ($attrs as &$attr) {
            $sti = $pdo->prepare("SELECT id, displayValue, value FROM attribute_items WHERE attribute_id = ?");
            $sti->execute([$attr['id']]);
            $attr['items'] = $sti->fetchAll() ?: [];
        }
        return $attrs;
    }

    /** @return array<int, array{amount: float, currency_label: ?string, currency_symbol: ?string}> */
    private static function prices(PDO $pdo, string $productId): array
    {
        $st = $pdo->prepare("SELECT amount, currency_label, currency_symbol FROM prices WHERE product_id = ?");
        $st->execute([$productId]);
        $rows = $st->fetchAll();
        if ($rows) {
            return array_map(fn($r) => [
                'amount'          => (float)$r['amount'],
                'currency_label'  => $r['currency_label'] ?? null,
                'currency_symbol' => $r['currency_symbol'] ?? null,
            ], $rows);
        }

        $sql = "SELECT p.amount, c.label AS currency_label, c.symbol AS currency_symbol
                FROM prices p JOIN currencies c ON c.id = p.currency_id
                WHERE p.product_id = ?";
        $st = $pdo->prepare($sql);
        $st->execute([$productId]);
        $rows = $st->fetchAll() ?: [];
        return array_map(fn($r) => [
            'amount'          => (float)$r['amount'],
            'currency_label'  => $r['currency_label'] ?? null,
            'currency_symbol' => $r['currency_symbol'] ?? null,
        ], $rows);
    }

    /** @return string[] */
    private static function gallery(PDO $pdo, string $productId, ?string $fallback): array
    {
        $st = $pdo->prepare("SELECT url FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
        $st->execute([$productId]);
        $rows = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];

        if (!$rows) {
            $st = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? ORDER BY id ASC");
            $st->execute([$productId]);
            $rows = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }
        if (!$rows && $fallback) $rows = [$fallback];
        return $rows;
    }
}
