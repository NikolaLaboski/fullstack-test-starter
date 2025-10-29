<?php
namespace App\GraphQL;

use App\Model\ProductInterface;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

final class Types
{
    private static ?ObjectType $product = null;

    public static function product(): ObjectType
    {
        if (self::$product) return self::$product;

        self::$product = new ObjectType([
            'name' => 'Product',
            'fields' => [
                'id' => Type::nonNull(Type::string()),
                'name' => Type::string(),
                'category' => Type::string(),
                'brand' => Type::string(),
                'description' => Type::string(),
                'inStock' => Type::boolean(),
                'gallery' => Type::listOf(Type::string()),
                'image' => [
                    'type' => Type::string(),
                    'resolve' => function(ProductInterface $p) {
                        $g = $p->getGallery();
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
                    'resolve' => fn(ProductInterface $p) => $p->getPrices(),
                ],
                'attributes' => [
                    'type' => Type::listOf(new ObjectType([
                        'name' => 'Attribute',
                        'fields' => [
                            'id' => Type::int(),
                            'name' => Type::string(),
                            'type' => Type::string(),
                            'items' => Type::listOf(new ObjectType([
                                'name' => 'AttributeItem',
                                'fields' => [
                                    'id' => Type::int(),
                                    'displayValue' => Type::string(),
                                    'value' => Type::string(),
                                ]
                            ]))
                        ]
                    ]))
                ],
            ]
        ]);

        return self::$product;
    }
}
