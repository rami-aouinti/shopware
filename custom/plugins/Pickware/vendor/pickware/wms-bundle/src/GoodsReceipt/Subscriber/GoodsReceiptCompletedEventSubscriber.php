<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\GoodsReceipt\Subscriber;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\GoodsReceipt\Event\GoodsReceiptCompletedEvent;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptStateMachine;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessCollection;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessDefinition;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessEntity;
use Pickware\PickwareWms\StockingProcess\StockingProcessCleanupService;
use Pickware\PickwareWms\StockingProcess\StockingProcessService;
use Pickware\PickwareWms\StockingProcess\StockingProcessStateMachine;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GoodsReceiptCompletedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly StockingProcessService $stockingProcessService,
        private readonly StockingProcessCleanupService $stockingProcessCleanupService,
        private readonly EntityManager $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            GoodsReceiptCompletedEvent::class => 'onGoodsReceiptCompleted',
        ];
    }

    public function onGoodsReceiptCompleted(GoodsReceiptCompletedEvent $event): void
    {
        /** @var StockingProcessCollection $stockingProcesses */
        $stockingProcesses = $this->entityManager->findBy(
            StockingProcessDefinition::class,
            [
                'sources.goodsReceiptId' => $event->getGoodsReceiptId(),
            ],
            $event->getContext(),
            [
                'sources.goodsReceipt.state',
                'state',
            ],
        );

        // If the goods receipt is not assigned to a stocking process, we do not need to do anything.
        if ($stockingProcesses->count() === 0) {
            return;
        }

        foreach ($stockingProcesses as $stockingProcess) {
            if (!$this->canStockingProcessBeCompleted($stockingProcess)) {
                continue;
            }

            $this->stockingProcessService->completeStockingProcess(
                $stockingProcess->getId(),
                $event->getContext(),
            );

            $this->stockingProcessCleanupService->deleteStockingProcess(
                $stockingProcess->getId(),
                $event->getContext(),
            );
        }
    }

    private function canStockingProcessBeCompleted(StockingProcessEntity $stockingProcess): bool
    {
        foreach ($stockingProcess->getSources() as $source) {
            if ($source->getGoodsReceipt()?->getState()->getTechnicalName() !== GoodsReceiptStateMachine::STATE_COMPLETED) {
                return false;
            }
        }

        return $stockingProcess->getState()->getTechnicalName() !== StockingProcessStateMachine::STATE_COMPLETED;
    }
}
