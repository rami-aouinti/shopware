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

use Pickware\DalBundle\Field\EnumField;
use Pickware\DalBundle\Field\JsonSerializableObjectField;
use Pickware\DalBundle\Field\NonUuidFkField;
use Pickware\DocumentBundle\Document\Model\DocumentDefinition;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\User\UserDefinition;

/**
 * @extends EntityDefinition<ImportExportEntity>
 */
class ImportExportDefinition extends EntityDefinition
{
    public const READER_TECHNICAL_NAME_CONFIG_KEY = 'readerTechnicalName';
    public const WRITER_TECHNICAL_NAME_CONFIG_KEY = 'writerTechnicalName';

    // Constants are sorted by their chronological order
    public const STATE_PENDING = 'pending';
    public const STATE_VALIDATING_FILE = 'validating_file';
    public const STATE_READING_FILE = 'reading_file';
    public const STATE_WRITING_FILE = 'writing_file';
    public const STATE_RUNNING = 'running';
    public const STATE_COMPLETED = 'completed';
    public const STATE_COMPLETED_WITH_ERRORS = 'completed_with_errors';
    public const STATE_FAILED = 'failed';
    public const STATES = [
        self::STATE_COMPLETED,
        self::STATE_COMPLETED_WITH_ERRORS,
        self::STATE_FAILED,
        self::STATE_PENDING,
        self::STATE_READING_FILE,
        self::STATE_WRITING_FILE,
        self::STATE_RUNNING,
        self::STATE_VALIDATING_FILE,
    ];
    public const TYPE_EXPORT = 'export';
    public const TYPE_IMPORT = 'import';
    public const TYPES = [
        self::TYPE_EXPORT,
        self::TYPE_IMPORT,
    ];
    public const STOCK_GRANULARITY_TYPE_PER_STOCK_LOCATION = 'per-stock-location';
    public const STOCK_GRANULARITY_TYPE_PER_WAREHOUSE = 'per-warehouse';
    public const STOCK_GRANULARITY_TYPE_PER_PRODUCT = 'per-product';
    public const STOCK_GRANULARITY_TYPES = [
        self::STOCK_GRANULARITY_TYPE_PER_STOCK_LOCATION,
        self::STOCK_GRANULARITY_TYPE_PER_WAREHOUSE,
        self::STOCK_GRANULARITY_TYPE_PER_PRODUCT,
    ];
    public const ENTITY_NAME = 'pickware_erp_import_export';
    public const ENTITY_LOADED_EVENT = self::ENTITY_NAME . '.loaded';
    public const ENTITY_PARTIAL_LOADED_EVENT = self::ENTITY_NAME . '.partial_loaded';
    public const EVENT_DELETED = self::ENTITY_NAME . '.deleted';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
                (new EnumField('type', 'type', self::TYPES))->addFlags(new Required()),
                (new NonUuidFkField(
                    'profile_technical_name',
                    'profileTechnicalName',
                    ImportExportProfileDefinition::class,
                    'technicalName',
                ))->addFlags(
                    new Required(),
                ),
                new ManyToOneAssociationField(
                    'profile',
                    'profile_technical_name',
                    ImportExportProfileDefinition::class,
                    'technical_name',
                ),
                (new JsonField('config', 'config'))->addFlags(new Required()),

                new FkField('user_id', 'userId', UserDefinition::class),
                new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id'),
                new LongTextField('user_comment', 'userComment'),

                (new EnumField('state', 'state', self::STATES))->addFlags(new Required()),
                (new JsonField('state_data', 'stateData', [], []))->addFlags(new Required()),
                (new IntField('current_item', 'currentItem', 0))->addFlags(),
                (new IntField('total_number_of_items', 'totalNumberOfItems', 0))->addFlags(),
                (new BoolField('is_download_ready', 'isDownloadReady'))->addFlags(new Runtime(dependsOn: ['documentId', 'type', 'state'])),
                (new DateTimeField('started_at', 'startedAt')),
                (new DateTimeField('completed_at', 'completedAt')),
                (new BoolField('logs_truncated', 'logsTruncated'))->addFlags(new Required()),

                /** @deprecated Will be removed in the next major version. Use `ImportExportLogEntry` instead */
                new JsonSerializableObjectField('errors', 'errors', JsonApiErrors::class, fn($value) => new JsonApiErrors($value)),

                new FkField('document_id', 'documentId', DocumentDefinition::class),
                new OneToOneAssociationField('document', 'document_id', 'id', DocumentDefinition::class, false),

                // Associations with foreign keys on the other side
                (new OneToManyAssociationField(
                    'importExportElements',
                    ImportExportElementDefinition::class,
                    'import_export_id',
                    'id',
                ))->addFlags(new CascadeDelete()),

                (new OneToManyAssociationField(
                    'importExportLogEntries',
                    ImportExportLogEntryDefinition::class,
                    'import_export_id',
                    'id',
                ))->addFlags(new CascadeDelete()),
            ],
        );
    }

    public function getCollectionClass(): string
    {
        return ImportExportCollection::class;
    }

    public function getEntityClass(): string
    {
        return ImportExportEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'logsTruncated' => false,
        ];
    }
}
