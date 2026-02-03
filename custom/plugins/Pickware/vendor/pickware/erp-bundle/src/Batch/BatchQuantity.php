<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class BatchQuantity
{
    public function __construct(
        private string $productId,
        private ?string $batchId,
        private int $quantity,
    ) {}

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getBatchId(): ?string
    {
        return $this->batchId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
