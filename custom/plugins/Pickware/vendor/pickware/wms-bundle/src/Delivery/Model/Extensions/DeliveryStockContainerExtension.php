<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery\Model\Extensions;

use Pickware\DalBundle\AbstractCompatibilityEntityExtension;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class DeliveryStockContainerExtension extends AbstractCompatibilityEntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField(
                'pickwareWmsDelivery',
                'id',
                'stock_container_id',
                DeliveryDefinition::class,
                false,
            ))->addFlags(new RestrictDelete(false /* isCloneRelevant */)),
        );
    }

    public function getEntityDefinitionClassName(): string
    {
        return StockContainerDefinition::class;
    }
}
