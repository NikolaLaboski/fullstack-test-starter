<?php

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

class OrderInputSchema
{
    public static function getOrderItemInputType()
    {
        return new InputObjectType([
            'name' => 'OrderItemInput',
            'fields' => [
                // product_id must be STRING because your product IDs are like "ps-5"
                'product_id' => Type::nonNull(Type::string()),
                'quantity'   => Type::nonNull(Type::int()),
            ]
        ]);
    }
}
