<?php

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
                $dbHost = getenv('MYSQLHOST') ?: 'localhost';
                $dbName = getenv('MYSQLDATABASE') ?: 'webshop';
                $dbUser = getenv('MYSQLUSER') ?: 'root';
                $dbPass = getenv('MYSQLPASSWORD') ?: '';
                $dbPort = getenv('MYSQLPORT') ?: '3306';

                $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);

                $items = $args['items'] ?? [];
                if (!is_array($items) || count($items) === 0) {
                    throw new RuntimeException('Items array is required and must be non-empty.');
                }

                // Compute total by summing first price per product_id * qty
                $priceStmt = $pdo->prepare(
                    "SELECT amount FROM prices WHERE product_id = ? ORDER BY id ASC LIMIT 1"
                );

                $total = 0.0;
                foreach ($items as $it) {
                    if (!isset($it['product_id'], $it['quantity'])) {
                        throw new RuntimeException('Each item must have product_id and quantity.');
                    }
                    $pid = (string)$it['product_id'];
                    $qty = (int)$it['quantity'];

                    $priceStmt->execute([$pid]);
                    $amount = $priceStmt->fetchColumn();
                    if ($amount === false) {
                        throw new RuntimeException("Price not found for product_id={$pid}");
                    }
                    $total += ((float)$amount) * $qty;
                }

                $pdo->beginTransaction();
                try {
                    // Insert into orders (customer_name can be NULL)
                    $orderStmt = $pdo->prepare(
                        "INSERT INTO orders (customer_name, total_price, created_at)
                         VALUES (NULL, ?, NOW())"
                    );
                    $orderStmt->execute([$total]);
                    $orderId = (int)$pdo->lastInsertId();

                    // Insert order_items
                    $itemStmt = $pdo->prepare(
                        "INSERT INTO order_items (order_id, product_id, quantity)
                         VALUES (?, ?, ?)"
                    );

                    foreach ($items as $it) {
                        $pid = (string)$it['product_id'];   // product_id is VARCHAR in DB
                        $qty = (int)$it['quantity'];
                        $itemStmt->execute([$orderId, $pid, $qty]);
                    }

                    $pdo->commit();
                    return true;
                } catch (Throwable $e) {
                    $pdo->rollBack();

                    // If APP_DEBUG is on, bubble up the real error so you see it in response
                    $debugEnv = getenv('APP_DEBUG') ?: getenv('DEBUG');
                    $debugOn  = is_string($debugEnv) && ($debugEnv === '1' || strtolower($debugEnv) === 'true');

                    if ($debugOn) {
                        throw $e;
                    }

                    // Otherwise, log quietly and return false
                    @file_put_contents(
                        __DIR__ . '/../error_log.txt',
                        "[" . date('c') . "] CreateOrder ERROR: {$e->getMessage()}\n",
                        FILE_APPEND
                    );
                    return false;
                }
            },
        ];
    }
}
