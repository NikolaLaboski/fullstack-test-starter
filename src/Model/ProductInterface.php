<?php
namespace App\Model;

interface ProductInterface
{
    public function getId(): string;
    public function getName(): ?string;
    public function getCategory(): ?string;
    public function getBrand(): ?string;
    public function getDescription(): ?string;
    public function isInStock(): ?bool;

    /** @return string[] */
    public function getGallery(): array;

    /** @return array<int, array{amount: float, currency_label: ?string, currency_symbol: ?string}> */
    public function getPrices(): array;

    /** @return array<int, array<string,mixed>> */
    public function getAttributes(): array;
}
