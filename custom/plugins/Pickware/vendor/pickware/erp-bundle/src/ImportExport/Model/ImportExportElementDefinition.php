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

use Pickware\DalBundle\Field\JsonSerializableObjectField;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<ImportExportElementEntity>
 */
class ImportExportElementDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_import_export_element';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ImportExportElementCollection::class;
    }

    public function getEntityClass(): string
    {
        return ImportExportElementEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

                (new FkField(
                    'import_export_id',
                    'importExportId',
                    ImportExportDefinition::class,
                ))->addFlags(new Required()),
                new ManyToOneAssociationField('importExport', 'import_export_id', ImportExportDefinition::class, 'id'),

                (new IntField('row_number', 'rowNumber'))->addFlags(new Required()),
                (new JsonField('row_data', 'rowData'))->addFlags(new Required()),

                /** @deprecated Will be removed in the next major version. Use `ImportExportLogEntry` instead */
                new JsonSerializableObjectField('errors', 'errors', JsonApiErrors::class, fn($value) => new JsonApiErrors($value)),
            ],
        );
    }
}
