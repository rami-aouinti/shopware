<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Model;

use Pickware\DocumentBundle\Document\Model\DocumentDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class GoodsReceiptDocumentMappingDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_goods_receipt_document_mapping';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField(
                'pickware_document_id',
                'pickwareDocumentId',
                DocumentDefinition::class,
            ))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('pickware_document', 'pickware_document_id', DocumentDefinition::class),

            (new FkField(
                'goods_receipt_id',
                'goodsReceiptId',
                GoodsReceiptDefinition::class,
            ))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('goods_receipt', 'goods_receipt_id', GoodsReceiptDefinition::class),
        ]);
    }
}
