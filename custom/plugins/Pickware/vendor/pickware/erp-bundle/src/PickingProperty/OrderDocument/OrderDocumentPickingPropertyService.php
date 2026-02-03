<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PickingProperty\OrderDocument;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\PickingProperty\Model\PickingPropertyCollection;
use Pickware\PickwareErpStarter\PickingProperty\Model\PickingPropertyDefinition;
use Pickware\PickwareErpStarter\PickingProperty\Model\PickingPropertyEntity;
use Pickware\PickwareErpStarter\PickingProperty\Model\PickingPropertyOrderRecordCollection;
use Pickware\PickwareErpStarter\PickingProperty\Model\PickingPropertyOrderRecordDefinition;
use Pickware\PickwareErpStarter\PickingProperty\Model\PickingPropertyOrderRecordValueEntity;
use Pickware\PickwareErpStarter\PickingProperty\OrderDocumentPickingPropertyRecord;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyRecord;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyRecordValue;
use Shopware\Core\Framework\Context;

class OrderDocumentPickingPropertyService
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * @param string[] $orderIds
     * @return ImmutableCollection<OrderDocumentPickingPropertyRecord>
     */
    public function getOrderDocumentPickingPropertyRecordsForOrderDocumentCreation(
        array $orderIds,
        Context $context,
    ): ImmutableCollection {
        if (count($orderIds) === 0) {
            return new ImmutableCollection();
        }

        /** @var PickingPropertyOrderRecordCollection $pickingPropertyOrderRecords */
        $pickingPropertyOrderRecords = $this->entityManager->findBy(
            PickingPropertyOrderRecordDefinition::class,
            ['orderId' => $orderIds],
            $context,
            ['values'],
        );

        $orderDocumentPickingPropertyRecords = [];
        foreach ($pickingPropertyOrderRecords as $pickingPropertyOrderRecord) {
            $orderDocumentPickingPropertyRecords[] = new OrderDocumentPickingPropertyRecord(
                $pickingPropertyOrderRecord->getOrderId(),
                new PickingPropertyRecord(
                    $pickingPropertyOrderRecord->getProductId(),
                    $pickingPropertyOrderRecord->getProductSnapshot(),
                    $pickingPropertyOrderRecord->getValues()->map(
                        fn(PickingPropertyOrderRecordValueEntity $recordValue) => new PickingPropertyRecordValue(
                            $recordValue->getName(),
                            $recordValue->getValue(),
                        ),
                    ),
                ),
            );
        }

        return new ImmutableCollection($orderDocumentPickingPropertyRecords);
    }

    /**
     * We match picking property record values (values that have been, at some point, added as records to the order) to
     * the existing picking property entities (can be configured by the user in the plugin configuration) by name.
     * This way the user can manage the picking property display of old and new orders.
     *
     * Known limitation: We have no direct reference from the picking property records to the picking property entities
     * which can be edited in the plugin config. We match both entities by name.
     * This means that if the user changes the name of the picking property, any picking property record of that picking
     * property (in the past) cannot match and will never be shown on documents.
     *
     * @param ImmutableCollection<OrderDocumentPickingPropertyRecord> $collection
     * @return ImmutableCollection<OrderDocumentPickingPropertyRecord>
     */
    public function filterOrderDocumentPickingPropertyRecordsForDocuments(
        ImmutableCollection $collection,
        Context $context,
    ): ImmutableCollection {
        /** @var PickingPropertyCollection $pickingPropertiesOnInvoiceDocument */
        $pickingPropertiesOnInvoiceDocument = $this->entityManager->findBy(
            PickingPropertyDefinition::class,
            ['showOnOrderDocuments' => true],
            $context,
        );
        $pickingPropertyNames = $pickingPropertiesOnInvoiceDocument->map(fn(PickingPropertyEntity $pickingProperty) => $pickingProperty->getName());

        $filteredOrderDocumentPickingPropertyRecords = [];
        foreach ($collection as $orderDocumentPickingPropertyRecord) {
            $pickingPropertyRecord = $orderDocumentPickingPropertyRecord->pickingPropertyRecord;
            $recordValues = array_filter(
                $pickingPropertyRecord->getPickingPropertyRecordValues(),
                fn(PickingPropertyRecordValue $value) => in_array($value->getName(), $pickingPropertyNames, true),
            );
            if (count($recordValues) === 0) {
                continue;
            }

            $filteredOrderDocumentPickingPropertyRecords[] = new OrderDocumentPickingPropertyRecord(
                $orderDocumentPickingPropertyRecord->orderId,
                new PickingPropertyRecord(
                    $pickingPropertyRecord->getProductId(),
                    $pickingPropertyRecord->getProductSnapshot(),
                    $recordValues,
                ),
            );
        }

        return new ImmutableCollection($filteredOrderDocumentPickingPropertyRecords);
    }
}
