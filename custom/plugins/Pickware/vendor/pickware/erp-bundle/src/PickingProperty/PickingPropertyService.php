<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PickingProperty;

use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\PickingProperty\Model\PickingPropertyOrderRecordDefinition;
use Shopware\Core\Framework\Context;

class PickingPropertyService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @param PickingPropertyRecord[] $pickingPropertyRecords
     */
    public function createPickingPropertyRecordsForOrder(
        string $orderId,
        array $pickingPropertyRecords,
        Context $context,
    ): void {
        $this->entityManager->create(
            PickingPropertyOrderRecordDefinition::class,
            array_map(
                fn(PickingPropertyRecord $record) => [
                    'productId' => $record->getProductId(),
                    'productSnapshot' => $record->getProductSnapshot(),
                    'orderId' => $orderId,
                    'values' => array_map(
                        fn(PickingPropertyRecordValue $recordValue) => [
                            'name' => $recordValue->getName(),
                            'value' => $recordValue->getValue(),
                        ],
                        $record->getPickingPropertyRecordValues(),
                    ),
                ],
                $pickingPropertyRecords,
            ),
            $context,
        );
    }

    /**
     * This method exists to allow feature detection in the pickware-wms plugin.
     */
    public function arePickingPropertiesAvailable(): bool
    {
        // When removing the feature flag, this method should always return true.
        return true;
    }
}
