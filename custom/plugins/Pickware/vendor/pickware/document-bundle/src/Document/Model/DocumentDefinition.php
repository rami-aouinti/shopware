<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Document\Model;

use Pickware\DalBundle\Field\EnumField;
use Pickware\DalBundle\Field\JsonSerializableObjectField;
use Pickware\DalBundle\Field\NonUuidFkField;
use Pickware\DocumentBundle\Document\PageFormat;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Util\Random;

/**
 * @extends EntityDefinition<DocumentEntity>
 */
class DocumentDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_document';
    public const ENTITY_LOADED_EVENT = self::ENTITY_NAME . '.loaded';
    public const ENTITY_DELETED_EVENT = self::ENTITY_NAME . '.deleted';
    public const DEEP_LINK_CODE_LENGTH = 32;

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return DocumentCollection::class;
    }

    public function getEntityClass(): string
    {
        return DocumentEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('deep_link_code', 'deepLinkCode', self::DEEP_LINK_CODE_LENGTH))->addFlags(new Required()),

            (new NonUuidFkField(
                'document_type_technical_name',
                'documentTypeTechnicalName',
                DocumentTypeDefinition::class,
                'technicalName',
            ))->addFlags(new Required()),
            new ManyToOneAssociationField(
                'documentType',
                'document_type_technical_name',
                DocumentTypeDefinition::class,
                'technical_name',
            ),

            (new StringField('path_in_private_file_system', 'pathInPrivateFileSystem'))->addFlags(new Required()),
            (new IntField('file_size_in_bytes', 'fileSizeInBytes'))->addFlags(new Required()),

            new StringField('file_name', 'fileName'),
            new StringField('mime_type', 'mimeType'),
            new EnumField('orientation', 'orientation', DocumentEntity::ORIENTATIONS),
            new JsonSerializableObjectField('page_format', 'pageFormat', PageFormat::class),
        ]);
    }

    public function getDefaults(): array
    {
        return [
            'deepLinkCode' => Random::getString(self::DEEP_LINK_CODE_LENGTH, implode(range('a', 'z')) . implode(range(0, 9))),
        ];
    }
}
