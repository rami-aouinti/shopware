<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\Model;

use Pickware\PickwareErpStarter\Address\Model\AddressDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Language\LanguageDefinition;

/**
 * @extends EntityDefinition<SupplierEntity>
 */
class SupplierDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_supplier';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SupplierEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SupplierCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new StringField('number', 'number'))->addFlags(new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),

            new StringField('customer_number', 'customerNumber'),
            new IntField('default_delivery_time', 'defaultDeliveryTime'),

            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id'),

            new FkField('address_id', 'addressId', AddressDefinition::class, 'id'),
            new OneToOneAssociationField('address', 'address_id', 'id', AddressDefinition::class, false),

            new CustomFields(),

            // Associations with foreign keys on the other side
            (new OneToManyAssociationField(
                'productSupplierConfigurations',
                ProductSupplierConfigurationDefinition::class,
                'supplier_id',
                'id',
            ))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField(
                'defaultPickwareProducts',
                PickwareProductDefinition::class,
                'default_supplier_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField(
                'orders',
                SupplierOrderDefinition::class,
                'supplier_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField(
                'goodsReceipts',
                GoodsReceiptDefinition::class,
                'supplier_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),
        ]);
    }
}
