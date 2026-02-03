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
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Use this subscriber to allow setting the execution time or the run interval of a scheduled task via plugin
 * configuration. It listens to configuration changes and updates the scheduled tasks accordingly.
 *
 * The scheduled task must extend AbstractPickwareScheduledTask and define at least one system config key.
 * The scheduled tasks must also be tagged with "pickware_config_bundle.scheduled_task_config_provider" which should be
 * done automatically via autoconfiguration.
 */
class ScheduledTaskConfigSubscriber implements EventSubscriberInterface
{
    /**
     * @var array<class-string<AbstractConfigurableScheduledTask>, AbstractConfigurableScheduledTask> $scheduledTasksByClassName
     */
    private readonly array $scheduledTasksByClassName;

    /**
     * @param iterable<AbstractConfigurableScheduledTask> $pickwareScheduledTasks
     */
    public function __construct(
        iterable $pickwareScheduledTasks,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly EntityManager $entityManager,
        private readonly SystemConfigService $systemConfigService,
    ) {
        $scheduledTasksByClassName = [];
        // validate that all scheduled tasks are instances of PickwareScheduledTask
        foreach ($pickwareScheduledTasks as $pickwareScheduledTask) {
            if (!$pickwareScheduledTask instanceof AbstractConfigurableScheduledTask) {
                throw new InvalidArgumentException(sprintf(
                    'Service "%s" tagged with "%s" must be an instance of "%s".',
                    $pickwareScheduledTask::class,
                    'pickware_config_bundle.configurable_scheduled_task',
                    AbstractConfigurableScheduledTask::class,
                ));
            }
            $scheduledTasksByClassName[$pickwareScheduledTask::class] = $pickwareScheduledTask;
        }
        $this->scheduledTasksByClassName = $scheduledTasksByClassName;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigChangedEvent::class => 'onSystemConfigChange',
            ScheduledTaskDefinition::ENTITY_NAME . '.written' => 'onScheduledTaskWritten',
        ];
    }

    /**
     * Listens to configuration changes and uses the input of a config field to update the next
     * execution time or execution interval of the scheduled task.
     */
    public function onSystemConfigChange(SystemConfigChangedEvent $event): void
    {
        // Since scheduled tasks are unique, we only support configuration without a sales channel id.
        if ($event->getSalesChannelId() !== null) {
            return;
        }
        if ($event->getValue() === null) {
            return;
        }

        /** @var AbstractConfigurableScheduledTask|null $scheduledTask */
        $scheduledTask = array_values(array_filter(
            $this->scheduledTasksByClassName,
            fn(AbstractConfigurableScheduledTask $pickwareScheduledTask): bool => in_array(
                $event->getKey(),
                [
                    $pickwareScheduledTask::getExecutionTimeConfigKey(),
                    $pickwareScheduledTask::getExecutionIntervalInSecondsConfigKey(),
                ],
                true,
            ),
        ))[0] ?? null;

        if (!$scheduledTask) {
            return;
        }

        $runInterval = $scheduledTask::getExecutionIntervalInSecondsConfigKey() === $event->getKey() ? (int) $event->getValue() : $this->getExecutionIntervalInSecondsForTask($scheduledTask);
        $executionTime = $scheduledTask::getExecutionTimeConfigKey() === $event->getKey() ? $event->getValue() : $this->systemConfigService->get($scheduledTask::getExecutionTimeConfigKey());
        if ($executionTime === null) {
            return;
        }

        $nextExecutionTime = $this->getNextExecutionTime(
            $executionTime,
            $runInterval,
        );

        $this->connection->update(
            ScheduledTaskDefinition::ENTITY_NAME,
            [
                'run_interval' => $runInterval,
                'next_execution_time' => $nextExecutionTime,
            ],
            ['scheduled_task_class' => $scheduledTask::class],
            [
                'next_execution_time' => 'datetime',
                'run_interval' => 'integer',
            ],
        );
    }

    public function onScheduledTaskWritten(EntityWrittenEvent $event): void
    {
        $updatePayloads = [];
        foreach ($event->getWriteResults() as $writeResult) {
            if (!in_array($writeResult->getOperation(), [EntityWriteResult::OPERATION_INSERT, EntityWriteResult::OPERATION_UPDATE], true)) {
                continue;
            }
            if ($writeResult->hasPayload('scheduledTaskClass')) {
                $scheduledTaskClass = $writeResult->getProperty('scheduledTaskClass');
            } else {
                /** @var ?ScheduledTaskEntity $scheduledTask */
                $scheduledTask = $this->entityManager->findByPrimaryKey(
                    ScheduledTaskDefinition::class,
                    $writeResult->getPrimaryKey(),
                    $event->getContext(),
                );
                $scheduledTaskClass = $scheduledTask?->getScheduledTaskClass();
            }
            $scheduledTask = $this->scheduledTasksByClassName[$scheduledTaskClass] ?? null;
            if ($scheduledTask === null || $scheduledTask::getExecutionTimeConfigKey() === null) {
                continue;
            }

            $executionTime = $this->systemConfigService->get($scheduledTask::getExecutionTimeConfigKey());
            if ($executionTime === null) {
                continue;
            }

            $runIntervalInSeconds = $this->getExecutionIntervalInSecondsForTask($scheduledTask);
            $expectedNextExecutionTime = $this->getNextExecutionTime($executionTime, $runIntervalInSeconds);

            // Make sure the next execution time aligns with the expected next execution time when Shopware
            // reschedules the task. This is necessary because the current next execution time might be in the past,
            // which causes the task to be executed immediately.
            if (
                $writeResult->getOperation() === EntityWriteResult::OPERATION_UPDATE
                && $writeResult->hasPayload('nextExecutionTime')
            ) {
                /** @var DateTimeInterface $currentNextExecutionTime */
                $currentNextExecutionTime = $writeResult->getProperty('nextExecutionTime');
                if (
                    ($currentNextExecutionTime <=> $expectedNextExecutionTime) === 0
                    || $writeResult->getProperty('status') !== ScheduledTaskDefinition::STATUS_SCHEDULED
                ) {
                    continue;
                }
            }

            $updatePayloads[] = [
                'id' => $writeResult->getPrimaryKey(),
                'nextExecutionTime' => $expectedNextExecutionTime,
            ];
        }

        $this->entityManager->update(
            ScheduledTaskDefinition::class,
            $updatePayloads,
            $event->getContext(),
        );
    }

    private function getNextExecutionTime(
        string $executionTime,
        int $runIntervalInSeconds,
    ): DateTime {
        $now = $this->clock->now();
        $nextExecutionTime = DateTime::createFromInterface($now)->setTimezone(new DateTimeZone('UTC'));
        $nextExecutionTime->modify($executionTime);
        if ($nextExecutionTime > $now) {
            // Ensure that the execution time is in the past so that we can add the required number of intervals below.
            $nextExecutionTime->sub(new DateInterval('P1D'));
        }

        $intervalsToAdd = (int) floor(($now->getTimestamp() - $nextExecutionTime->getTimestamp()) / $runIntervalInSeconds) + 1;
        $nextExecutionTime->add(new DateInterval(sprintf('PT%dS', $intervalsToAdd * $runIntervalInSeconds)));

        return $nextExecutionTime;
    }

    private function getExecutionIntervalInSecondsForTask(AbstractConfigurableScheduledTask $scheduledTask): int
    {
        $key = $scheduledTask::getExecutionIntervalInSecondsConfigKey();
        if ($key === null) {
            return $scheduledTask::getDefaultInterval();
        }

        return (int) ($this->systemConfigService->get($key) ?? $scheduledTask::getDefaultInterval());
    }
}
