<?php
namespace App\Model;

final class TechProduct extends AbstractProduct
{
    public function requiredAttributeNames(): array
    {
        return ['Capacity', 'Color'];
    }

    // Example of type-specific tweak (still returns same value unless you change pricing rules)
    public function buildSkuKey(): string
    {
        return 'tech-' . $this->getId();
    }
}
