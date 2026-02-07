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

use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class SupplierOrderGoodsReceiptMappingDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_supplier_order_goods_receipt_mapping';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField(
                'supplier_order_id',
                'supplierOrderId',
                SupplierOrderDefinition::class,
            ))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('supplier_order', 'supplier_order_id', SupplierOrderDefinition::class),

            (new FkField(
                'goods_receipt_id',
                'goodsReceiptId',
                GoodsReceiptDefinition::class,
            ))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('goods_receipt', 'goods_receipt_id', GoodsReceiptDefinition::class),
        ]);
    }
}
