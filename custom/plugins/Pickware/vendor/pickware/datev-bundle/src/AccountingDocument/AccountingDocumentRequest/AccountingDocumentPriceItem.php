<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocument\AccountingDocumentRequest;

use Pickware\DatevBundle\CostCenters\ProductCostCenter;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AccountingDocumentPriceItem
{
    public function __construct(
        private float $price,
        private readonly ?float $taxRate,
        private ?ProductCostCenter $productCostCenter = null,
    ) {}

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getTaxRate(): ?float
    {
        return $this->taxRate;
    }

    public function getProductCostCenter(): ?ProductCostCenter
    {
        return $this->productCostCenter;
    }

    public function setProductCostCenter(?ProductCostCenter $productCostCenter): void
    {
        $this->productCostCenter = $productCostCenter;
    }
}
