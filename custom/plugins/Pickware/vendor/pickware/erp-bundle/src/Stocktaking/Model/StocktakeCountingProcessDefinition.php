<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\Model;

use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopware\Core\System\User\UserDefinition;

/**
 * @extends EntityDefinition<StocktakeCountingProcessEntity>
 */
class StocktakeCountingProcessDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_stocktaking_stocktake_counting_process';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';
    public const ENTITY_DELETED_EVENT = self::ENTITY_NAME . '.deleted';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new NumberRangeField('number', 'number', 255))->addFlags(
                new Required(),
                new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING, false),
            ),

            (new FkField('stocktake_id', 'stocktakeId', StocktakeDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('stocktake', 'stocktake_id', StocktakeDefinition::class, 'id'),

            new FkField('bin_location_id', 'binLocationId', BinLocationDefinition::class),
            new ManyToOneAssociationField('binLocation', 'bin_location_id', BinLocationDefinition::class, 'id'),
            new JsonField('bin_location_snapshot', 'binLocationSnapshot'),

            new FkField('user_id', 'userId', UserDefinition::class),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id'),
            (new JsonField('user_snapshot', 'userSnapshot'))->addFlags(new Required()),

            // Associations with foreign keys on the other side

            (new OneToManyAssociationField(
                'items',
                StocktakeCountingProcessItemDefinition::class,
                'counting_process_id',
                'id',
            ))->addFlags(new CascadeDelete()),
        ]);
    }

    public function getCollectionClass(): string
    {
        return StocktakeCountingProcessCollection::class;
    }

    public function getEntityClass(): string
    {
        return StocktakeCountingProcessEntity::class;
    }
}
