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

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<StockMovementProcessTypeEntity>
 */
class StockMovementProcessTypeDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_stock_movement_process_type';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return StockMovementProcessTypeEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('technical_name', 'technicalName'))->addFlags(new PrimaryKey(), new Required()),

            (new StringField('referenced_entity_field_name', 'referencedEntityFieldName'))->addFlags(new Required()),
            (new StringField('referenced_entity_definition_class', 'referencedEntityDefinitionClass'))->addFlags(new Required()),

            new OneToManyAssociationField('stockMovementProcesses', StockMovementProcessDefinition::class, 'type_technical_name', 'technical_name'),
        ]);
    }
}
