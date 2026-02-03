<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery\Model;

use Shopware\Core\Checkout\Document\DocumentDefinition as OrderDocumentDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class DeliveryOrderDocumentMappingDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_delivery_order_document_mapping';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField(
                'delivery_id',
                'deliveryId',
                DeliveryDefinition::class,
            ))->addFlags(new Required(), new PrimaryKey()),
            new ManyToOneAssociationField('delivery', 'delivery_id', DeliveryDefinition::class),

            (new FkField(
                'order_document_id',
                'orderDocumentId',
                OrderDocumentDefinition::class,
            ))->addFlags(new Required(), new PrimaryKey()),
            new ManyToOneAssociationField('orderDocument', 'order_document_id', OrderDocumentDefinition::class),
        ]);
    }
}
