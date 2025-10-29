<?php
namespace App\Model;

abstract class AbstractProduct implements ProductInterface
{
    protected array $row;
    protected array $gallery = [];
    protected array $prices = [];
    protected array $attributes = [];

    public function __construct(array $row)
    {
        $this->row = $row;
    }

    // setters populated by repository
    public function setGallery(array $g): void    { $this->gallery = $g; }
    public function setPrices(array $p): void     { $this->prices = $p; }
    public function setAttributes(array $a): void { $this->attributes = $a; }

    // common getters
    public function getId(): string                 { return (string)($this->row['id'] ?? ''); }
    public function getName(): ?string              { return $this->row['name'] ?? null; }
    public function getCategory(): ?string          { return $this->row['category'] ?? null; }
    public function getBrand(): ?string             { return $this->row['brand'] ?? null; }
    public function getDescription(): ?string       { return $this->row['description'] ?? null; }
    public function isInStock(): ?bool
    {
        if (array_key_exists('inStock', $this->row)) return (bool)$this->row['inStock'];
        if (array_key_exists('in_stock', $this->row)) return (bool)$this->row['in_stock'];
        return null;
    }

    /** @return string[] */
    public function getGallery(): array             { return $this->gallery; }

    /** @return array<int, array{amount: float, currency_label: ?string, currency_symbol: ?string}> */
    public function getPrices(): array              { return $this->prices; }

    /** @return array<int, array<string,mixed>> */
    public function getAttributes(): array          { return $this->attributes; }

    // ---------- OOP hooks (polymorphic) ----------
    /** Names of attributes required to place an order for this product type. */
    public function requiredAttributeNames(): array { return []; }

    /** Simple availability rule; child classes may extend it. */
    public function canBeOrdered(): bool            { return $this->isInStock() !== false; }

    /** Base unit price (first price amount). Child classes may override if needed. */
    public function getUnitPrice(): float
    {
        $first = $this->prices[0]['amount'] ?? 0.0;
        return (float)$first;
    }

    /** SKU-like key demonstration (type-specific classes can alter the format). */
    public function buildSkuKey(): string
    {
        $cat = strtolower((string)$this->getCategory() ?: 'product');
        return $cat . '-' . $this->getId();
    }
}
