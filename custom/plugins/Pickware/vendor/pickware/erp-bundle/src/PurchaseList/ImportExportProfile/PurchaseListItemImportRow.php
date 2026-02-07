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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @phpstan-import-type NormalizedPurchaseListRow from PurchaseListImportCsvRowNormalizer
 */
#[Exclude]
readonly class PurchaseListItemImportRow
{
    private function __construct(
        private string $productNumber,
        private ?int $quantity,
        private ?string $supplierName,
        private ?float $purchasePriceNet,
        private int $rowNumber,
    ) {}

    /**
     * @param NormalizedPurchaseListRow $array
     */
    public static function fromRow(array $array, int $rowNumber): self
    {
        return new self(
            $array['productNumber'],
            $array['quantity'] ?? null,
            $array['supplierName'] ?? null,
            $array['purchasePriceNet'] ?? null,
            $rowNumber,
        );
    }

    /**
     * @return NormalizedPurchaseListRow
     */
    public function toNormalizedRow(): array
    {
        return [
            'productNumber' => $this->productNumber,
            'quantity' => $this->quantity,
            'supplierName' => $this->supplierName,
            'purchasePriceNet' => $this->purchasePriceNet,
        ];
    }

    public function getProductNumber(): string
    {
        return $this->productNumber;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function getSupplierName(): ?string
    {
        return $this->supplierName;
    }

    public function getPurchasePriceNet(): ?float
    {
        return $this->purchasePriceNet;
    }

    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }
}
