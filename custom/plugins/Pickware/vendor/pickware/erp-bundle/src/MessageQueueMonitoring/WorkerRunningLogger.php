<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\MessageQueueMonitoring;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

class WorkerRunningLogger implements EventSubscriberInterface
{
    private MessageQueueMonitoringService $messageQueueMonitoringService;

    public function __construct(MessageQueueMonitoringService $messageQueueMonitoringService)
    {
        $this->messageQueueMonitoringService = $messageQueueMonitoringService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerRunningEvent::class => 'logWorkerRunningEvent',
        ];
    }

    public function logWorkerRunningEvent(WorkerRunningEvent $workerRunningEvent): void
    {
        $this->messageQueueMonitoringService->logCLIWorkerRun();
    }
}
