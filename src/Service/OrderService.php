<?php
// src/Service/OrderService.php

namespace App\Service;

use App\Infrastructure\Database;
use PDO;
use Throwable;

final class OrderService
{
    /**
     * @param array<int,array{product_id:string,quantity:int}> $items
     */
    public function place(array $items): bool
    {
        if (empty($items)) {
            return false;
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            // Calculate total: sum of the first price.amount per product_id
            $total = 0.0;
            $stmtPrice = $pdo->prepare(
                "SELECT amount FROM prices WHERE product_id = ? ORDER BY id ASC LIMIT 1"
            );

            foreach ($items as $it) {
                $pid = (string)$it['product_id'];
                $qty = (int)$it['quantity'];

                $stmtPrice->execute([$pid]);
                $amount = $stmtPrice->fetchColumn();

                if ($amount === false) {
                    // No price found; treat as 0
                    $amount = 0;
                }

                $total += ((float)$amount) * $qty;
            }

            // Insert order
            $stmtOrder = $pdo->prepare(
                "INSERT INTO orders (customer_name, total_price, created_at)
                 VALUES (NULL, ?, NOW())"
            );
            $stmtOrder->execute([$total]);

            $orderId = (int)$pdo->lastInsertId();

            // Insert order_items
            $stmtItem = $pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, quantity)
                 VALUES (?, ?, ?)"
            );

            foreach ($items as $it) {
                $stmtItem->execute([
                    $orderId,
                    (string)$it['product_id'], // VARCHAR column
                    (int)$it['quantity'],
                ]);
            }

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // writing minimal error line
            @file_put_contents(__DIR__ . '/../../error_log.txt',
                '['.date('c')."] OrderService: ".$e->getMessage()."\n",
                FILE_APPEND
            );
            throw $e; 
        }
    }
}
