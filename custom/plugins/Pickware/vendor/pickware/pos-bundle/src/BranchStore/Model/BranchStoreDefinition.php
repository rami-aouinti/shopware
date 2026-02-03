<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\BranchStore\Model;

use Pickware\PickwarePos\Address\Model\AddressDefinition;
use Pickware\PickwarePos\CashRegister\Model\CashRegisterDefinition;
use Pickware\PickwarePos\Order\Model\OrderBranchStoreMappingDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @extends EntityDefinition<BranchStoreEntity>
 */
class BranchStoreDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_pos_branch_store';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return BranchStoreEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BranchStoreCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new StringField('name', 'name'))->addFlags(new Required()),
            new StringField('fiskaly_organization_uuid', 'fiskalyOrganizationUuid'),

            new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class, 'id'),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id'),

            (new FkField('address_id', 'addressId', AddressDefinition::class, 'id'))->addFlags(new Required()),
            new OneToOneAssociationField('address', 'address_id', 'id', AddressDefinition::class, false),

            (new OneToManyAssociationField(
                'cashRegisters',
                CashRegisterDefinition::class,
                'branch_store_id',
                'id',
            ))->addFlags(new RestrictDelete()),

            // A ManyToManyAssociationField is used even though this actually is a one-to-many association. This is
            // because we have a mapping table and with ManyToManyAssociationFields we can make that mapping table
            // transparent for users of the DAL and the API. The one-to-many characteristics are enforced by a unique
            // index on the mapping table.
            (new ManyToManyAssociationField(
                'orders',
                OrderDefinition::class,
                OrderBranchStoreMappingDefinition::class,
                'branch_store_id',
                'order_id',
            ))->addFlags(new CascadeDelete()),
        ]);
    }
}
