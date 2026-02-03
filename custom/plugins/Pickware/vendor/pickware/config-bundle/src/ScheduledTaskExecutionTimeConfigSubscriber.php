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

use DateTime;
use DateTimeZone;
use InvalidArgumentException;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Use this subscriber to allow setting the execution time (not datetime!) of a scheduled task via plugin configuration.
 * @deprecated Use AbstractConfigurableScheduledTask instead.
 */
class ScheduledTaskExecutionTimeConfigSubscriber implements EventSubscriberInterface
{
    private ScheduledTaskExecutionTimeUpdater $scheduledTaskExecutionTimeUpdater;
    private string $configurationKey;
    private string $scheduledTaskClassName;

    public function __construct(
        ScheduledTaskExecutionTimeUpdater $scheduledTaskExecutionTimeUpdater,
        string $configurationKey,
        string $scheduledTaskClassName,
    ) {
        $this->scheduledTaskExecutionTimeUpdater = $scheduledTaskExecutionTimeUpdater;
        $this->configurationKey = $configurationKey;
        $this->scheduledTaskClassName = $scheduledTaskClassName;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigChangedEvent::class => 'onSystemConfigChange',
        ];
    }

    /**
     * Listens to configuration changes and uses the time (not datetime!) input of a config field to update the next
     * execution time of the scheduled task.
     */
    public function onSystemConfigChange(SystemConfigChangedEvent $event): void
    {
        // Since scheduled tasks are unique, we only support a single global configuration (i.e. not for a specific
        // sales channel) for scheduled task configuration. Ignore configurations that have a sales channel id.
        if ($event->getSalesChannelId() !== null || $event->getKey() !== $this->configurationKey) {
            return;
        }

        $nextExecutionTimeInUTC = DateTime::createFromFormat('H:i:s', $event->getValue(), new DateTimeZone('UTC'));
        if (!$nextExecutionTimeInUTC) {
            throw new InvalidArgumentException(sprintf(
                'Given value for config key "%s" could not be formatted into a DateTime object. Value: %s.',
                $event->getKey(),
                $event->getValue(),
            ));
        }
        $this->scheduledTaskExecutionTimeUpdater->updateExecutionTimeOfScheduledTask(
            $this->scheduledTaskClassName,
            $nextExecutionTimeInUTC,
            new Context(new SystemSource()),
        );
    }
}
