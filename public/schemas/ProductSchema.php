<?php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

require_once __DIR__ . '/../schemas/AttributeSchema.php';

/**
 * Make sure composer autoload is present for src/ classes.
 * (hot-fix so this file can see App\Model\* directly)
 */
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (is_file($autoload)) { require_once $autoload; }

use App\Model\ProductRepository;
use App\Model\ProductInterface;

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
                            // Fetch domain models, then adapt to plain arrays for GraphQL
                            $models = ProductRepository::all();
                            return array_map([self::class, 'modelToArray'], $models);
                        }
                    ],
                    'product' => [
                        'type' => self::productType(),
                        'args' => [
                            'id' => Type::nonNull(Type::string())
                        ],
                        'resolve' => function ($root, $args) {
                            $m = ProductRepository::find($args['id']);
                            return $m ? self::modelToArray($m) : null;
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
                        'id'        => Type::nonNull(Type::string()),
                        'name'      => Type::string(),
                        'category'  => Type::string(),
                        'brand'     => Type::string(),
                        'description'=> Type::string(),
                        'inStock'   => Type::boolean(),

                        'gallery'   => Type::listOf(Type::string()),

                        'image' => [
                            'type' => Type::string(),
                            'resolve' => function ($product) {
                                return isset($product['gallery'][0])
                                    ? $product['gallery'][0]
                                    : 'https://via.placeholder.com/220x220.png?text=No+Image';
                            }
                        ],

                        'prices' => [
                            'type' => Type::listOf(new ObjectType([
                                'name' => 'Price',
                                'fields' => [
                                    'amount'          => Type::float(),
                                    'currency_label'  => Type::string(),
                                    'currency_symbol' => Type::string(),
                                ]
                            ])),
                            'resolve' => function ($product) {
                                return $product['prices'] ?? [];
                            }
                        ],

                        'price' => [
                            'type' => Type::float(),
                            'resolve' => function ($product) {
                                if (isset($product['price']) && $product['price'] !== null) {
                                    return (float)$product['price'];
                                }
                                if (!empty($product['prices'][0]['amount'])) {
                                    return (float)$product['prices'][0]['amount'];
                                }
                                return null;
                            }
                        ],

                        'attributes' => [
                            'type' => Type::listOf(AttributeSchema::getAttributeType()),
                            'resolve' => function ($product) {
                                return $product['attributes'] ?? [];
                            }
                        ],
                    ];
                }
            ]);
        }

        return self::$productType;
    }

    /**
     * Adapt a domain model (ProductInterface) to the associative-array
     * structure expected by the GraphQL type above.
     */
    private static function modelToArray(ProductInterface $p): array
    {
        $prices = $p->getPrices();
        $price  = null;
        if (!empty($prices) && isset($prices[0]['amount'])) {
            $price = (float)$prices[0]['amount'];
        }

        return [
            'id'          => $p->getId(),
            'name'        => $p->getName(),
            'category'    => $p->getCategory(),
            'brand'       => $p->getBrand(),
            'description' => $p->getDescription(),
            'inStock'     => $p->isInStock(),
            'gallery'     => $p->getGallery(),
            'prices'      => $prices,
            'price'       => $price,
            'attributes'  => $p->getAttributes(),
        ];
    }
}
