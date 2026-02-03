<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Config;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\ShippingBundle\Config\Model\ShippingMethodConfigDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Writes the shipping method technical name into the shipment config of the shipping method config for a passed
 * carrier technical name when a shipping method or a shipping method config is updated.
 */
class ShippingMethodConfigUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $carrierTechnicalName,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ShippingMethodConfigDefinition::ENTITY_WRITTEN_EVENT => 'shippingMethodConfigWritten',
            ShippingMethodDefinition::ENTITY_NAME . '.written' => 'shippingMethodWritten',
        ];
    }

    public function shippingMethodConfigWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $shippingMethodConfigIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            $operation = $writeResult->getOperation();
            if ($operation === EntityWriteResult::OPERATION_INSERT || $operation === EntityWriteResult::OPERATION_UPDATE) {
                $shippingMethodConfigIds[] = $writeResult->getPrimaryKey();
            }
        }
        if (count($shippingMethodConfigIds) === 0) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE `pickware_shipping_shipping_method_config` pickwareShippingMethodConfig
            INNER JOIN `shipping_method` shippingMethod
              ON shippingMethod.`id` = pickwareShippingMethodConfig.`shipping_method_id`

            SET pickwareShippingMethodConfig.`shipment_config` = IF(
                  pickwareShippingMethodConfig.`shipment_config` = "[]",
                  JSON_OBJECT("shippingMethodTechnicalName", shippingMethod.`technical_name`),
                  JSON_SET(pickwareShippingMethodConfig.`shipment_config`, "$.shippingMethodTechnicalName", shippingMethod.`technical_name`)
            )

            WHERE pickwareShippingMethodConfig.`id` IN (:shippingMethodConfigIds)
            AND pickwareShippingMethodConfig.`carrier_technical_name` = :carrierTechnicalName;',
            [
                'shippingMethodConfigIds' => array_map('hex2bin', $shippingMethodConfigIds),
                'carrierTechnicalName' => $this->carrierTechnicalName,
            ],
            ['shippingMethodConfigIds' => ArrayParameterType::STRING],
        );
    }

    public function shippingMethodWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $shippingMethodIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            $shippingMethodIds[] = $writeResult->getPrimaryKey();
        }
        if (count($shippingMethodIds) === 0) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE `pickware_shipping_shipping_method_config` pickwareShippingMethodConfig
            INNER JOIN `shipping_method` shippingMethod
              ON shippingMethod.`id` = pickwareShippingMethodConfig.`shipping_method_id`

            SET pickwareShippingMethodConfig.`shipment_config` = IF(
                  pickwareShippingMethodConfig.`shipment_config` = "[]",
                  JSON_OBJECT("shippingMethodTechnicalName", shippingMethod.`technical_name`),
                  JSON_SET(pickwareShippingMethodConfig.`shipment_config`, "$.shippingMethodTechnicalName", shippingMethod.`technical_name`)
            )

            WHERE shippingMethod.`id` IN (:shippingMethodIds)
            AND pickwareShippingMethodConfig.`carrier_technical_name` = :carrierTechnicalName;',
            [
                'shippingMethodIds' => array_map('hex2bin', $shippingMethodIds),
                'carrierTechnicalName' => $this->carrierTechnicalName,
            ],
            ['shippingMethodIds' => ArrayParameterType::STRING],
        );
    }
}
