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

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class StockMovementProcessTypeEntity extends Entity
{
    protected string $technicalName;
    protected string $referencedEntityFieldName;
    protected string $referencedEntityDefinitionClass;
    protected ?StockMovementProcessCollection $stockMovementProcesses = null;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getReferencedEntityFieldName(): string
    {
        return $this->referencedEntityFieldName;
    }

    public function setReferencedEntityFieldName(string $referencedEntityFieldName): void
    {
        $this->referencedEntityFieldName = $referencedEntityFieldName;
    }

    public function getReferencedEntityDefinitionClass(): string
    {
        return $this->referencedEntityDefinitionClass;
    }

    public function setReferencedEntityDefinitionClass(string $referencedEntityDefinitionClass): void
    {
        $this->referencedEntityDefinitionClass = $referencedEntityDefinitionClass;
    }

    public function getStockMovementProcesses(): StockMovementProcessCollection
    {
        if (!$this->stockMovementProcesses) {
            throw new AssociationNotLoadedException('stockMovementProcesses', $this);
        }

        return $this->stockMovementProcesses;
    }

    public function setStockMovementProcesses(?StockMovementProcessCollection $stockMovementProcesses): void
    {
        $this->stockMovementProcesses = $stockMovementProcesses;
    }
}
