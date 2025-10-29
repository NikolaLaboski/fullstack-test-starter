<?php
// mutations/CreateOrderMutation.php
use GraphQL\Type\Definition\Type;
use App\Service\OrderService;

class CreateOrderMutation
{
    public static function getMutation(): array
    {
        return [
            'createOrder' => self::getDefinition(),
        ];
    }

    public static function getDefinition(): array
    {
        return [
            'type' => Type::nonNull(Type::boolean()),
            'args' => [
                'items' => [
                    'type' => Type::nonNull(
                        Type::listOf(
                            OrderInputSchema::getOrderItemInputType()
                        )
                    ),
                ],
            ],
            'resolve' => function ($root, $args) {
                $service = new OrderService();
                return $service->place($args['items']);
            },
        ];
    }
}
