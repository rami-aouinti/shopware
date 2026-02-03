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

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class ReturnOrderGoodsReceiptMappingDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_return_order_goods_receipt_mapping';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField(
                'return_order_id',
                'returnOrderId',
                ReturnOrderDefinition::class,
            ))->addFlags(new PrimaryKey(), new Required()),
            // Shopware assumes that the version field of a Many-To-Many-Association is named "<EntityName>_version_id", see
            // https://github.com/shopware/shopware/blob/540d526e61e32c7065d1eae6d74a81f5e1728cb9/src/Core/Framework/DataAbstractionLayer/Dbal/FieldResolver/ManyToManyAssociationFieldResolver.php#L87
            (new FixedReferenceVersionField(ReturnOrderDefinition::class, 'pickware_erp_return_order_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('return_order', 'return_order_id', ReturnOrderDefinition::class),

            (new FkField(
                'goods_receipt_id',
                'goodsReceiptId',
                GoodsReceiptDefinition::class,
            ))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('goods_receipt', 'goods_receipt_id', GoodsReceiptDefinition::class),
        ]);
    }
}
