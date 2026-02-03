<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockingProcess;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessDefinition;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessEntity;
use Shopware\Core\Framework\Context;

class StockingProcessCleanupService
{
    private const STOCKING_PROCESS_STATES_FOR_DELETION = [
        StockingProcessStateMachine::STATE_COMPLETED,
    ];

    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function deleteStockingProcess(string $stockingProcessId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($stockingProcessId, $context): void {
            /** @var StockingProcessEntity $stockingProcess */
            $stockingProcess = $this->entityManager->getByPrimaryKey(
                StockingProcessDefinition::class,
                $stockingProcessId,
                $context,
                [
                    'state',
                    'sources.goodsReceipt.stocks',
                    'sources.stockContainer.stocks',
                ],
            );

            if ($stockingProcess->getSources()->getProductQuantities()->count() > 0) {
                throw StockingProcessException::pendingStock($stockingProcessId);
            }

            $isStockingProcessInStateForDeletion = in_array(
                $stockingProcess->getState()->getTechnicalName(),
                self::STOCKING_PROCESS_STATES_FOR_DELETION,
                true,
            );
            if (!$isStockingProcessInStateForDeletion) {
                throw StockingProcessException::invalidStockingProcessState(
                    stockingProcessId: $stockingProcessId,
                    currentStateName: $stockingProcess->getState()->getTechnicalName(),
                    expectedStateNames: self::STOCKING_PROCESS_STATES_FOR_DELETION,
                );
            }

            $this->entityManager->delete(StockingProcessDefinition::class, [$stockingProcessId], $context);
        });
    }
}
