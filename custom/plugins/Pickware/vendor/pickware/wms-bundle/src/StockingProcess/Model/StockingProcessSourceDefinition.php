<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockingProcess\Model;

use Pickware\DalBundle\Field\NonUuidFkField;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<StockingProcessSourceEntity>
 */
class StockingProcessSourceDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_stocking_process_source';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return StockingProcessSourceEntity::class;
    }

    public function getCollectionClass(): string
    {
        return StockingProcessSourceCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('stocking_process_id', 'stockingProcessId', StockingProcessDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField('stockingProcess', 'stocking_process_id', StockingProcessDefinition::class, 'id'),

            (new NonUuidFkField('location_type_technical_name', 'locationTypeTechnicalName', LocationTypeDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('locationType', 'location_type_technical_name', LocationTypeDefinition::class, 'technical_name'),

            new FkField('goods_receipt_id', 'goodsReceiptId', GoodsReceiptDefinition::class, 'id'),
            new OneToOneAssociationField('goodsReceipt', 'goods_receipt_id', 'id', GoodsReceiptDefinition::class, false),

            new FkField('stock_container_id', 'stockContainerId', StockContainerDefinition::class, 'id'),
            new OneToOneAssociationField('stockContainer', 'stock_container_id', 'id', StockContainerDefinition::class, false),
        ]);
    }
}
