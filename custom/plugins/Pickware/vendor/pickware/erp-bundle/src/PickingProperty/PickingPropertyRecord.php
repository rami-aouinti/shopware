<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PickingProperty;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class PickingPropertyRecord
{
    /**
     * @param ?string $productId Is null if the product was deleted
     * @param PickingPropertyRecordValue[] $pickingPropertyRecordValues
     */
    public function __construct(
        private ?string $productId,
        private array $productSnapshot,
        private array $pickingPropertyRecordValues,
    ) {}

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function getProductSnapshot(): array
    {
        return $this->productSnapshot;
    }

    /**
     * @return PickingPropertyRecordValue[]
     */
    public function getPickingPropertyRecordValues(): array
    {
        return $this->pickingPropertyRecordValues;
    }
}
