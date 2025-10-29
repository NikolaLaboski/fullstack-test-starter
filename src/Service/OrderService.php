<?php
// src/Service/OrderService.php
namespace App\Service;

use App\Infrastructure\Database;
use App\Repository\ProductRepository;
use Throwable;

final class OrderService
{
    /**
     * @param array<int,array{product_id:string,quantity:int}> $items
     */
    public function place(array $items): bool
    {
        if (empty($items)) return false;

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();

            $total = 0.0;

            foreach ($items as $it) {
                $pid = (string)$it['product_id'];
                $qty = (int)$it['quantity'];

                $product = ProductRepository::find($pid);
                if (!$product || !$product->canBeOrdered()) {
                    throw new \RuntimeException("Product {$pid} not orderable");
                }

                // polymorphic calls
                $unit = $product->getUnitPrice();
                $sku  = $product->buildSkuKey();
                // $sku not stored now, but shows delegation/behavior; keep if you extend schema.

                $total += $unit * $qty;
            }

            // Insert order
            $stmtOrder = $pdo->prepare(
                "INSERT INTO orders (customer_name, total_price, created_at)
                 VALUES (NULL, ?, NOW())"
            );
            $stmtOrder->execute([$total]);
            $orderId = (int)$pdo->lastInsertId();

            // Insert items
            $stmtItem = $pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, quantity)
                 VALUES (?, ?, ?)"
            );
            foreach ($items as $it) {
                $stmtItem->execute([
                    $orderId,
                    (string)$it['product_id'],
                    (int)$it['quantity'],
                ]);
            }

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            @file_put_contents(__DIR__ . '/../../error_log.txt',
                '['.date('c')."] OrderService: ".$e->getMessage()."\n",
                FILE_APPEND
            );
            return false;
        }
    }
}
