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
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyRecordValue;
use Pickware\PickwareWms\PickingProperty\Model\PickingPropertyDeliveryRecordDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;

class PickingPropertyService
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * @param PickingPropertyRecordValue[][] $pickingPropertyRecords An array of grouped record values
     */
    public function savePickingPropertiesForDeliveryItem(
        string $deliveryId,
        array $pickingPropertyRecords,
        string $productId,
        Context $context,
    ): void {
        if ($pickingPropertyRecords === []) {
            return;
        }

        /** @var ProductEntity $product */
        $product = $this->entityManager->getByPrimaryKey(
            ProductDefinition::class,
            $productId,
            $context,
        );

        $productSnapshot = [
            'name' => $product->getName(),
            'productNumber' => $product->getProductNumber(),
        ];

        $this->entityManager->create(
            PickingPropertyDeliveryRecordDefinition::class,
            array_map(
                fn(array $recordValues) => [
                    'deliveryId' => $deliveryId,
                    'productId' => $productId,
                    'productSnapshot' => $productSnapshot,
                    'values' => array_map(
                        fn(PickingPropertyRecordValue $recordValue) => [
                            'name' => $recordValue->getName(),
                            'value' => $recordValue->getValue(),
                        ],
                        $recordValues,
                    ),
                ],
                $pickingPropertyRecords,
            ),
            $context,
        );
    }
}
