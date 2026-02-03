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
class StockMovementProcessType
{
    public function __construct(
        private readonly string $technicalName,
        private readonly string $referencedEntityFieldName,
        private readonly string $referencedEntityDefinitionClass,
    ) {}

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getReferencedEntityFieldName(): string
    {
        return $this->referencedEntityFieldName;
    }

    public function getReferencedEntityDefinitionClass(): string
    {
        return $this->referencedEntityDefinitionClass;
    }
}
