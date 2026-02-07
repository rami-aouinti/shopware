<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\PickwareAccount\ScheduledTask;

use Pickware\LicenseBundle\PickwareAccount\PickwareAccountService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: RefreshPickwareLicenseLeaseTask::class)]
class RefreshPickwareLicenseLeaseTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private readonly PickwareAccountService $pickwareAccountService,
        LoggerInterface $logger,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $context = Context::createDefaultContext();
        if (!$this->pickwareAccountService->isPickwareAccountConnected($context)) {
            return;
        }

        $this->pickwareAccountService->refreshPickwareLicenseLease($context);
    }
}
