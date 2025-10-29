<?php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use App\Repository\ProductRepository;
use App\Model\ProductInterface;

require_once __DIR__ . '/../schemas/AttributeSchema.php';

class ProductSchema
{
    private static $productType = null;

    public static function getQueryType()
    {
        return new ObjectType([
            'name' => 'Query',
            'fields' => function () {
                return [
                    'products' => [
                        'type' => Type::listOf(self::productType()),
                        'resolve' => function () {
                            // return model instances
                            return ProductRepository::all();
                        }
                    ],
                    'product' => [
                        'type' => self::productType(),
                        'args' => [
                            'id' => Type::nonNull(Type::string())
                        ],
                        'resolve' => function ($root, $args) {
                            return ProductRepository::find((string)$args['id']);
                        }
                    ],
                ];
            }
        ]);
    }

    public static function productType()
    {
        if (self::$productType === null) {
            self::$productType = new ObjectType([
                'name' => 'Product',
                'fields' => function () {
                    return [
                        'id' => [
                            'type' => Type::nonNull(Type::string()),
                            'resolve' => function ($p) {
                                return $p instanceof ProductInterface ? $p->getId() : (string)($p['id'] ?? '');
                            }
                        ],
                        'name' => [
                            'type' => Type::string(),
                            'resolve' => function ($p) {
                                return $p instanceof ProductInterface ? $p->getName() : ($p['name'] ?? null);
                            }
                        ],
                        'category' => [
                            'type' => Type::string(),
                            'resolve' => function ($p) {
                                return $p instanceof ProductInterface ? $p->getCategory() : ($p['category'] ?? null);
                            }
                        ],
                        'brand' => [
                            'type' => Type::string(),
                            'resolve' => function ($p) {
                                return $p instanceof ProductInterface ? $p->getBrand() : ($p['brand'] ?? null);
                            }
                        ],
                        'description' => [
                            'type' => Type::string(),
                            'resolve' => function ($p) {
                                return $p instanceof ProductInterface ? $p->getDescription() : ($p['description'] ?? null);
                            }
                        ],
                        'inStock' => [
                            'type' => Type::boolean(),
                            'resolve' => function ($p) {
                                return $p instanceof ProductInterface ? $p->isInStock() : ($p['inStock'] ?? null);
                            }
                        ],
                        'gallery' => [
                            'type' => Type::listOf(Type::string()),
                            'resolve' => function ($p) {
                                return $p instanceof ProductInterface ? $p->getGallery() : (array)($p['gallery'] ?? []);
                            }
                        ],
                        'image' => [
                            'type' => Type::string(),
                            'resolve' => function ($p) {
                                $g = $p instanceof ProductInterface ? $p->getGallery() : (array)($p['gallery'] ?? []);
                                return $g[0] ?? 'https://via.placeholder.com/220x220.png?text=No+Image';
                            }
                        ],
                        'prices' => [
                            'type' => Type::listOf(new ObjectType([
                                'name' => 'Price',
                                'fields' => [
                                    'amount' => Type::float(),
                                    'currency_label' => Type::string(),
                                    'currency_symbol' => Type::string(),
                                ]
                            ])),
                            'resolve' => function ($p) {
                                return $p instanceof ProductInterface ? $p->getPrices() : (array)($p['prices'] ?? []);
                            }
                        ],
                        'price' => [
                            'type' => Type::float(),
                            'resolve' => function ($p) {
                                if ($p instanceof ProductInterface) {
                                    return $p->getPrices()[0]['amount'] ?? null;
                                }
                                if (!empty($p['prices']) && isset($p['prices'][0]['amount'])) {
                                    return (float)$p['prices'][0]['amount'];
                                }
                                return null;
                            }
                        ],
                        'attributes' => [
                            'type' => Type::listOf(AttributeSchema::getAttributeType()),
                            'resolve' => function ($p) {
                                return $p instanceof ProductInterface ? $p->getAttributes() : (array)($p['attributes'] ?? []);
                            }
                        ],
                    ];
                }
            ]);
        }

        return self::$productType;
    }
}
