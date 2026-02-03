<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\ImportExportProfile;

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductEntity;
use Pickware\PickwareErpStarter\Stock\Model\ConfigurableStockLocation;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\ProductStockLocationConfigurationEntity;
use Pickware\PickwareErpStarter\Stock\Model\ProductStockLocationMappingDefinition;
use Pickware\PickwareErpStarter\Stock\Model\ProductStockLocationMappingEntity;
use Pickware\PickwareErpStarter\Stock\Model\WarehouseStockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\WarehouseStockEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class StockLimitConfigurationUpdater
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    /**
     * @param array<string, mixed> $normalizedRow
     */
    public function upsertStockLimitConfiguration(
        array $normalizedRow,
        string $productId,
        StockImportLocation $stockImportLocation,
        Context $context,
    ): void {
        $payload = [];
        $fieldsToUpdate = [
            'reorderPoint',
            'targetMaximumQuantity',
        ];
        foreach ($fieldsToUpdate as $fieldToUpdate) {
            if (array_key_exists($fieldToUpdate, $normalizedRow)) {
                $payload[$fieldToUpdate] = $normalizedRow[$fieldToUpdate];
            }
        }
        if (count($payload) === 0) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter($stockImportLocation->getStockImportGranularity()->getCriteriaFilterForReferencedEntity($stockImportLocation, $productId));
        $entityToUpdate = $this->entityManager->findOneBy(
            $stockImportLocation->getStockImportGranularity()->getReferencedEntityDefinitionClassName(),
            $criteria,
            $context,
        );
        if ($entityToUpdate) {
            $this->updateConfigurationEntity($stockImportLocation, $entityToUpdate, $payload, $context);
        } else {
            $this->createConfigurationEntity($stockImportLocation, $productId, $payload, $context);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateConfigurationEntity(
        StockImportLocation $stockImportLocation,
        PickwareProductEntity|ProductWarehouseConfigurationEntity|ProductStockLocationConfigurationEntity $entityToUpdate,
        array $payload,
        Context $context,
    ): void {
        $context->scope(Context::SYSTEM_SCOPE, function(Context $context) use ($entityToUpdate, $payload, $stockImportLocation): void {
            $this->entityManager->update(
                $stockImportLocation->getStockImportGranularity()->getReferencedEntityDefinitionClassName(),
                [
                    [
                        'id' => $entityToUpdate->getId(),
                        ...$payload,
                    ],
                ],
                $context,
            );
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createConfigurationEntity(StockImportLocation $stockImportLocation, string $productId, array $payload, Context $context): void
    {
        switch ($stockImportLocation->getStockImportGranularity()) {
            case StockImportGranularity::Product:
                $payload['productId'] = $productId;
                break;
            case StockImportGranularity::Warehouse:
                /** @var WarehouseStockEntity $warehouseStock */
                $warehouseStock = $this->entityManager->getOneBy(
                    WarehouseStockDefinition::class,
                    [
                        'productId' => $productId,
                        'warehouseId' => $stockImportLocation->getStockArea()->getWarehouseId(),
                    ],
                    $context,
                );
                $payload['warehouseStockId'] = $warehouseStock->getId();
                $payload['productId'] = $productId;
                $payload['warehouseId'] = $stockImportLocation->getStockArea()->getWarehouseId();
                break;
            case StockImportGranularity::StockLocation:
                /** @var ProductStockLocationMappingEntity $productStockLocationMapping */
                $productStockLocationMapping = $this->entityManager->findOneBy(
                    ProductStockLocationMappingDefinition::class,
                    (new Criteria())
                        ->addFilter(new EqualsFilter('productId', $productId))
                        ->addFilter($stockImportLocation->getStockLocationReference()->getFilterForProductStockLocationMapping()),
                    $context,
                );
                if ($productStockLocationMapping === null) {
                    $payload['productStockLocationMapping'] = [
                        'productId' => $productId,
                        $stockImportLocation->getStockLocationReference()->getFilterForStockDefinition()->getField() => $stockImportLocation->getStockLocationReference()->getFilterForStockDefinition()->getValue(),
                        'stockLocationType' => match ($stockImportLocation->getStockLocationReference()->getLocationTypeTechnicalName()) {
                            LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION => ConfigurableStockLocation::BinLocation,
                            LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE => ConfigurableStockLocation::Warehouse,
                            default => throw new InvalidArgumentException(sprintf('Invalid stock location type %s', $stockImportLocation->getStockLocationReference()->getLocationTypeTechnicalName())),
                        },
                    ];
                } else {
                    $payload['productStockLocationMappingId'] = $productStockLocationMapping->getId();
                }
        }

        $context->scope(Context::SYSTEM_SCOPE, function(Context $context) use ($payload, $stockImportLocation): void {
            $this->entityManager->create(
                $stockImportLocation->getStockImportGranularity()->getReferencedEntityDefinitionClassName(),
                [
                    $payload,
                ],
                $context,
            );
        });
    }
}
