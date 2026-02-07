<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<ImportExportProfileEntity>
 */
class ImportExportProfileDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_import_export_profile';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('technical_name', 'technicalName'))->addFlags(new PrimaryKey(), new Required()),
            (new IntField('log_retention_days', 'logRetentionDays'))->addFlags(new Required()),

            (new OneToManyAssociationField(
                'importExports',
                ImportExportDefinition::class,
                'profile_technical_name',
                'technical_name',
            ))->addFlags(new RestrictDelete()),

        ]);
    }

    public function getCollectionClass(): string
    {
        return ImportExportProfileCollection::class;
    }

    public function getEntityClass(): string
    {
        return ImportExportProfileEntity::class;
    }
}
