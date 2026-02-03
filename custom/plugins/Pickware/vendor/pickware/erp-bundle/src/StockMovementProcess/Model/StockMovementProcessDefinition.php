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

use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\User\UserDefinition;

/**
 * @extends EntityDefinition<StockMovementProcessEntity>
 */
class StockMovementProcessDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_stock_movement_process';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return StockMovementProcessEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new StringField('type_technical_name', 'typeTechnicalName'))->addFlags(new Required()),
            new ManyToOneAssociationField('type', 'type_technical_name', StockMovementProcessTypeDefinition::class, 'technical_name'),

            (new JsonField('referenced_entity_snapshot', 'referencedEntitySnapshot'))->addFlags(new Required()),

            new FkField('user_id', 'userId', UserDefinition::class),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id'),
            new JsonField('user_snapshot', 'userSnapshot'),

            (new OneToManyAssociationField('stockMovements', StockMovementDefinition::class, 'stock_movement_process_id', 'id'))->addFlags(new SetNullOnDelete()),

            // A ManyToManyAssociationField is used even though this actually is a many-to-one association. This is
            // because we have a mapping table and with ManyToManyAssociationFields we can make that mapping table
            // transparent for users of the DAL and the API. The many-to-one characteristics are enforced by a unique
            // index on the mapping table.
            (new ManyToManyAssociationField(
                'orders',
                OrderDefinition::class,
                OrderStockMovementProcessMappingDefinition::class,
                'stock_movement_process_id',
                'order_id',
            ))->addFlags(new CascadeDelete()),
        ]);
    }
}
