<?php
namespace App\Model;

final class ClothingProduct extends AbstractProduct
{
    public function requiredAttributeNames(): array
    {
        return ['Size'];
    }

    public function buildSkuKey(): string
    {
        return 'clothes-' . $this->getId();
    }
}
