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
            'resolve' => function ($root, $args) {
                $host = getenv('MYSQLHOST') ?: 'localhost';
                $db   = getenv('MYSQLDATABASE') ?: 'webshop';
                $user = getenv('MYSQLUSER') ?: 'root';
                $pass = getenv('MYSQLPASSWORD') ?: '';
                $port = getenv('MYSQLPORT') ?: '3306';

                $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4;ssl-mode=REQUIRED";

                try {
                    $pdo = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);

                    $pdo->beginTransaction();

                    // Insert order (only created_at)
                    $pdo->exec("INSERT INTO orders (created_at) VALUES (NOW())");
                    $orderId = (int)$pdo->lastInsertId();

                    // Prepare helpers
                    $stmtPrice = $pdo->prepare("
                        SELECT amount
                        FROM prices
                        WHERE product_id = ?
                        ORDER BY id ASC
                        LIMIT 1
                    ");
                    $stmtItem = $pdo->prepare("
                        INSERT INTO order_items (order_id, product_id, quantity, unit_price)
                        VALUES (:order_id, :product_id, :quantity, :unit_price)
                    ");

                    foreach ($args['items'] as $item) {
                        $productId = $item['product_id'];
                        $qty       = (int)$item['quantity'];

                        $unit = 0.0;
                        $stmtPrice->execute([$productId]);
                        if ($row = $stmtPrice->fetch()) {
                            $unit = (float)$row['amount'];
                        }

                        $stmtItem->execute([
                            ':order_id'   => $orderId,
                            ':product_id' => $productId,
                            ':quantity'   => $qty,
                            ':unit_price' => $unit,
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
                        '['.date('c')."] CreateOrder error: ".$e->getMessage()."\n",
                        FILE_APPEND
                    );
                    return false;
                }
            },
        ];
    }
}
