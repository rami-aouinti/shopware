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

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<ProductSupplierConfigurationListItemEntity>
 * This is a "pseudo" entity definition, which does not create its own database table. It is only used to be created
 * on-the-fly and passed to the administration api to be displayed in a grid view. You cannot write or update these
 * entities with the DAL.
 */
class ProductSupplierConfigurationListItemDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_product_supplier_configuration_list_item';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductSupplierConfigurationListItemEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductSupplierConfigurationListItemCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField(
                'product',
                'product_id',
                ProductDefinition::class,
                'id',
                false,
            ),

            new FkField('product_supplier_configuration_id', 'productSupplierConfigurationId', ProductSupplierConfigurationDefinition::class, 'id'),
            new ManyToOneAssociationField(
                'productSupplierConfiguration',
                'product_supplier_configuration_id',
                ProductSupplierConfigurationDefinition::class,
                'id',
                false,
            ),
        ]);
    }
}
