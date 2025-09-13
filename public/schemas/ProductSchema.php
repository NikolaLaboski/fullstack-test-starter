<?php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

require_once __DIR__ . '/../schemas/AttributeSchema.php';
require_once __DIR__ . '/../resolvers/ProductResolver.php';

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
                            return ProductResolver::getAllProducts();
                        }
                    ],
                    'product' => [
                        'type' => self::productType(),
                        'args' => [
                            'id' => Type::nonNull(Type::string())
                        ],
                        'resolve' => function ($root, $args) {
                            return ProductResolver::getProductById($args['id']);
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
                        'id' => Type::nonNull(Type::string()),
                        'name' => Type::nonNull(Type::string()),
                        'category' => Type::string(),
                        'brand' => Type::string(),
                        'description' => Type::string(),
                        'inStock' => Type::boolean(),

                        'gallery' => Type::listOf(Type::string()),

                        'image' => [
                            'type' => Type::string(),
                            'resolve' => function ($product) {
                                return isset($product['gallery']) && is_array($product['gallery']) && count($product['gallery']) > 0
                                    ? $product['gallery'][0]
                                    : 'https://via.placeholder.com/220x220.png?text=No+Image';
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
                            'resolve' => function ($product) {
                                return isset($product['prices']) ? $product['prices'] : [];
                            }
                        ],

                        // >>> ADDED: single price (first price amount) to satisfy frontend queries
                        'price' => [
                            'type' => Type::float(),
                            'resolve' => function ($product) {
                                if (isset($product['price']) && $product['price'] !== null) {
                                    return (float)$product['price'];
                                }
                                if (!empty($product['prices']) && isset($product['prices'][0]['amount'])) {
                                    return (float)$product['prices'][0]['amount'];
                                }
                                return null;
                            }
                        ],

                        'attributes' => [
                            'type' => Type::listOf(AttributeSchema::getAttributeType()),
                            'resolve' => function ($product) {
                                return isset($product['attributes']) ? $product['attributes'] : [];
                            }
                        ],
                    ];
                }
            ]);
        }

        return self::$productType;
    }
}
