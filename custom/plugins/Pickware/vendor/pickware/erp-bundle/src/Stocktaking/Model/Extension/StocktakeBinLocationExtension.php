<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\Model\Extension;

use Pickware\DalBundle\AbstractCompatibilityEntityExtension;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeCountingProcessDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class StocktakeBinLocationExtension extends AbstractCompatibilityEntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField(
                'pickwareStocktakeCountingProcesses',
                StocktakeCountingProcessDefinition::class,
                'bin_location_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),
        );
    }

    public function getEntityDefinitionClassName(): string
    {
        return BinLocationDefinition::class;
    }
}
