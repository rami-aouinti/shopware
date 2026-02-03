<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Reorder\ScheduledTask;

use Exception;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\Feature\PickwareErpProductionFeatureFlag;
use Pickware\PickwareErpStarter\Logger\PickwareErpEvents;
use Pickware\PickwareErpStarter\Reorder\ReorderNotificationService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: ReorderNotificationTask::class)]
class ReorderNotificationHandler extends ScheduledTaskHandler
{
    private ReorderNotificationService $reorderNotificationService;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
        ReorderNotificationService $reorderNotificationService,
        #[Autowire(service: 'monolog.logger.pickware_erp')]
        LoggerInterface $logger,
        private readonly FeatureFlagService $featureFlagService,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);

        $this->reorderNotificationService = $reorderNotificationService;
        $this->logger = $logger;
    }

    public function run(): void
    {
        if (!$this->featureFlagService->isActive(PickwareErpProductionFeatureFlag::NAME)) {
            return;
        }

        try {
            $this->reorderNotificationService->sendReorderNotification(Context::createDefaultContext());
            $this->logger->info(
                PickwareErpEvents::SCHEDULED_TASK_SUCCESSFUL,
                [
                    'taskName' => ReorderNotificationTask::getTaskName(),
                ],
            );
        } catch (Exception $exception) {
            // Catch exceptions to the handler and log the result so the task does not crash in the worker cycle.
            $this->logger->error(
                PickwareErpEvents::SCHEDULED_TASK_ERROR,
                [
                    'message' => $exception->getMessage(),
                    'stackTrace' => $exception->getTraceAsString(),
                ],
            );
        }
    }
}
