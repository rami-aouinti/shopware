<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProperty;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\PickingProperty\OrderDocumentPickingPropertyRecord;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyRecord;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyRecordValue;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Pickware\PickwareWms\PickingProperty\Model\PickingPropertyDeliveryRecordValueEntity;
use Shopware\Core\Framework\Context;

/**
 * This provider is used to add picking property delivery records to order documents. Because upon order document
 * creation via WMS, the picking property records are not yet persisted in the order - so they are only known to WMS.
 *
 * For invoices, WMS picking properties are added additionally.
 * For delivery notes, any existing picking properties are removed and WMS picking properties added exclusively.
 * See: https://github.com/pickware/shopware-plugins/issues/8871#issuecomment-2858498190
 */
class OrderDocumentPickingPropertyProvider
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * @return ImmutableCollection<OrderDocumentPickingPropertyRecord>>
     */
    public function getOrderDocumentPickingProperties(string $deliveryId, Context $context): ImmutableCollection
    {
        /** @var DeliveryEntity $delivery */
        $delivery = $this->entityManager->getByPrimaryKey(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
            ['pickingPropertyRecords.values'],
        );

        $orderDocumentPickingPropertyRecords = [];
        $orderId = $delivery->getOrderId();
        foreach ($delivery->getPickingPropertyRecords() as $pickingPropertyRecord) {
            $recordsForDocuments = array_map(
                fn(PickingPropertyDeliveryRecordValueEntity $value) => new PickingPropertyRecordValue(
                    $value->getName(),
                    $value->getValue(),
                ),
                $pickingPropertyRecord->getValues()->getElements(),
            );
            if (count($recordsForDocuments) === 0) {
                continue;
            }

            $orderDocumentPickingPropertyRecords[] = new OrderDocumentPickingPropertyRecord(
                orderId: $orderId,
                pickingPropertyRecord: new PickingPropertyRecord(
                    productId: $pickingPropertyRecord->getProductId(),
                    productSnapshot: [], // Not relevant for document generation
                    pickingPropertyRecordValues: $recordsForDocuments,
                ),
            );
        }

        return new ImmutableCollection($orderDocumentPickingPropertyRecords);
    }
}
