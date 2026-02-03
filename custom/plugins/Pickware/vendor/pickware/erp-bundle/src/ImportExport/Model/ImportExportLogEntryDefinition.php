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
use Pickware\DalBundle\Field\PhpEnumField;
use Pickware\PickwareErpStarter\ImportExport\ImportExportLogEntryMessage;
use Pickware\PickwareErpStarter\ImportExport\ImportExportLogLevel;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<ImportExportLogEntryEntity>
 */
class ImportExportLogEntryDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_import_export_log_entry';

    /**
     * @deprecated will be removed in version 5. Use {@link ImportExportLogLevel::Info} instead
     */
    public const LOG_LEVEL_INFO = 'info';

    /**
     * @deprecated will be removed in version 5. Use {@link ImportExportLogLevel::Warning} instead
     */
    public const LOG_LEVEL_WARNING = 'warning';

    /**
     * @deprecated will be removed in version 5. Use {@link ImportExportLogLevel::Error} instead
     */
    public const LOG_LEVEL_ERROR = 'error';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ImportExportLogEntryCollection::class;
    }

    public function getEntityClass(): string
    {
        return ImportExportLogEntryEntity::class;
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

                new IntField('row_number', 'rowNumber', minValue: 0),

                (new PhpEnumField('log_level', 'logLevel', ImportExportLogLevel::class))
                    ->addFlags(new Required()),
                (new JsonSerializableObjectField(
                    'message',
                    'message',
                    ImportExportLogEntryMessage::class,
                    fn($value) => ImportExportLogEntryMessage::fromArray($value),
                ))->addFlags(new Required()),
            ],
        );
    }
}
