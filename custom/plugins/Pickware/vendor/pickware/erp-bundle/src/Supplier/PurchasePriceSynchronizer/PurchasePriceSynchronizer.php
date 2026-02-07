<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\PurchasePriceSynchronizer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareErpStarter\Supplier\DefaultSupplier\DefaultSupplierInitialySetEvent;
use Pickware\PickwareErpStarter\Supplier\DefaultSupplier\DefaultSupplierUpdatedEvent;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PurchasePriceSynchronizer implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Connection $connection,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ProductSupplierConfigurationDefinition::ENTITY_WRITTEN_EVENT => 'onProductSupplierConfigurationWritten',
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
            DefaultSupplierUpdatedEvent::class => 'onDefaultSupplierUpdated',
            DefaultSupplierInitialySetEvent::class => 'onDefaultSupplierInitiallySet',
        ];
    }

    public function onProductSupplierConfigurationWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        /** @var string[] $productSupplierConfigurationIds */
        $productSupplierConfigurationIds = (new ImmutableCollection($event->getWriteResults()))
            ->filter(fn(EntityWriteResult $writeResult) => $writeResult->getOperation() === EntityWriteResult::OPERATION_UPDATE)
            ->filter(fn(EntityWriteResult $writeResult) => array_key_exists('purchasePrices', $writeResult->getPayload()))
            ->map(fn(EntityWriteResult $writeResult) => $writeResult->getPrimaryKey())
            ->asArray();
        if (count($productSupplierConfigurationIds) === 0) {
            return;
        }

        $productIds = $this->entityManager->findIdsBy(
            ProductDefinition::class,
            ['pickwareErpProductSupplierConfigurations.id' => $productSupplierConfigurationIds],
            $event->getContext(),
        );
        if (count($productIds) === 0) {
            return;
        }

        $this->synchronizePurchasePricesOfProductDefaultSupplierConfigurationToProduct($productIds);
    }

    public function onProductWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        /** @var string[] $productIds */
        $productIds = (new ImmutableCollection($event->getWriteResults()))
            ->filter(fn(EntityWriteResult $writeResult) => $writeResult->getOperation() === EntityWriteResult::OPERATION_UPDATE)
            ->filter(fn(EntityWriteResult $writeResult) => array_key_exists('purchasePrices', $writeResult->getPayload()))
            ->map(fn(EntityWriteResult $writeResult) => $writeResult->getPrimaryKey())
            ->asArray();
        if (count($productIds) === 0) {
            return;
        }

        $this->synchronizePurchasePricesOfProductToProductDefaultSupplierConfiguration($productIds);
    }

    public function onDefaultSupplierUpdated(DefaultSupplierUpdatedEvent $event): void
    {
        $this->synchronizePurchasePricesOfProductDefaultSupplierConfigurationToProduct($event->getProductIds());
    }

    public function onDefaultSupplierInitiallySet(DefaultSupplierInitialySetEvent $event): void
    {
        $productIdsWithDefaultPurchasePriceConfigurations = $this->entityManager->findIdsBy(
            ProductDefinition::class,
            [
                'id' => $event->getProductIds(),
                'pickwareErpProductSupplierConfigurations.purchasePrices.net' => (
                    ProductSupplierConfigurationDefinition::DEFAULT_PURCHASE_PRICE['net']
                ),
            ],
            $event->getContext(),
        );
        $productIdsWithoutDefaultPurchasePriceConfigurations = array_diff(
            $event->getProductIds(),
            $productIdsWithDefaultPurchasePriceConfigurations,
        );

        $this->entityManager->runInTransactionWithRetry(function() use (
            $productIdsWithDefaultPurchasePriceConfigurations,
            $productIdsWithoutDefaultPurchasePriceConfigurations,
        ): void {
            $this->synchronizePurchasePricesOfProductToProductDefaultSupplierConfiguration(
                $productIdsWithDefaultPurchasePriceConfigurations,
            );
            $this->synchronizePurchasePricesOfProductDefaultSupplierConfigurationToProduct(
                $productIdsWithoutDefaultPurchasePriceConfigurations,
            );
        });
    }

    private function synchronizePurchasePricesOfProductDefaultSupplierConfigurationToProduct(array $productIds): void
    {
        $this->connection->executeStatement(
            <<<SQL
                UPDATE `product`
                INNER JOIN `pickware_erp_pickware_product` AS `pickwareProduct`
                    ON `product`.`id` = `pickwareProduct`.`product_id`
                    AND `product`.`version_id` = `pickwareProduct`.`product_version_id`
                INNER JOIN `pickware_erp_product_supplier_configuration` AS `productSupplierConfiguration`
                    ON `product`.`id` = `productSupplierConfiguration`.`product_id`
                    AND `product`.`version_id` = `productSupplierConfiguration`.`product_version_id`
                    AND `productSupplierConfiguration`.`supplier_id` = `pickwareProduct`.`default_supplier_id`
                SET `product`.`purchase_prices` = `productSupplierConfiguration`.`purchase_prices`
                WHERE `product`.`id` IN (:productIds)
                SQL,
            ['productIds' => array_map('hex2bin', $productIds)],
            ['productIds' => ArrayParameterType::STRING],
        );
    }

    private function synchronizePurchasePricesOfProductToProductDefaultSupplierConfiguration(array $productIds): void
    {
        $this->connection->executeStatement(
            <<<SQL
                UPDATE `pickware_erp_product_supplier_configuration` AS `productSupplierConfiguration`
                INNER JOIN `product`
                    ON `productSupplierConfiguration`.`product_id` = `product`.`id`
                    AND `productSupplierConfiguration`.`product_version_id` = `product`.`version_id`
                INNER JOIN `pickware_erp_pickware_product` AS `pickwareProduct`
                    ON `product`.`id` = `pickwareProduct`.`product_id`
                    AND `product`.`version_id` = `pickwareProduct`.`product_version_id`
                    AND `productSupplierConfiguration`.`supplier_id` = `pickwareProduct`.`default_supplier_id`
                LEFT JOIN `product` AS `parentProduct`
                    ON `product`.`parent_id` = `parentProduct`.`id`
                    AND `product`.`version_id` = `parentProduct`.`version_id`
                SET `productSupplierConfiguration`.`purchase_prices` = COALESCE(`product`.`purchase_prices`, `parentProduct`.`purchase_prices`, :defaultPrice)
                WHERE `productSupplierConfiguration`.`product_id` IN (:productIds)
                SQL,
            [
                'productIds' => array_map('hex2bin', $productIds),
                'defaultPrice' => Json::stringify([ProductSupplierConfigurationDefinition::DEFAULT_PURCHASE_PRICE]),
            ],
            [
                'productIds' => ArrayParameterType::STRING,
                'defaultPrice' => ParameterType::STRING,
            ],
        );
    }
}
