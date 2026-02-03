<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ConfigBundle;

use DateInterval;
use DateTime;
use DateTimeZone;
use Pickware\DalBundle\EntityManager;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;

/**
 * @deprecated Use the AbstractConfigurableScheduledTask instead
 */
class ScheduledTaskExecutionTimeUpdater
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function updateExecutionTimeOfScheduledTask(
        string $scheduledTaskClassName,
        DateTime $nextExecutionTimeInUTC,
        Context $context,
    ): void {
        // The existence of the scheduled task entity is required.
        $scheduledTask = $this->entityManager->getOneBy(
            ScheduledTaskDefinition::class,
            ['scheduledTaskClass' => $scheduledTaskClassName],
            $context,
        );

        $timeNowInUTC = new DateTime('now', new DateTimeZone('UTC'));
        if ($timeNowInUTC > $nextExecutionTimeInUTC) {
            // New reorder notification time is older than now. Set next execution to tomorrow on that time.
            $nextExecutionTimeInUTC->add(new DateInterval('P1D'));
        }

        // Shopware expects the "nextExecutionTime" of a scheduled task to be in UTC
        $this->entityManager->update(
            ScheduledTaskDefinition::class,
            [
                [
                    'id' => $scheduledTask->getId(),
                    'nextExecutionTime' => $nextExecutionTimeInUTC,
                ],
            ],
            $context,
        );
    }
}
