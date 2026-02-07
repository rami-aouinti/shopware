<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PurchaseList\ImportExportProfile;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PurchaseListImportListItem
{
    private PriceCollection $purchasePrices;

    public function __construct(
        private readonly string $id,
        private string $productId,
        private ?string $productSupplierConfigurationId = null,
        private ?int $quantity = null,
        ?float $purchasePriceNet = null,
        ?float $productTaxRate = null,
    ) {
        $this->purchasePrices = new PriceCollection();

        if ($purchasePriceNet === null || $productTaxRate === null) {
            return;
        }

        $gross = $purchasePriceNet * (1 + $productTaxRate / 100);
        $this->purchasePrices->add(new Price(Defaults::CURRENCY, $purchasePriceNet, $gross, linked: true));
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function setPurchasePriceNet(float $purchasePriceNet, float $productTaxRate): void
    {
        $gross = $purchasePriceNet * (1 + $productTaxRate / 100);
        $this->purchasePrices = new PriceCollection();
        $this->purchasePrices->add(new Price(Defaults::CURRENCY, $purchasePriceNet, $gross, linked: true));
    }

    public function setPurchasePrices(PriceCollection $purchasePrices): void
    {
        $this->purchasePrices = $purchasePrices;
    }

    public function setProductSupplierConfigurationId(string $productSupplierConfigurationId): void
    {
        $this->productSupplierConfigurationId = $productSupplierConfigurationId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getProductSupplierConfigurationId(): ?string
    {
        return $this->productSupplierConfigurationId;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getPurchasePrices(): array
    {
        return $this->purchasePrices->map(fn($price) => $price->jsonSerialize());
    }
}
