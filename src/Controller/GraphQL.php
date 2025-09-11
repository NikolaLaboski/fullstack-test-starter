<?php

namespace App\Controller;

use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use PDO;
use RuntimeException;
use Throwable;

class GraphQL {
    public static function handle() {
        try {
            // (optional) load .env.local for local dev
            $envFile = __DIR__ . '/../../.env.local';
            if (is_file($envFile)) {
                $pairs = @parse_ini_file($envFile, false, INI_SCANNER_RAW) ?: [];
                foreach ($pairs as $k => $v) {
                    if (is_string($k)) { putenv("$k=$v"); }
                }
            }

            // PDO from ENV (same names as your Railway backend)
            $host = getenv('MYSQLHOST') ?: 'localhost';
            $db   = getenv('MYSQLDATABASE') ?: 'webshop';
            $user = getenv('MYSQLUSER') ?: 'root';
            $pass = getenv('MYSQLPASSWORD') ?: '';
            $port = getenv('MYSQLPORT') ?: '3306';
            $dsn  = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Query
            $queryType = new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'echo' => [
                        'type' => Type::string(),
                        'args' => ['message' => ['type' => Type::string()]],
                        'resolve' => static fn($root, array $args): string =>
                            $root['prefix'] . $args['message'],
                    ],
                ],
            ]);

            // EXACTLY like your old backend input
            $orderItemInput = new InputObjectType([
                'name'   => 'OrderItemInput',
                'fields' => [
                    'product_id' => ['type' => Type::nonNull(Type::int())],
                    'quantity'   => ['type' => Type::nonNull(Type::int())],
                ],
            ]);

            // Mutation
            $mutationType = new ObjectType([
                'name'   => 'Mutation',
                'fields' => [
                    'sum' => [
                        'type' => Type::int(),
                        'args' => [
                            'x' => ['type' => Type::int()],
                            'y' => ['type' => Type::int()],
                        ],
                        'resolve' => static fn($root, array $args): int => $args['x'] + $args['y'],
                    ],

                    // createOrder(items: [OrderItemInput!]!, customerName: String, address: String): Boolean!
                    'createOrder' => [
                        'type' => Type::nonNull(Type::boolean()),
                        'args' => [
                            'items'        => ['type' => Type::nonNull(Type::listOf(Type::nonNull($orderItemInput)))],
                            'customerName' => ['type' => Type::string()],
                            'address'      => ['type' => Type::string()],
                        ],
                        'resolve' => static function ($root, array $args) use ($pdo): bool {
                            try {
                                $pdo->beginTransaction();

                                // Insert order (minimal columns per your legacy signature)
                                $stmt = $pdo->prepare(
                                    "INSERT INTO orders (customer_name, address, created_at)
                                     VALUES (?, ?, NOW())"
                                );
                                $stmt->execute([
                                    $args['customerName'] ?? null,
                                    $args['address'] ?? null,
                                ]);
                                $orderId = (int)$pdo->lastInsertId();

                                // Insert items
                                $ins = $pdo->prepare(
                                    "INSERT INTO order_items (order_id, product_id, quantity)
                                     VALUES (?, ?, ?)"
                                );
                                foreach ($args['items'] as $it) {
                                    $ins->execute([
                                        $orderId,
                                        (int)$it['product_id'],
                                        (int)$it['quantity'],
                                    ]);
                                }

                                $pdo->commit();
                                return true;
                            } catch (Throwable $e) {
                                if ($pdo->inTransaction()) {
                                    $pdo->rollBack();
                                }
                                // optionally log: error_log($e->getMessage());
                                return false;
                            }
                        },
                    ],
                ],
            ]);

            $schema = new Schema(
                (new SchemaConfig())
                    ->setQuery($queryType)
                    ->setMutation($mutationType)
            );

            $rawInput = file_get_contents('php://input');
            if ($rawInput === false) {
                throw new RuntimeException('Failed to get php://input');
            }

            $input = json_decode($rawInput, true);
            $query = $input['query'] ?? null;
            $variableValues = $input['variables'] ?? null;

            $rootValue = ['prefix' => 'You said: '];
            $result = GraphQLBase::executeQuery($schema, $query, $rootValue, null, $variableValues);
            $output = $result->toArray();
        } catch (Throwable $e) {
            $output = ['error' => ['message' => $e->getMessage()]];
        }

        header('Content-Type: application/json; charset=UTF-8');
        return json_encode($output);
    }
}
 