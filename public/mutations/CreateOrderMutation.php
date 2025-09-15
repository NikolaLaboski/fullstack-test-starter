<?php
// mutations/CreateOrderMutation.php

use GraphQL\Type\Definition\Type;

class CreateOrderMutation
{
    public static function getMutation(): array
    {
        return [
            'createOrder' => self::getDefinition(),
        ];
    }

    public static function getDefinition(): array
    {
        return [
            'type' => Type::nonNull(Type::boolean()),
            'args' => [
                'items' => [
                    'type' => Type::nonNull(
                        Type::listOf(
                            OrderInputSchema::getOrderItemInputType()
                        )
                    ),
                ],
            ],
            'resolve' => function ($root, $args): bool {
                try {
                    // --- DB from ENV (works on Railway) ---
                    $dbHost = getenv('MYSQLHOST') ?: 'localhost';
                    $dbName = getenv('MYSQLDATABASE') ?: 'webshop';
                    $dbUser = getenv('MYSQLUSER') ?: 'root';
                    $dbPass = getenv('MYSQLPASSWORD') ?: '';
                    $dbPort = getenv('MYSQLPORT') ?: '3306';

                    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
                    $pdo = new PDO($dsn, $dbUser, $dbPass, [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);

                    $items = $args['items'] ?? [];
                    if (!is_array($items) || count($items) === 0) {
                        return false;
                    }

                    // 1) пресметај total од prices (земаме прва цена за производ)
                    $stmtPrice   = $pdo->prepare("SELECT amount FROM prices WHERE product_id = ? ORDER BY id ASC LIMIT 1");
                    $stmtExists  = $pdo->prepare("SELECT 1 FROM products WHERE id = ? LIMIT 1");

                    $total = 0.0;
                    foreach ($items as $item) {
                        $pid = (string)$item['product_id'];
                        $qty = (int)$item['quantity'];
                        if ($qty < 1) { return false; }

                        // постои ли product
                        $stmtExists->execute([$pid]);
                        if (!$stmtExists->fetchColumn()) {
                            throw new RuntimeException("Unknown product_id: {$pid}");
                        }

                        // цена
                        $stmtPrice->execute([$pid]);
                        $row = $stmtPrice->fetch();
                        $price = $row && isset($row['amount']) ? (float)$row['amount'] : 0.0;

                        $total += $price * $qty;
                    }

                    // 2) INSERT во orders
                    // твојата табела: orders(id, customer_name, total_price, created_at)
                    $pdo->beginTransaction();

                    $stmtOrder = $pdo->prepare("
                        INSERT INTO orders (customer_name, total_price, created_at)
                        VALUES (:customer_name, :total_price, :created_at)
                    ");
                    // customer_name може да е NULL ако не го користиш
                    $stmtOrder->execute([
                        ':customer_name' => null,
                        ':total_price'   => $total,
                        ':created_at'    => date('Y-m-d H:i:s'),
                    ]);

                    $orderId = (int)$pdo->lastInsertId();
                    if ($orderId <= 0) {
                        throw new RuntimeException('Failed to insert order');
                    }

                    // 3) INSERT во order_items (id, order_id, product_id, quantity)
                    $stmtItem = $pdo->prepare("
                        INSERT INTO order_items (order_id, product_id, quantity)
                        VALUES (:order_id, :product_id, :quantity)
                    ");

                    foreach ($items as $item) {
                        $stmtItem->execute([
                            ':order_id'   => $orderId,
                            ':product_id' => (string)$item['product_id'], // string slug
                            ':quantity'   => (int)$item['quantity'],
                        ]);
                    }

                    $pdo->commit();
                    return true;

                } catch (\Throwable $e) {
                    if (isset($pdo) && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    @file_put_contents(
                        __DIR__ . '/../error_log.txt',
                        "[ERROR] CreateOrder: {$e->getMessage()}\n",
                        FILE_APPEND
                    );
                    return false;
                }
            },
        ];
    }
}
