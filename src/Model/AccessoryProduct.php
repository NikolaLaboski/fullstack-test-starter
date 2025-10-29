<?php
namespace App\Model;

final class AccessoryProduct extends AbstractProduct
{
    public function requiredAttributeNames(): array
    {
        return [];
    }

    public function buildSkuKey(): string
    {
        return 'acc-' . $this->getId();
    }
}
