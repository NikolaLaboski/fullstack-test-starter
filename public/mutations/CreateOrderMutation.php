<?php
use GraphQL\Type\Definition\Type;

class CreateOrderMutation
{
    public static function getMutation(): array
    {
        return ['createOrder' => self::getDefinition()];
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
                $pdo = null;
                $debugOn = in_array(strtolower((string)(getenv('APP_DEBUG') ?: '0')), ['1','true'], true);

                try {
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
                        self::elog('CreateOrder: empty items');
                        return false;
                    }

                    // calc total
                    $stmtPrice  = $pdo->prepare("SELECT amount FROM prices WHERE product_id = ? ORDER BY id ASC LIMIT 1");
                    $stmtExists = $pdo->prepare("SELECT 1 FROM products WHERE id = ? LIMIT 1");

                    $total = 0.0;
                    foreach ($items as $it) {
                        $pid = (string)$it['product_id'];
                        $qty = (int)$it['quantity'];
                        if ($qty < 1) { throw new RuntimeException("quantity < 1 for {$pid}"); }

                        $stmtExists->execute([$pid]);
                        if (!$stmtExists->fetchColumn()) {
                            throw new RuntimeException("Unknown product_id: {$pid}");
                        }

                        $stmtPrice->execute([$pid]);
                        $row = $stmtPrice->fetch();
                        $price = $row && isset($row['amount']) ? (float)$row['amount'] : 0.0;
                        $total += $price * $qty;
                    }

                    $pdo->beginTransaction();

                    $now = date('Y-m-d H:i:s');
                    $customerName = ''; // за NOT NULL колони

                    // обиди се со total_price, па fallback total
                    $orderId = self::insertOrder($pdo, [
                        'customer_name' => $customerName,
                        'total_price'   => $total,
                        'total'         => $total,
                        'created_at'    => $now,
                    ]);

                    $stmtItem = $pdo->prepare("
                        INSERT INTO order_items (order_id, product_id, quantity)
                        VALUES (:order_id, :product_id, :quantity)
                    ");
                    foreach ($items as $it) {
                        $stmtItem->execute([
                            ':order_id'   => $orderId,
                            ':product_id' => (string)$it['product_id'],
                            ':quantity'   => (int)$it['quantity'],
                        ]);
                    }

                    $pdo->commit();
                    return true;

                } catch (\Throwable $e) {
                    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
                    self::elog('[CreateOrder][EXCEPTION] '.$e->getMessage());
                    if ($debugOn) { throw $e; } // ← ќе ја видиш грешката во GraphQL response
                    return false;
                }
            },
        ];
    }

    private static function insertOrder(PDO $pdo, array $data): int
    {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO orders (customer_name, total_price, created_at)
                VALUES (:customer_name, :total_price, :created_at)
            ");
            $stmt->execute([
                ':customer_name' => $data['customer_name'],
                ':total_price'   => $data['total_price'],
                ':created_at'    => $data['created_at'],
            ]);
            return (int)$pdo->lastInsertId();
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'Unknown column') !== false && stripos($e->getMessage(), 'total_price') !== false) {
                $stmt = $pdo->prepare("
                    INSERT INTO orders (customer_name, total, created_at)
                    VALUES (:customer_name, :total, :created_at)
                ");
                $stmt->execute([
                    ':customer_name' => $data['customer_name'],
                    ':total'         => $data['total'],
                    ':created_at'    => $data['created_at'],
                ]);
                return (int)$pdo->lastInsertId();
            }
            throw $e;
        }
    }

    private static function elog(string $msg): void
    {
        @file_put_contents(__DIR__ . '/../error_log.txt', '['.date('Y-m-d H:i:s')."] {$msg}\n", FILE_APPEND);
    }
}
