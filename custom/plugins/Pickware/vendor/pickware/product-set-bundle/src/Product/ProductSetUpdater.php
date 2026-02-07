<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Product;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Exception;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityWriteValidationEventType;
use function Pickware\DebugBundle\Profiling\trace;
use Pickware\DebugBundle\Profiling\TracingTag;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Cache\CacheInvalidationService;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailLoggingService;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailUriProvider;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductDefinition;
use Pickware\PickwareErpStarter\Stock\ProductAvailableStockUpdatedEvent;
use Pickware\PickwareErpStarter\Stock\ProductAvailableStockUpdater;
use Pickware\ProductSetBundle\Model\ProductSetConfigurationDefinition;
use Pickware\ProductSetBundle\Model\ProductSetDefinition;
use Pickware\ProductSetBundle\PaperTrail\ProductSetPaperTrailUri;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSetUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ProductAvailableStockUpdater $productAvailableStockUpdater,
        // The CacheInvalidationService might not exist in ERP starter prior to v4.24.0
        private readonly ?CacheInvalidationService $cacheInvalidationService = null,
        // Not available in older ERP versions
        private readonly ?PaperTrailUriProvider $paperTrailUriProvider = null,
        // Not available in older ERP versions
        private readonly ?PaperTrailLoggingService $paperTrailLoggingService = null,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ProductAvailableStockUpdatedEvent::class => 'availableStockUpdated',
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'productWritten',
            ProductSetDefinition::ENTITY_WRITTEN_EVENT => 'productSetWritten',
            ProductSetDefinition::ENTITY_DELETED_EVENT => 'productSetDeleted',
            ProductSetConfigurationDefinition::ENTITY_WRITTEN_EVENT => 'productSetConfigurationWritten',
            ProductSetConfigurationDefinition::ENTITY_DELETED_EVENT => 'productSetConfigurationDeleted',
            PickwareProductDefinition::ENTITY_WRITTEN_EVENT => 'pickwareProductWritten',
            SystemConfigChangedEvent::class => 'systemConfigChanged',
            EntityWriteValidationEventType::Pre->getEventName(PickwareProductDefinition::ENTITY_NAME) => 'triggerChangeSet',
            EntityWriteValidationEventType::Pre->getEventName(ProductSetConfigurationDefinition::ENTITY_NAME) => 'triggerChangeSet',
        ];
    }

    public function systemConfigChanged(SystemConfigChangedEvent $event): void
    {
        if ($event->getKey() !== 'core.cart.maxQuantity') {
            return;
        }

        $this->paperTrailUriProvider?->registerUri(ProductSetPaperTrailUri::withProcess('product-set-updater'));
        $this->paperTrailLoggingService?->logPaperTrailEvent(
            'Product set updater triggered',
            ['trigger' => 'system-config-change'],
        );

        $mainProductIds = $this->connection->fetchFirstColumn(
            'SELECT
                DISTINCT(LOWER(HEX(`product`.`id`)))
                FROM `product`
                INNER JOIN `pickware_product_set_product_set` `productSet`
                ON `productSet`.`product_id` = `product`.`id`;',
        );

        // Even though we would want to use the correct (event) context here, `SystemConfigChangedEvent` does not
        // provide one. Use CLI context here instead.
        $this->recalculateProductSetAvailableStock($mainProductIds, Context::createCLIContext());

        $this->paperTrailUriProvider?->reset();
    }

    public function triggerChangeSet(EntityPreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (
                ($command instanceof DeleteCommand
                    && $command->getEntityName() === ProductSetConfigurationDefinition::ENTITY_NAME)
                || ($command instanceof UpdateCommand
                    && $command->getEntityName() === PickwareProductDefinition::ENTITY_NAME)
            ) {
                $command->requestChangeSet();
            }
        }
    }

    // In case the closeout status of a sub-product of a product set changes, we need to re-evaluate the availability
    // of the affected main products.
    public function productWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $productIdsWithChangedCloseout = (new ImmutableCollection($event->getWriteResults()))
            ->filter(fn(EntityWriteResult $writeResult) => $writeResult->getOperation() === EntityWriteResult::OPERATION_UPDATE)
            ->filter(fn(EntityWriteResult $writeResult) => isset($writeResult->getPayload()['isCloseout']))
            ->map(fn(EntityWriteResult $writeResult) => $writeResult->getPrimaryKey())
            ->asArray();
        if (count($productIdsWithChangedCloseout) === 0) {
            return;
        }

        $mainProductIds = $this->connection->fetchFirstColumn(
            'SELECT
                DISTINCT(LOWER(HEX(productSet.`product_id`)))
            FROM `pickware_product_set_product_set_configuration` productSetConfiguration
            LEFT JOIN `pickware_product_set_product_set` productSet
            ON productSet.`id` = productSetConfiguration.`product_set_id`
            WHERE productSetConfiguration.`product_id` IN (:productIds)',
            ['productIds' => array_map('hex2bin', $productIdsWithChangedCloseout)],
            ['productIds' => ArrayParameterType::STRING],
        );
        if (count($mainProductIds) === 0) {
            return;
        }

        $this->updateProductSetProductAvailability($mainProductIds);
        $this->cacheInvalidationService?->invalidateProductCache($mainProductIds);
    }

    public function productSetWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $productSetIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            $operation = $writeResult->getOperation();
            if ($operation === EntityWriteResult::OPERATION_INSERT) {
                $productSetIds[] = $writeResult->getPrimaryKey();
            }
        }

        $pickwareProductIds = $this->connection->fetchFirstColumn(
            'SELECT
                DISTINCT(LOWER(HEX(productSetPickwareProduct.`id`)))
            FROM pickware_product_set_product_set productSet
            INNER JOIN `pickware_erp_pickware_product` productSetPickwareProduct
            ON productSetPickwareProduct.`product_id` = productSet.`product_id`
            AND productSetPickwareProduct.`product_version_id` = productSet.`product_version_id`
            WHERE productSet.`id` IN (:productSetIds);',
            ['productSetIds' => array_map('hex2bin', $productSetIds)],
            ['productSetIds' => ArrayParameterType::STRING],
        );

        if (count($pickwareProductIds) === 0) {
            return;
        }

        $this->paperTrailUriProvider?->registerUri(ProductSetPaperTrailUri::withProcess('product-set-updater'));
        $this->paperTrailLoggingService?->logPaperTrailEvent(
            'Product set updater triggered',
            [
                'trigger' => 'product-set-written',
                'productSetIds' => $productSetIds,
                'pickwareProductIds' => $pickwareProductIds,
            ],
        );

        $pickwareProductPayloads = array_map(fn(string $ids) => [
            'id' => $ids,
            'isStockManagementDisabled' => true,
        ], $pickwareProductIds);

        $this->entityManager->update(
            PickwareProductDefinition::class,
            $pickwareProductPayloads,
            $event->getContext(),
        );

        $this->paperTrailUriProvider?->reset();
    }

    public function productSetDeleted(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $productIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            $operation = $writeResult->getOperation();
            if ($operation === EntityWriteResult::OPERATION_DELETE && $writeResult->getChangeSet()) {
                $productIds[] = bin2hex($writeResult->getChangeSet()->getBefore('product_id'));
            }
        }

        $this->paperTrailUriProvider?->registerUri(ProductSetPaperTrailUri::withProcess('product-set-updater'));
        $this->paperTrailLoggingService?->logPaperTrailEvent(
            'Product set updater triggered',
            [
                'trigger' => 'product-set-deleted',
                'productIds' => $productIds,
            ],
        );

        $this->productAvailableStockUpdater->recalculateProductAvailableStock($productIds, $event->getContext());

        $this->paperTrailUriProvider?->reset();
    }

    public function pickwareProductWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $pickwareProductIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            $operation = $writeResult->getOperation();
            if ($operation === EntityWriteResult::OPERATION_UPDATE && $writeResult->getChangeSet()?->hasChanged('is_stock_management_disabled')) {
                $pickwareProductIds[] = $writeResult->getPrimaryKey();
            }
        }

        if (count($pickwareProductIds) === 0) {
            return;
        }

        $this->paperTrailUriProvider?->registerUri(ProductSetPaperTrailUri::withProcess('product-set-updater'));
        $this->paperTrailLoggingService?->logPaperTrailEvent(
            'Product set updater triggered',
            [
                'trigger' => 'pickware-product-written',
                'pickwareProductIds' => $pickwareProductIds,
            ],
        );

        $productIds = $this->connection->fetchFirstColumn(
            'SELECT
                DISTINCT(LOWER(HEX(productSet.`product_id`)))
            FROM pickware_erp_pickware_product pickwareProduct
            INNER JOIN `pickware_product_set_product_set_configuration` productSetConfiguration
            ON productSetConfiguration.`product_id` = pickwareProduct.`product_id`
            INNER JOIN `pickware_product_set_product_set` productSet
            ON productSet.`id` = productSetConfiguration.`product_set_id`
            WHERE pickwareProduct.`id` IN (:pickwareProductIds);',
            ['pickwareProductIds' => array_map('hex2bin', $pickwareProductIds)],
            ['pickwareProductIds' => ArrayParameterType::STRING],
        );

        $this->recalculateProductSetAvailableStock($productIds, $event->getContext());

        $this->paperTrailUriProvider?->reset();
    }

    public function productSetConfigurationWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $productSetConfigurationIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            $operation = $writeResult->getOperation();
            if ($operation === EntityWriteResult::OPERATION_INSERT || $operation === EntityWriteResult::OPERATION_UPDATE) {
                $productSetConfigurationIds[] = $writeResult->getPrimaryKey();
            }
        }
        if (count($productSetConfigurationIds) === 0) {
            return;
        }

        $this->paperTrailUriProvider?->registerUri(ProductSetPaperTrailUri::withProcess('product-set-updater'));
        $this->paperTrailLoggingService?->logPaperTrailEvent(
            'Product set updater triggered',
            [
                'trigger' => 'product-set-configuration-written',
                'productSetConfigurationIds' => $productSetConfigurationIds,
            ],
        );

        $mainProductIds = $this->connection->fetchFirstColumn(
            'SELECT
                DISTINCT(LOWER(HEX(productSet.`product_id`)))
            FROM pickware_product_set_product_set_configuration productSetConfiguration
            LEFT JOIN `pickware_product_set_product_set` productSet
            ON productSet.`id` = productSetConfiguration.`product_set_id`
            WHERE productSetConfiguration.`id` IN (:productSetConfigurationIds);',
            ['productSetConfigurationIds' => array_map('hex2bin', $productSetConfigurationIds)],
            ['productSetConfigurationIds' => ArrayParameterType::STRING],
        );

        $this->recalculateProductSetAvailableStock($mainProductIds, $event->getContext());

        $this->paperTrailUriProvider?->reset();
    }

    public function productSetConfigurationDeleted(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $productSetIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                // If the product set configuration was deleted, we need to fetch the respective product itself (because
                // the product set configuration does not exist anymore).
                $productSetIds[] = bin2hex($writeResult->getChangeSet()?->getBefore('product_set_id'));
            }
        }
        if (count($productSetIds) === 0) {
            return;
        }

        $this->paperTrailUriProvider?->registerUri(ProductSetPaperTrailUri::withProcess('product-set-updater'));
        $this->paperTrailLoggingService?->logPaperTrailEvent(
            'Product set updater triggered',
            [
                'trigger' => 'product-set-configuration-deleted',
                'productSetIds' => $productSetIds,
            ],
        );

        $mainProductIds = $this->connection->fetchFirstColumn(
            'SELECT
                DISTINCT(LOWER(HEX(productSet.`product_id`)))
            FROM `pickware_product_set_product_set` productSet
            WHERE productSet.`id` IN (:productSetIds);',
            ['productSetIds' => array_map('hex2bin', $productSetIds)],
            ['productSetIds' => ArrayParameterType::STRING],
        );

        $this->recalculateProductSetAvailableStock($mainProductIds, $event->getContext());

        $this->paperTrailUriProvider?->reset();
    }

    /**
     * The ProductAvailableStockUpdatedEvent can contain product set main and sub products. We need to update the
     * available stock of any product set _main_ product in both cases because if the...
     *   - sub product available stock changes, we need to re-calculated the main product available stock (obviously)
     *   - main product available stock changes (e.g. due to stock movements or orders) we want to ignore this change
     *     and re-calculate (overwrite) the available stock from the sub products
     */
    public function availableStockUpdated(ProductAvailableStockUpdatedEvent $event): void
    {
        $productIds = $event->getProductIds();
        $mainProductIds = $this->connection->fetchFirstColumn(
            'SELECT
                DISTINCT(LOWER(HEX(productSet.`product_id`)))
            FROM `pickware_product_set_product_set_configuration` productSetConfiguration
            LEFT JOIN `pickware_product_set_product_set` productSet
            ON productSet.`id` = productSetConfiguration.`product_set_id`
            WHERE productSetConfiguration.`product_id` IN (:productIds)
               OR productSet.`product_id` IN (:mainProductIds)',
            [
                'productIds' => array_map('hex2bin', $productIds),
                'mainProductIds' => array_map('hex2bin', $productIds),
            ],
            [
                'productIds' => ArrayParameterType::STRING,
                'mainProductIds' => ArrayParameterType::STRING,
            ],
        );

        // Fallback context can be removed as soon as pickware-erp-starter min compatible version is increased to where
        // the context is required in the event.
        $context = method_exists($event, 'getContext') ? $event->getContext() : Context::createCLIContext();
        $this->recalculateProductSetAvailableStock($mainProductIds, $context);
    }

    /**
     * @param string[] $mainProductIds
     */
    private function recalculateProductSetAvailableStock(array $mainProductIds, Context $context): void
    {
        $tags = [TracingTag::Stacktrace->getKey() => (new Exception())->getTraceAsString()];

        trace(__METHOD__, function() use ($mainProductIds, $context): void {
            $this->doRecalculateProductSetAvailableStock($mainProductIds, $context);
        }, tags: $tags);
    }

    /**
     * @param string[] $mainProductIds
     */
    private function doRecalculateProductSetAvailableStock(array $mainProductIds, Context $context): void
    {
        if (count($mainProductIds) === 0) {
            return;
        }

        $rows = RetryableTransaction::retryable($this->connection, function() use ($mainProductIds) {
            // The Update-Query sometimes deadlocks with itself, for example when adding two sub products to a main
            // product in parallel. To solve this we run the query in a retryable transaction.

            return $this->connection->executeStatement(
                '# Query: recalculateProductSetAvailableStock
                UPDATE `product`
                LEFT JOIN (
                    SELECT
                        `productSet`.`product_id` AS `mainProductId`,
                        `productSet`.`product_version_id` AS `mainProductVersionId`,
                        IF(
                            MIN(`subProductPickwareProduct`.`is_stock_management_disabled`) = 0,
                            MIN(IF(
                                `subProductPickwareProduct`.`is_stock_management_disabled` = 0,
                                `subProduct`.`available_stock` DIV `productSetConfiguration`.`quantity`,
                                NULL
                            )),
                            IFNULL(
                                `product`.`max_purchase`,
                                IFNULL(
                                    JSON_UNQUOTE(JSON_EXTRACT(`systemConfig`.`configuration_value`, "$._value")),
                                    100
                                )
                            )
                        ) AS `availableStock`
                    FROM `system_config` `systemConfig`,
                        `pickware_product_set_product_set_configuration` `productSetConfiguration`
                    INNER JOIN `pickware_product_set_product_set` `productSet`
                        ON `productSet`.`id` = `productSetConfiguration`.`product_set_id`
                    INNER JOIN `product` `product`
                        ON `product`.`id` = `productSet`.`product_id`
                        AND `product`.`version_id` = `productSet`.`product_version_id`
                    INNER JOIN `product` subProduct
                        ON `subProduct`.`id` = productSetConfiguration.`product_id`
                        AND `subProduct`.`version_id` = `productSetConfiguration`.`product_version_id`
                    INNER JOIN `pickware_erp_pickware_product` `subProductPickwareProduct`
                        ON `subProductPickwareProduct`.`product_id` = subProduct.`id`
                        AND `subProductPickwareProduct`.`product_version_id` = subProduct.`version_id`
                    WHERE
                        `productSet`.`product_id` IN (:productIds)
                        AND `productSet`.`product_version_id` = :liveVersionId
                        AND `systemConfig`.`configuration_key` = "core.cart.maxQuantity"
                    GROUP BY
                        `productSet`.`product_id`,
                        `productSet`.`product_version_id`
                ) AS `productSet`
                    ON `productSet`.`mainProductId` = `product`.`id`
                    AND `productSet`.`mainProductVersionId` = `product`.`version_id`

                SET `product`.`available_stock` = IFNULL(`productSet`.`availableStock`, 0),
                    `product`.`stock` = IFNULL(`productSet`.`availableStock`, 0)

                WHERE `product`.`id` IN (:productIds)
                AND `product`.`version_id` = :liveVersionId',
                [
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                    'productIds' => array_map('hex2bin', $mainProductIds),
                ],
                [
                    'productIds' => ArrayParameterType::STRING,
                ],
            );
        });

        // The `ProductAvailableStockUpdatedEvent` also triggers the update of the available stock (see
        // `availableStockUpdated`). So we need to dispatch the event, here, only if rows were actually updated to avoid
        // endless loops.
        if ($rows !== 0) {
            $this->eventDispatcher->dispatch(new ProductAvailableStockUpdatedEvent($mainProductIds, $context));
        }

        // We need to update the product availability again after dispatching the ProductAvailableStockUpdatedEvent,
        // because the event handler might change the availability of the product.
        $this->updateProductSetProductAvailability($mainProductIds);
    }

    /**
     * @param string[] $mainProductIds
     */
    private function updateProductSetProductAvailability(array $mainProductIds): void
    {
        RetryableTransaction::retryable(
            $this->connection,
            fn() => $this->connection->executeStatement(
                'UPDATE `product`
                LEFT JOIN (
                    SELECT
                        `productSet`.`product_id` AS `mainProductId`,
                        `productSet`.`product_version_id` AS `mainProductVersionId`,
                        IF(
                            `productSetProduct`.`is_closeout` = 0,
                            IF(
                                MAX(`subProduct`.`is_closeout`) = 1,
                                MIN(IF(
                                    `subProduct`.`is_closeout` = 1 && (`subProduct`.`available_stock` DIV `productSetConfiguration`.`quantity`) <= 0,
                                    0,
                                    1
                                )),
                                1
                            ),
                            NULL
                        ) AS `availability`
                    FROM `pickware_product_set_product_set_configuration` `productSetConfiguration`
                    INNER JOIN `pickware_product_set_product_set` `productSet`
                        ON `productSet`.`id` = `productSetConfiguration`.`product_set_id`
                    INNER JOIN `product` `productSetProduct`
                        ON `productSetProduct`.`id` = `productSet`.`product_id`
                        AND `productSetProduct`.`version_id` = `productSet`.`product_version_id`
                    INNER JOIN `product` subProduct
                        ON `subProduct`.`id` = productSetConfiguration.`product_id`
                        AND `subProduct`.`version_id` = `productSetConfiguration`.`product_version_id`
                    WHERE
                        `productSet`.`product_id` IN (:productIds)
                        AND `productSet`.`product_version_id` = :liveVersionId
                    GROUP BY
                        `productSet`.`product_id`,
                        `productSet`.`product_version_id`
                ) AS `productSet`
                    ON `productSet`.`mainProductId` = `product`.`id`
                    AND `productSet`.`mainProductVersionId` = `product`.`version_id`

                SET `product`.`available` = IFNULL(`productSet`.`availability`, `product`.`available`)
                WHERE `product`.`id` IN (:productIds)
                AND `product`.`version_id` = :liveVersionId',
                [
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                    'productIds' => array_map('hex2bin', $mainProductIds),
                ],
                ['productIds' => ArrayParameterType::STRING],
            ),
        );
    }
}
