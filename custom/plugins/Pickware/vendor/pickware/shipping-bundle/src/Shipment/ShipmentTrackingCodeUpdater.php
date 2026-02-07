<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment;

use Pickware\DalBundle\EntityManager;
use Pickware\ShippingBundle\Shipment\Model\ShipmentCollection;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\Model\ShipmentEntity;
use Pickware\ShippingBundle\Shipment\Model\ShippingDirection;
use Pickware\ShippingBundle\Shipment\Model\TrackingCodeEntity;
use Pickware\ShopwareExtensionsBundle\OrderDelivery\OrderDeliveryCollectionExtension;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class ShipmentTrackingCodeUpdater
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function copyTrackingCodesOfShipmentsToOrders(array $shipmentIds, Context $context): void
    {
        /** @var ShipmentCollection $shipments */
        $shipments = $this->entityManager->findBy(
            ShipmentDefinition::class,
            ['id' => $shipmentIds],
            $context,
            [
                'trackingCodes',
                'orders.deliveries',
            ],
        );

        $payloads = [];
        /** @var ShipmentEntity $shipment */
        foreach ($shipments as $shipment) {
            $shipmentTrackingCodes = $shipment->getTrackingCodes()
                ->filter(fn(TrackingCodeEntity $trackingCode) => $trackingCode->getShippingDirection() === ShippingDirection::Outgoing)
                ->map(fn(TrackingCodeEntity $trackingCode) => $trackingCode->getTrackingCode());

            /** @var OrderEntity $order */
            foreach ($shipment->getOrders() as $order) {
                $orderDelivery = OrderDeliveryCollectionExtension::primaryOrderDelivery($order->getDeliveries());
                if ($orderDelivery) {
                    $orderTrackingCodes = $orderDelivery->getTrackingCodes();
                    $updatedTrackingCodes = $this->synchronizeTrackingCodes(
                        $orderTrackingCodes,
                        $shipmentTrackingCodes,
                        $shipment,
                    );
                    $payloads[] = [
                        'id' => $order->getId(),
                        'deliveries' => [
                            [
                                'id' => $orderDelivery->getId(),
                                'trackingCodes' => $updatedTrackingCodes,
                            ],
                        ],
                    ];
                }
            }
        }

        $this->persistOrderTrackingCodes($payloads, $context);
    }

    public function copyReturnTrackingCodesOfShipmentsToOrders(array $returnShipmentIds, Context $context): void
    {
        /** @var ShipmentCollection $shipments */
        $shipments = $this->entityManager->findBy(
            ShipmentDefinition::class,
            ['id' => $returnShipmentIds],
            $context,
            [
                'trackingCodes',
                'orders',
            ],
        );

        $payloads = [];
        /** @var ShipmentEntity $shipment */
        foreach ($shipments as $shipment) {
            $shipmentReturnTrackingCodes = $shipment->getTrackingCodes()
                ->filter(fn(TrackingCodeEntity $trackingCode) => $trackingCode->getShippingDirection() === ShippingDirection::Incoming)
                ->map(fn(TrackingCodeEntity $trackingCode) => $trackingCode->getTrackingCode());

            /** @var OrderEntity $order */
            foreach ($shipment->getOrders() as $order) {
                $returnTrackingCodeString = $order->getCustomFields()[ReturnTrackingCodesCustomFieldSet::CUSTOM_FIELD_NAME_RETURN_TRACKING_CODES] ?? '';
                $returnTrackingCodes = $returnTrackingCodeString !== '' ? explode(',', $returnTrackingCodeString) : [];
                $updatedReturnTrackingCodes = $this->synchronizeTrackingCodes(
                    $returnTrackingCodes,
                    $shipmentReturnTrackingCodes,
                    $shipment,
                );
                $payloads[] = [
                    'id' => $order->getId(),
                    'customFields' => [
                        ReturnTrackingCodesCustomFieldSet::CUSTOM_FIELD_NAME_RETURN_TRACKING_CODES => implode(',', $updatedReturnTrackingCodes),
                    ],
                ];
            }
        }

        $this->persistOrderTrackingCodes($payloads, $context);
    }

    private function synchronizeTrackingCodes(array $orderTrackingCodes, array $shipmentTrackingCodes, ShipmentEntity $shipment): array
    {
        if ($shipment->isCancelled()) {
            // Remove tracking codes from list
            return array_values(array_diff($orderTrackingCodes, $shipmentTrackingCodes));
        }

        // Add tracking codes to list
        return array_values(array_unique(array_merge($orderTrackingCodes, $shipmentTrackingCodes)));
    }

    private function persistOrderTrackingCodes(array $payloads, Context $context): void
    {
        /**
         * We enter the system scope here to ensure that all current callsites of the shipment service
         * can update the order even though they lack the required permissions.
         *
         * @deprecated tag:4.0.0 we only enter the system scope here to ensure backwards compatibility,
         * after the next major all callsites are expected to have all required permissions
         * or enter system scope themselves
         */
        $context->scope(
            Context::SYSTEM_SCOPE,
            fn() => $this->entityManager->update(OrderDefinition::class, $payloads, $context),
        );
    }
}
