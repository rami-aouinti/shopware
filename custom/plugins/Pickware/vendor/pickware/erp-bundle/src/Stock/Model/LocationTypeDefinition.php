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
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<LocationTypeEntity>
 */
class LocationTypeDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_location_type';
    public const TECHNICAL_NAME_WAREHOUSE = 'warehouse';
    public const TECHNICAL_NAME_BIN_LOCATION = 'bin_location';
    public const TECHNICAL_NAME_ORDER = 'order';
    public const TECHNICAL_NAME_RETURN_ORDER = 'return_order';
    public const TECHNICAL_NAME_SPECIAL_STOCK_LOCATION = 'special_stock_location';
    public const TECHNICAL_NAME_STOCK_CONTAINER = 'stock_container';
    public const TECHNICAL_NAME_GOODS_RECEIPT = 'goods_receipt';
    public const TECHNICAL_NAMES = [
        self::TECHNICAL_NAME_WAREHOUSE,
        self::TECHNICAL_NAME_BIN_LOCATION,
        self::TECHNICAL_NAME_ORDER,
        self::TECHNICAL_NAME_RETURN_ORDER,
        self::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION,
        self::TECHNICAL_NAME_STOCK_CONTAINER,
        self::TECHNICAL_NAME_GOODS_RECEIPT,
    ];
    public const TECHNICAL_NAMES_INTERNAL = [
        self::TECHNICAL_NAME_BIN_LOCATION,
        self::TECHNICAL_NAME_WAREHOUSE,
        self::TECHNICAL_NAME_STOCK_CONTAINER,
        self::TECHNICAL_NAME_GOODS_RECEIPT,
    ];
    public const TECHNICAL_NAMES_EXTERNAL = [
        self::TECHNICAL_NAME_ORDER,
        self::TECHNICAL_NAME_RETURN_ORDER,
        self::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION,
    ];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return LocationTypeEntity::class;
    }

    public function getCollectionClass(): string
    {
        return LocationTypeCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new EnumField('technical_name', 'technicalName', self::TECHNICAL_NAMES))
                ->addFlags(new PrimaryKey(), new Required()),
            (new BoolField('internal', 'internal'))->addFlags(new Required()),

            // Associations that exist only to define restrict delete / cascade delete / set null
            (new OneToManyAssociationField(
                'sourceStockMovements',
                StockMovementDefinition::class,
                'source_location_type_technical_name',
                'technical_name',
            ))->addFlags(new RestrictDelete()),

            (new OneToManyAssociationField(
                'destinationStockMovements',
                StockMovementDefinition::class,
                'destination_location_type_technical_name',
                'technical_name',
            ))->addFlags(new RestrictDelete()),

            (new OneToManyAssociationField(
                'stocks',
                StockDefinition::class,
                'location_type_technical_name',
                'technical_name',
            ))->addFlags(new RestrictDelete()),
        ]);
    }
}
