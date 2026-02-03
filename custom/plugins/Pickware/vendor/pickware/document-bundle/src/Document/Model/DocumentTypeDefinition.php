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

use Pickware\DalBundle\Field\JsonTranslationField;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<DocumentTypeEntity>
 */
class DocumentTypeDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_document_type';
    public const ENTITY_LOADED_EVENT = self::ENTITY_NAME . '.loaded';
    public const ENTITY_PARTIAL_LOADED_EVENT = self::ENTITY_NAME . '.partial_loaded';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return DocumentTypeCollection::class;
    }

    public function getEntityClass(): string
    {
        return DocumentTypeEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('technical_name', 'technicalName'))->addFlags(new Required(), new PrimaryKey()),
            // This field was removed, by accident in https://github.com/pickware/shopware-plugins/commit/e34370c0805564026d2ecd78cab97844b368e395#diff-b34fe994d5660a1e7c41514af0e9034164272ac3223b0f946fab5ebef86452b9
            // To ensure backwards compatibility to the apps, we add a Runtime field. This can be removed as soon as all
            // apps require at least pickware-wms-2.20.1.
            (new StringField('description', 'description'))->addFlags(new Runtime(dependsOn: ['singularDescription'])),
            (new JsonTranslationField('singular_description', 'singularDescription'))->addFlags(new Required()),
            (new JsonTranslationField('plural_description', 'pluralDescription'))->addFlags(new Required()),
            (new OneToManyAssociationField(
                'documents',
                DocumentDefinition::class,
                'document_type_technical_name',
                'technical_name',
            ))->addFlags(new RestrictDelete()),
        ]);
    }
}
