<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Product\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\DalBundle\Field\PhpEnumField;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<PickwareProductEntity>
 */
class PickwareProductDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_pickware_product';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PickwareProductEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PickwareProductCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new OneToOneAssociationField('product', 'product_id', 'id', ProductDefinition::class, false),

            new FkField(
                'default_supplier_id',
                'defaultSupplierId',
                SupplierDefinition::class,
                'id',
            ),
            new OneToOneAssociationField(
                'defaultSupplier',
                'default_supplier_id',
                'id',
                SupplierDefinition::class,
                autoload: false,
            ),

            (new IntField('reorder_point', 'reorderPoint'))->addFlags(new Required()),
            (new BoolField('is_excluded_from_reorder_notification_mail', 'isExcludedFromReorderNotificationMail'))
                ->addFlags(new Required()),
            new IntField('target_maximum_quantity', 'targetMaximumQuantity'),
            (new IntField('stock_below_reorder_point', 'stockBelowReorderPoint'))->addFlags(new Computed(), new WriteProtected()),
            (new IntField('reserved_stock', 'reservedStock'))->addFlags(new Computed(), new WriteProtected()),
            (new IntField('internal_reserved_stock', 'internalReservedStock'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new IntField('external_reserved_stock', 'externalReservedStock'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new IntField('physical_stock', 'physicalStock'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new IntField('stock_not_available_for_sale', 'stockNotAvailableForSale'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new IntField('incoming_stock', 'incomingStock'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new BoolField('is_stock_management_disabled', 'isStockManagementDisabled'))->addFlags(new Required()),
            (new BoolField('ship_automatically', 'shipAutomatically')),
            (new BoolField('is_batch_managed', 'isBatchManaged'))->addFlags(new Required()),
            (new PhpEnumField('tracking_profile', 'trackingProfile', ProductTrackingProfile::class))->addFlags(new Required()),
        ]);
    }

    public function getDefaults(): array
    {
        return [
            'isStockManagementDisabled' => false,
            'isBatchManaged' => false,
            'trackingProfile' => ProductTrackingProfile::Number,
            'isExcludedFromReorderNotificationMail' => false,
        ];
    }
}
