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
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailLoggingService;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductCollection;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductDefinition;
use Pickware\PickwareErpStarter\Stock\Event\CollectExternalReservedStockEvent;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Collects and updates reserved stock from external sources (e.g., Shopify committed quantity).
 */
class ExternalReservedStockUpdater
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PaperTrailLoggingService $paperTrailLoggingService,
    ) {}

    /**
     * @param array<string> $productIds
     */
    public function recalculateExternalReservedStock(array $productIds, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($productIds, $context): void {
            /** @var PickwareProductCollection $pickwareProducts */
            $pickwareProducts = $this->entityManager->findBy(
                PickwareProductDefinition::class,
                ['productId' => $productIds],
                $context,
            );

            $event = $this->eventDispatcher->dispatch(new CollectExternalReservedStockEvent(
                $productIds,
                new CountingMap(array_combine(
                    $productIds,
                    array_fill(0, count($productIds), 0),
                )),
                $context,
            ));
            $pickwareProductUpdatePayloads = [];
            foreach ($pickwareProducts as $pickwareProduct) {
                $pickwareProductUpdatePayloads[] = [
                    'id' => $pickwareProduct->getId(),
                    'externalReservedStock' => $event->getExternalReservedStock()->get($pickwareProduct->getProductId()),
                ];
            }

            $this->entityManager->update(
                PickwareProductDefinition::class,
                $pickwareProductUpdatePayloads,
                $context,
            );
            $this->paperTrailLoggingService->logPaperTrailEvent(
                'Externally reserved stock updated for products',
                [
                    'productIds' => $productIds,
                    'externalReservedStockChangesByPickwareProductId' => $pickwareProductUpdatePayloads,
                ],
            );
            $this->eventDispatcher->dispatch(new ProductReservedStockUpdatedEvent(
                $productIds,
                $context,
            ));
        });
    }
}
