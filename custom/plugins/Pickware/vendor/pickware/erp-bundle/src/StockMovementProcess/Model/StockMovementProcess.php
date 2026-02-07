<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockMovementProcess\Model;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class StockMovementProcess
{
    public function __construct(
        private readonly StockMovementProcessType $type,
        private readonly string $referencedEntityId,
        private readonly ?string $id = null,
        private readonly ?string $userId = null,
        private array $stockMovementIds = [],
    ) {}

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getType(): StockMovementProcessType
    {
        return $this->type;
    }

    public function getReferencedEntityId(): string
    {
        return $this->referencedEntityId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getStockMovementIds(): array
    {
        return $this->stockMovementIds;
    }

    public function setStockMovementIds(array $stockMovementIds): void
    {
        $this->stockMovementIds = $stockMovementIds;
    }
}
