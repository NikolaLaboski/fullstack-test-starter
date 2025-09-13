<?php

// OrderInputSchema.php
// Declares the GraphQL InputObjectType used by mutations that accept order items.
// Shape: { product_id: Int!, quantity: Int! }

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

class OrderInputSchema
{
    /**
     * Returns the input type for a single order item.
     * Fields:
     *  - product_id: integer product identifier
     *  - quantity: integer quantity for the product
     *
     * @return \GraphQL\Type\Definition\InputObjectType
     */
    public static function getOrderItemInputType()
    {
        return new InputObjectType([
            'name' => 'OrderItemInput',
            'fields' => [
                'product_id' => Type::nonNull(Type::int()),
                'quantity' => Type::nonNull(Type::int()),
            ]
        ]);
    }
}
