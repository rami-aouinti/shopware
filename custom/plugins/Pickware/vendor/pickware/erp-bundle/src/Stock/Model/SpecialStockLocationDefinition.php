<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\Model;

use Pickware\DalBundle\Field\EnumField;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<SpecialStockLocationEntity>
 */
class SpecialStockLocationDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_special_stock_location';
    public const TECHNICAL_NAME_IMPORT = 'import';
    public const TECHNICAL_NAME_INITIALIZATION = 'initialization';
    public const TECHNICAL_NAME_PRODUCT_TOTAL_STOCK_CHANGE = 'product_total_stock_change';
    public const TECHNICAL_NAME_PRODUCT_AVAILABLE_STOCK_CHANGE = 'product_available_stock_change';
    public const TECHNICAL_NAME_STOCK_CORRECTION = 'stock_correction';
    public const TECHNICAL_NAME_SHOPWARE_MIGRATION = 'shopware_migration';
    public const TECHNICAL_NAME_UNKNOWN = 'unknown';
    public const TECHNICAL_NAMES = [
        self::TECHNICAL_NAME_IMPORT,
        self::TECHNICAL_NAME_INITIALIZATION,
        self::TECHNICAL_NAME_PRODUCT_TOTAL_STOCK_CHANGE,
        self::TECHNICAL_NAME_PRODUCT_AVAILABLE_STOCK_CHANGE,
        self::TECHNICAL_NAME_STOCK_CORRECTION,
        self::TECHNICAL_NAME_SHOPWARE_MIGRATION,
        self::TECHNICAL_NAME_UNKNOWN,
    ];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SpecialStockLocationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SpecialStockLocationCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new EnumField(
                'technical_name',
                'technicalName',
                self::TECHNICAL_NAMES,
            ))->addFlags(new PrimaryKey(), new Required()),

            // Associations that exist only to define restrict delete / cascade delete / set null
            (new OneToManyAssociationField(
                'sourceStockMovements',
                StockMovementDefinition::class,
                'source_special_stock_location_technical_name',
                'technical_name',
            ))->addFlags(new RestrictDelete()),

            (new OneToManyAssociationField(
                'destinationStockMovements',
                StockMovementDefinition::class,
                'destination_special_stock_location_technical_name',
                'technical_name',
            ))->addFlags(new RestrictDelete()),

            (new OneToManyAssociationField(
                'stocks',
                StockDefinition::class,
                'special_stock_location_technical_name',
                'technical_name',
            ))->addFlags(new RestrictDelete()),
        ]);
    }
}
