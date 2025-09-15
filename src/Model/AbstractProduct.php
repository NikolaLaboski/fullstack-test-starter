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

    public function setGallery(array $g): void   { $this->gallery = $g; }
    public function setPrices(array $p): void    { $this->prices = $p; }
    public function setAttributes(array $a): void{ $this->attributes = $a; }

    public function getId(): string        { return (string)($this->row['id'] ?? ''); }
    public function getName(): ?string     { return $this->row['name'] ?? null; }
    public function getCategory(): ?string { return $this->row['category'] ?? null; }
    public function getBrand(): ?string    { return $this->row['brand'] ?? null; }
    public function getDescription(): ?string { return $this->row['description'] ?? null; }

    public function isInStock(): ?bool
    {
        if (array_key_exists('inStock', $this->row)) return (bool)$this->row['inStock'];
        if (array_key_exists('in_stock', $this->row)) return (bool)$this->row['in_stock'];
        return null;
    }

    public function getGallery(): array    { return $this->gallery; }
    public function getPrices(): array     { return $this->prices; }
    public function getAttributes(): array { return $this->attributes; }
}
