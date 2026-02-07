<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Stock\Model\ProductStockLocationConfigurationDefinition;
use Pickware\PickwareErpStarter\Stock\Model\ProductStockLocationMappingDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductStockLocationMappingUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ProductStockLocationMappingInitializer $productStockLocationMappingInitializer,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            StockDefinition::ENTITY_WRITTEN_EVENT => 'stockWritten',
            ProductStockLocationConfigurationDefinition::ENTITY_WRITTEN_EVENT => 'cleanupUnusedProductStockLocationMappingsForProductStockLocationConfigurations',
        ];
    }

    public function stockWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        $stockIds = $this->getNewlyCreatedOrUpdatedEntityIds($entityWrittenEvent);
        $this->ensureProductStockLocationMappingsExistForStockIds($stockIds);
    }

    /**
     * Creates product stock location mappings for all given stock ids that don't have a mapping yet and are of type
     * warehouse or bin_location.
     * @param String[] $stockIds
     */
    public function ensureProductStockLocationMappingsExistForStockIds(array $stockIds): void
    {
        $this->productStockLocationMappingInitializer->ensureProductStockLocationMappingsExistForStockIds($stockIds);
    }

    /**
     * Whenever product stock location configuration is written, we check if the corresponding product stock location
     * mapping is still needed and delete it if not.
     */
    public function cleanupUnusedProductStockLocationMappingsForProductStockLocationConfigurations(EntityWrittenEvent $entityWrittenEvent): void
    {
        $configurationIds = $this->getNewlyCreatedOrUpdatedEntityIds($entityWrittenEvent);
        if (empty($configurationIds)) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('productStockLocationConfiguration.id', $configurationIds));
        $this->cleanupUnusedProductStockLocationMappings($criteria, $entityWrittenEvent->getContext());
    }

    /**
     * Deletes all product stock location mappings for the given product ids that are not needed anymore.
     * @param string[] $productIds
     */
    public function cleanupUnusedProductStockLocationMappingsForProductIds(array $productIds, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('productId', $productIds));
        $this->cleanupUnusedProductStockLocationMappings($criteria, $context);
    }

    /**
     * Deletes all product stock location mappings that are not needed anymore and fit the given criteria.
     * A mapping is not needed anymore if there is no stock entry associated with it and all the configuration fields
     * are null.
     */
    private function cleanupUnusedProductStockLocationMappings(Criteria $additionalCriteria, Context $context): void
    {
        $additionalCriteria->addFilter(new EqualsFilter('stockId', null));
        $additionalCriteria->addFilter(
            new MultiFilter(
                'OR',
                [
                    new EqualsFilter('productStockLocationConfiguration.id', null),
                    new MultiFilter(
                        'AND',
                        [
                            new EqualsFilter('productStockLocationConfiguration.reorderPoint', 0),
                            new EqualsFilter('productStockLocationConfiguration.targetMaximumQuantity', null),
                        ],
                    ),
                ],
            ),
        );

        $context->scope(
            Context::SYSTEM_SCOPE,
            function($systemScopeContext) use ($additionalCriteria): void {
                $this->entityManager->deleteByCriteria(
                    ProductStockLocationMappingDefinition::class,
                    $additionalCriteria,
                    $systemScopeContext,
                );
            },
        );
    }

    /**
     * @return string[]
     */
    private function getNewlyCreatedOrUpdatedEntityIds(EntityWrittenEvent $entityWrittenEvent): array
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return [];
        }

        $ids = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            // We want to handle both new and updated stock entries
            $ids[] = $writeResult->getPrimaryKey();
        }

        return $ids;
    }
}
