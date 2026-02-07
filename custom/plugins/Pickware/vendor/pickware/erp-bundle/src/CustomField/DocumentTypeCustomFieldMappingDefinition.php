<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\CustomField;

use Pickware\DalBundle\Field\PhpEnumField;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\CustomField\CustomFieldDefinition;

/**
 * @extends EntityDefinition<DocumentTypeCustomFieldMappingEntity>
 */
class DocumentTypeCustomFieldMappingDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_document_type_custom_field_mapping';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return DocumentTypeCustomFieldMappingCollection::class;
    }

    public function getEntityClass(): string
    {
        return DocumentTypeCustomFieldMappingEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),

            (new FkField(
                'document_type_id',
                'documentTypeId',
                DocumentTypeDefinition::class,
                'id',
            ))->addFlags(new Required()),
            new ManyToOneAssociationField(
                'documentType',
                'document_type_id',
                DocumentTypeDefinition::class,
                'id',
            ),

            (new FkField(
                'custom_field_id',
                'customFieldId',
                CustomFieldDefinition::class,
            ))->addFlags(new Required()),
            new ManyToOneAssociationField('customField', 'custom_field_id', CustomFieldDefinition::class),

            (new IntField('position', 'position'))->addFlags(new Required()),
            (new PhpEnumField('entity_type', 'entityType', DocumentCustomFieldTargetEntityType::class))->addFlags(new Required()),
        ]);
    }
}
