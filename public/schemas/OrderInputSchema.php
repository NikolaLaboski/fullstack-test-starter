<?php

// OrderInputSchema.php
// Declares the GraphQL InputObjectType used by mutations that accept order items.
// Shape: { product_id: String!, quantity: Int! }

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

class OrderInputSchema
{
    public static function getOrderItemInputType()
    {
        return new InputObjectType([
            'name' => 'OrderItemInput',
            'fields' => [
                'product_id' => Type::nonNull(Type::int()),
                'quantity'   => Type::nonNull(Type::int()),
            ]
        ]);
    }
}
