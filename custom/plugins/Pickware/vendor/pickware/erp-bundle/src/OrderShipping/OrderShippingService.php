<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Picking\OrderQuantitiesToShipCalculator;
use Pickware\PickwareErpStarter\Picking\PickingRequest;
use Pickware\PickwareErpStarter\Picking\PickingStrategyStockShortageException;
use Pickware\PickwareErpStarter\Picking\ProductOrthogonalPickingStrategy;
use Pickware\PickwareErpStarter\Stock\OrderStockInitializer;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Pickware\ShopwareExtensionsBundle\OrderDelivery\OrderDeliveryCollectionExtension;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;

class OrderShippingService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly OrderParcelService $orderParcelService,
        private readonly OrderQuantitiesToShipCalculator $orderQuantitiesToShipCalculator,
        private readonly ProductOrthogonalPickingStrategy $pickingStrategy,
        private readonly Connection $connection,
    ) {}

    /**
     * @return ProductQuantityLocation[]
     */
    public function shipOrderCompletely(string $orderId, string $warehouseId, Context $context): array
    {
        $this->checkLiveVersion($context);

        /** @var PreOrderShippingValidationEvent $preOrderShippingValidationEvent */
        $preOrderShippingValidationEvent = $this->eventDispatcher->dispatch(
            new PreOrderShippingValidationEvent($context, [$orderId]),
            PreOrderShippingValidationEvent::EVENT_NAME,
        );

        if (count($preOrderShippingValidationEvent->getErrors()) > 0) {
            throw OrderShippingException::preOrderShippingValidationErrors(
                $preOrderShippingValidationEvent->getErrors(),
            );
        }

        /** @var WarehouseEntity $warehouse */
        $warehouse = $this->entityManager->getByPrimaryKey(
            WarehouseDefinition::class,
            $warehouseId,
            $context,
        );

        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(OrderDefinition::class, $orderId, $context, ['deliveries']);

        // Throw an exception after the transaction completed, because throwing inside would mark any parent transaction
        // as roll-back-only where committing is not possible anymore. This can be done as long as no data has been
        // modified up to the point where the exception would be thrown, as otherwise it would be committed.
        $exceptionToThrow = null;

        $productQuantityLocations = $this->entityManager->runInTransactionWithRetry(
            function() use ($context, $warehouse, $order, &$exceptionToThrow): array {
                $this->lockProductStocks($order->getId());

                try {
                    $primaryOrderDelivery = OrderDeliveryCollectionExtension::primaryOrderDelivery(
                        $order->getDeliveries(),
                    );
                    $stockToShip = $this->pickingStrategy->calculatePickingSolution(
                        pickingRequest: new PickingRequest(
                            productQuantities: $this->orderQuantitiesToShipCalculator->calculateProductsToShipForOrder(
                                orderId: $order->getId(),
                                context: $context,
                            ),
                            sourceStockArea: StockArea::warehouse($warehouse->getId()),
                        ),
                        context: $context,
                    );
                } catch (PickingStrategyStockShortageException $exception) {
                    $exceptionToThrow = new NotEnoughStockException(
                        $warehouse,
                        $order,
                        $exception->getStockShortages()->asArray(),
                        $exception,
                    );

                    return [];
                }

                try {
                    $this->orderParcelService->shipParcelForOrder(
                        stockToShip: $stockToShip,
                        orderId: $order->getId(),
                        trackingCodes: array_map(
                            fn(string $code) => new TrackingCode(
                                $code,
                                null,
                            ),
                            $primaryOrderDelivery?->getTrackingCodes() ?? [],
                        ),
                        context: $context,
                    );

                    return $stockToShip->asArray();
                } catch (OrderOverfulfilledException $exception) {
                    // Do not wrap the OrderOverfulfilledException as a Domain exception, as it is purely a programming
                    // error. The missing stock was calculated a few lines above, so this scenario should never occur.
                    throw $exception;
                } catch (OrderParcelException $exception) {
                    throw new OrderShippingException(
                        $exception->serializeToJsonApiError(),
                        $exception,
                    );
                }
            },
        );

        if ($exceptionToThrow) {
            throw $exceptionToThrow;
        }

        return $productQuantityLocations;
    }

    public function getDocumentIdsByOrderId(array $orderIds, array $documentTypes, bool $skipDocumentsAlreadySent, Context $context): array
    {
        /** @var DocumentCollection $documents */
        $documents = $this->entityManager->findBy(
            DocumentDefinition::class,
            [
                'orderId' => $orderIds,
                'documentType.technicalName' => $documentTypes,
            ],
            $context,
        );

        $documentIdsByOrderId = [];
        /** @var DocumentEntity $document */
        foreach ($documents as $document) {
            if ($document->getSent() && $skipDocumentsAlreadySent) {
                continue;
            }
            $documentIdsByOrderId[$document->getOrderId()][] = $document->getId();
        }

        return $documentIdsByOrderId;
    }

    private function lockProductStocks(string $orderId): void
    {
        // Starting with SW 6.6, locking rows using the DAL can create unnecessarily complex queries. The lock here led
        // to timeouts when shipping an order with many line items. To circumvent this, we use an explicit, more concise
        // lock query here.
        $lockQuery = <<<SQL
                SELECT
                    LOWER(HEX(`pickware_erp_stock`.`id`))
                FROM
                    `pickware_erp_stock`
                    INNER JOIN `product` ON `pickware_erp_stock`.`product_id` = `product`.`id`
                        AND `pickware_erp_stock`.`product_version_id` = `product`.`version_id`
                    INNER JOIN `order_line_item` ON `product`.`id` = `order_line_item`.`product_id`
                    INNER JOIN `order` ON `order_line_item`.`order_id` = `order`.`id`
                        AND `order_line_item`.`order_version_id` = `order`.`version_id`
                WHERE
                    `order`.`id` = :orderId
                    AND `order_line_item`.`type` IN (:orderLineItemTypes)
                FOR UPDATE;
            SQL;
        $this->connection->executeStatement(
            $lockQuery,
            [
                'orderId' => hex2bin($orderId),
                'orderLineItemTypes' => OrderStockInitializer::ORDER_STOCK_RELEVANT_LINE_ITEM_TYPES,
            ],
            [
                'orderLineItemTypes' => ArrayParameterType::STRING,
            ],
        );
    }

    private function checkLiveVersion(Context $context): void
    {
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            throw OrderShippingException::notInLiveVersion();
        }
    }
}
