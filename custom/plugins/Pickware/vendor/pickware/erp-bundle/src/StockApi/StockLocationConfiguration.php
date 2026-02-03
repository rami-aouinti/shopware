<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi;

use DateTimeInterface;
use Pickware\DalBundle\Translation;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class StockLocationConfiguration
{
    /**
     * @param ?string $code Can be a custom "code" or a "number" from a number range.
     * Fill the warehouse related properties with `null` if no warehouse is designated for this location type
     */
    public function __construct(
        private readonly ?bool $stockAvailableForSale,
        private readonly ?string $code,
        private readonly ?string $warehouseCode,
        private readonly ?int $position,
        private readonly ?bool $isInDefaultWarehouse,
        private readonly ?DateTimeInterface $warehouseCreationDate,
        private readonly Translation $globalUniqueDisplayName,
    ) {}

    public function isStockAvailableForSale(): bool
    {
        return $this->stockAvailableForSale;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getWarehouseCode(): ?string
    {
        return $this->warehouseCode;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function getIsInDefaultWarehouse(): ?bool
    {
        return $this->isInDefaultWarehouse;
    }

    public function getWarehouseCreationDate(): ?DateTimeInterface
    {
        return $this->warehouseCreationDate;
    }

    public function getGlobalUniqueDisplayName(): Translation
    {
        return $this->globalUniqueDisplayName;
    }
}
