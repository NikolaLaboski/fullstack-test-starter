<?php
// ProductSchema.php
// GraphQL schema parts for Product-related queries and the Product object type.
// Exposes:
//  - Query.products: [Product]
//  - Query.product(id: String!): Product
//  - Product: id, name, category, brand, description, inStock, gallery, image, prices, attributes

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

require_once __DIR__ . '/../schemas/AttributeSchema.php';
require_once __DIR__ . '/../resolvers/ProductResolver.php';

class ProductSchema
{
    // Cache for the Product ObjectType so we don't rebuild it multiple times.
    private static $productType = null;

    /**
     * Returns the Query root type with "products" and "product" fields.
     *
     * @return \GraphQL\Type\Definition\ObjectType
     */
    public static function getQueryType()
    {
        return new ObjectType([
            'name' => 'Query',
            'fields' => function () {
                return [
                    // Fetch all products
                    'products' => [
                        'type' => Type::listOf(self::productType()),
                        'resolve' => function () {
                            return ProductResolver::getAllProducts();
                        }
                    ],
                    // Fetch a single product by ID (string)
                    'product' => [
                        'type' => self::productType(),
                        'args' => [
                            'id' => Type::nonNull(Type::string()) // 🔄 ID is STRING in your DB
                        ],
                        'resolve' => function ($root, $args) {
                            return ProductResolver::getProductById($args['id']);
                        }
                    ],
                ];
            }
        ]);
    }

    /**
     * Returns (and caches) the Product object type definition.
     * Fields include nested resolvers for image, prices, and attributes to
     * keep a consistent shape even when source arrays are missing.
     *
     * @return \GraphQL\Type\Definition\ObjectType
     */
    public static function productType()
    {
        if (self::$productType === null) {
            self::$productType = new ObjectType([
                'name' => 'Product',
                'fields' => function () {
                    return [
                        // Core fields
                        'id' => Type::nonNull(Type::string()), // ✅ string, not int
                        'name' => Type::nonNull(Type::string()),
                        'category' => Type::string(),
                        'brand' => Type::string(),
                        'description' => Type::string(),
                        'inStock' => Type::boolean(),

                        // Gallery: array of image URLs
                        'gallery' => Type::listOf(Type::string()),

                        // Convenience "image": first gallery image or a placeholder
                        'image' => [
                            'type' => Type::string(),
                            'resolve' => function ($product) {
                                return isset($product['gallery']) && is_array($product['gallery']) && count($product['gallery']) > 0
                                    ? $product['gallery'][0]
                                    : 'https://via.placeholder.com/220x220.png?text=No+Image';
                            }
                        ],

                        // Prices: list of objects with amount and currency metadata
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

                        // Attributes: list of Attribute types (with items)
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
