<?php 
// AttributeSchema.php
// GraphQL type definitions for product attributes and nested attribute items.
// Exposes:
//  - Attribute type: { id, name, type, items }
//  - AttributeItem type (inline inside items): { id, displayValue, value }

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class AttributeSchema
{
    // Cached GraphQL type instance to avoid re-creating on every call.
    private static $attributeType = null;

    /**
     * Returns the GraphQL ObjectType for an Attribute, with an "items" list.
     * The items list uses an inline ObjectType definition for AttributeItem.
     *
     * @return \GraphQL\Type\Definition\ObjectType
     */
    public static function getAttributeType()
    {
        if (self::$attributeType === null) {
            self::$attributeType = new ObjectType([
                'name' => 'Attribute',
                'fields' => [
                    // Scalar fields
                    'id' => Type::nonNull(Type::int()),
                    'name' => Type::nonNull(Type::string()),
                    'type' => Type::string(),

                    // Nested items field: list of AttributeItem objects
                    'items' => [
                        'type' => Type::listOf(new ObjectType([
                            'name' => 'AttributeItem',
                            'fields' => [
                                'id' => Type::nonNull(Type::string()),
                                'displayValue' => Type::nonNull(Type::string()),
                                'value' => Type::nonNull(Type::string()),
                            ]
                        ])),
                        // Resolver ensures an array is always returned (empty if missing).
                        'resolve' => function ($attribute) {
                            return isset($attribute['items']) ? $attribute['items'] : [];
                        }
                    ],
                ]
            ]);
        }

        return self::$attributeType;
    }
}
