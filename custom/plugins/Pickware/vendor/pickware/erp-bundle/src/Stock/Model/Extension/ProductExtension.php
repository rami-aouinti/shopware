<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\Model\Extension;

use Pickware\DalBundle\AbstractCompatibilityEntityExtension;
use Pickware\PickwareErpStarter\Stock\Model\ProductStockLocationMappingDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Pickware\PickwareErpStarter\Stock\Model\WarehouseStockDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductExtension extends AbstractCompatibilityEntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField(
                'pickwareErpStockMovements',
                StockMovementDefinition::class,
                'product_id',
                'id',
            ))->addFlags(new CascadeDelete(false /* isCloneRelevant */)),
        );

        $collection->add(
            (new OneToManyAssociationField(
                'pickwareErpStocks',
                StockDefinition::class,
                'product_id',
                'id',
            ))->addFlags(new CascadeDelete(false /* isCloneRelevant */)),
        );

        $collection->add(
            (new OneToManyAssociationField(
                'pickwareErpWarehouseStocks',
                WarehouseStockDefinition::class,
                'product_id',
                'id',
            ))->addFlags(new CascadeDelete(false /* isCloneRelevant */)),
        );

        $collection->add(
            (new OneToManyAssociationField(
                'pickwareErpProductStockLocationMappings',
                ProductStockLocationMappingDefinition::class,
                'product_id',
                'id',
            ))->addFlags(new CascadeDelete(false /* isCloneRelevant */)),
        );
    }

    public function getEntityDefinitionClassName(): string
    {
        return ProductDefinition::class;
    }
}
