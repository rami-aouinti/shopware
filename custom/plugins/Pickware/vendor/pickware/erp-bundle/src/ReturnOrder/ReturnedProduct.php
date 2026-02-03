<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder;

use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ReturnedProduct implements JsonSerializable
{
    public function __construct(
        private readonly string $productId,
        private readonly string $returnReason,
        private readonly int $quantity,
    ) {}

    public static function fromArray(array $array): self
    {
        return new self(
            productId: $array['productId'],
            returnReason: $array['reason'],
            quantity: $array['quantity'],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'productId' => $this->productId,
            'reason' => $this->returnReason,
            'quantity' => $this->quantity,
        ];
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getReturnReason(): string
    {
        return $this->returnReason;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
