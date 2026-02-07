<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\InvoiceCorrection\Model;

use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<PickwareDocumentVersionEntity>
 */
class PickwareDocumentVersionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_document_version';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

                (new FkField('document_id', 'documentId', DocumentDefinition::class))->addFlags(new Required()),
                new OneToOneAssociationField('document', 'document_id', 'id', DocumentDefinition::class, false),

                (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required()),
                (new FkField('order_version_id', 'orderVersionId', OrderDefinition::class))->addFlags(new Required()),
                new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id'),
            ],
        );
    }

    public function getEntityClass(): string
    {
        return PickwareDocumentVersionEntity::class;
    }
}
