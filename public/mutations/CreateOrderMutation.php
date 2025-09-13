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

                $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4;ssl-mode=REQUIRED";

                try {
                    if (empty($args['items']) || !is_array($args['items'])) {
                        throw new InvalidArgumentException('items is required');
                    }

                    $pdo = new PDO($dsn, $dbUser, $dbPass, [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_TIMEOUT            => 5,
                    ]);

                    $pdo->beginTransaction();

                    $pdo->exec("INSERT INTO orders (total) VALUES (0.00)");
                    $orderId = (int)$pdo->lastInsertId();

                    $findPrice = $pdo->prepare("
                        SELECT amount, currency_label, currency_symbol
                        FROM prices
                        WHERE product_id = ?
                        ORDER BY id ASC
                        LIMIT 1
                    ");

                    $insertItem = $pdo->prepare("
                        INSERT INTO order_items
                            (order_id, product_id, quantity, unit_amount, currency_label, currency_symbol)
                        VALUES
                            (:order_id, :product_id, :quantity, :unit_amount, :currency_label, :currency_symbol)
                    ");

                    $total = 0.0;

                    foreach ($args['items'] as $item) {
                        $pid = (string)$item['product_id'];
                        $qty = (int)$item['quantity'];
                        if ($qty <= 0) {
                            throw new InvalidArgumentException('quantity must be > 0');
                        }

                        $findPrice->execute([$pid]);
                        $priceRow = $findPrice->fetch();

                        $amount = $priceRow ? (float)$priceRow['amount'] : 0.0;
                        $clabel = $priceRow['currency_label'] ?? 'USD';
                        $csym   = $priceRow['currency_symbol'] ?? '$';

                        $insertItem->execute([
                            ':order_id'        => $orderId,
                            ':product_id'      => $pid,
                            ':quantity'        => $qty,
                            ':unit_amount'     => $amount,
                            ':currency_label'  => $clabel,
                            ':currency_symbol' => $csym,
                        ]);

                        $total += $amount * $qty;
                    }

                    $upd = $pdo->prepare("UPDATE orders SET total = :total WHERE id = :id");
                    $upd->execute([':total' => $total, ':id' => $orderId]);

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
