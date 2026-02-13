<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use LieferzeitenAdmin\Service\Notification\NotificationEventService;
use LieferzeitenAdmin\Service\Notification\NotificationTriggerCatalog;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

class LieferzeitenTaskService
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';
    public const STATUS_REOPENED = 'reopened';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<int, string> */
    public const ALLOWED_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
        self::STATUS_REOPENED,
        self::STATUS_CANCELLED,
    ];

    /** @var array<string, array<int, string>> */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_OPEN => [self::STATUS_IN_PROGRESS, self::STATUS_DONE, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS => [self::STATUS_DONE, self::STATUS_CANCELLED],
        self::STATUS_DONE => [self::STATUS_REOPENED],
        self::STATUS_REOPENED => [self::STATUS_IN_PROGRESS, self::STATUS_DONE, self::STATUS_CANCELLED],
        self::STATUS_CANCELLED => [self::STATUS_REOPENED],
    ];

    public function __construct(
        private readonly EntityRepository $taskRepository,
        private readonly NotificationEventService $notificationEventService,
    ) {
    }

    /** @param array<string,mixed> $payload */
    public function createTask(array $payload, ?string $initiator, ?string $assignee, ?\DateTimeInterface $dueDate, Context $context): string
    {
        $positionId = isset($payload['positionId']) ? (string) $payload['positionId'] : null;
        $triggerKey = isset($payload['triggerKey']) ? (string) $payload['triggerKey'] : (($payload['taskType'] ?? null) ? (string) $payload['taskType'] : null);

        if ($positionId !== null && $positionId !== '' && $triggerKey !== null && $triggerKey !== '') {
            $existingTask = $this->findLatestByPositionAndTrigger($positionId, $triggerKey, $context);
            if ($existingTask !== null) {
                $taskId = (string) $existingTask->getUniqueIdentifier();
                $currentStatus = (string) ($existingTask->get('status') ?? self::STATUS_OPEN);

                if (\in_array($currentStatus, [self::STATUS_DONE, self::STATUS_CANCELLED], true)) {
                    $this->reopenTask($taskId, $context, false);
                }

                return $taskId;
            }
        }

        $id = Uuid::randomHex();
        $this->taskRepository->create([[
            'id' => $id,
            'status' => self::STATUS_OPEN,
            'assignee' => $assignee,
            'dueDate' => $dueDate,
            'initiator' => $initiator,
            'positionId' => $positionId,
            'triggerKey' => $triggerKey,
            'payload' => $payload,
        ]], $context);

        return $id;
    }

    /** @return array{total:int,data:array<int,array<string,mixed>>} */
    public function listTasks(Context $context, ?string $status = null, ?string $assignee = null, int $page = 1, int $limit = 25): array
    {
        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset(max(0, ($page - 1) * $limit));

        if ($status !== null && $status !== '') {
            $criteria->addFilter(new EqualsFilter('status', $status));
        }

        if ($assignee !== null && $assignee !== '') {
            $criteria->addFilter(new EqualsFilter('assignee', $assignee));
        }

        $result = $this->taskRepository->search($criteria, $context);
        $data = [];
        foreach ($result->getEntities() as $task) {
            $data[] = [
                'id' => $task->getUniqueIdentifier(),
                'status' => $task->get('status'),
                'assignee' => $task->get('assignee'),
                'initiator' => $task->get('initiator'),
                'positionId' => $task->get('positionId'),
                'triggerKey' => $task->get('triggerKey'),
                'dueDate' => $task->get('dueDate')?->format(DATE_ATOM),
                'closedAt' => $task->get('closedAt')?->format(DATE_ATOM),
                'payload' => $task->get('payload'),
                'createdAt' => $task->get('createdAt')?->format(DATE_ATOM),
            ];
        }

        return ['total' => $result->getTotal(), 'data' => $data];
    }

    public function assignTask(string $taskId, string $assignee, Context $context): void
    {
        $this->transitionTask($taskId, self::STATUS_IN_PROGRESS, $context, true, ['assignee' => $assignee]);
    }

    public function closeTask(string $taskId, Context $context): void
    {
        $this->transitionTask($taskId, self::STATUS_DONE, $context, true);
    }

    public function cancelTask(string $taskId, Context $context): void
    {
        $this->transitionTask($taskId, self::STATUS_CANCELLED, $context, true);
    }

    public function reopenTask(string $taskId, Context $context, bool $manual = true): void
    {
        $this->transitionTask($taskId, self::STATUS_REOPENED, $context, $manual);
    }

    public function resolveActor(Context $context): string
    {
        $source = $context->getSource();
        if ($source instanceof AdminApiSource && $source->getUserId() !== null) {
            return $source->getUserId();
        }

        return 'system';
    }

    /** @param array<string,mixed> $extraUpdates */
    private function transitionTask(string $taskId, string $toStatus, Context $context, bool $manual, array $extraUpdates = []): void
    {
        if (!\in_array($toStatus, self::ALLOWED_STATUSES, true)) {
            return;
        }

        $criteria = new Criteria([$taskId]);
        $task = $this->taskRepository->search($criteria, $context)->first();
        if ($task === null) {
            return;
        }

        $fromStatus = (string) ($task->get('status') ?? self::STATUS_OPEN);
        if (!isset(self::ALLOWED_TRANSITIONS[$fromStatus]) || !\in_array($toStatus, self::ALLOWED_TRANSITIONS[$fromStatus], true)) {
            return;
        }

        $closedAt = \in_array($toStatus, [self::STATUS_DONE, self::STATUS_CANCELLED], true) ? new \DateTimeImmutable() : null;
        $this->taskRepository->update([array_merge([
            'id' => $taskId,
            'status' => $toStatus,
            'closedAt' => $closedAt,
        ], $extraUpdates)], $context);

        $payload = (array) ($task->get('payload') ?? []);
        if (($payload['taskType'] ?? null) !== 'additional-delivery-request') {
            return;
        }

        $initiator = (string) ($task->get('initiator') ?? '');
        if ($initiator === '') {
            return;
        }

        if ($toStatus === self::STATUS_DONE || $toStatus === self::STATUS_CANCELLED) {
            $eventKey = sprintf('task-close:%s:%s', NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUEST_CLOSED, $taskId);
            $this->notificationEventService->dispatch(
                $eventKey,
                NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUEST_CLOSED,
                'email',
                [
                    'taskId' => $taskId,
                    'initiator' => $initiator,
                    'manual' => $manual,
                    'status' => $toStatus,
                    'closedAt' => $closedAt?->format(DATE_ATOM),
                    'payload' => $payload,
                ],
                $context,
                isset($payload['externalOrderId']) ? (string) $payload['externalOrderId'] : null,
                isset($payload['sourceSystem']) ? (string) $payload['sourceSystem'] : null,
            );

            return;
        }

        if ($toStatus === self::STATUS_REOPENED) {
            $eventKey = sprintf('task-reopen:%s:%s', NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUEST_REOPENED, $taskId);
            $this->notificationEventService->dispatch(
                $eventKey,
                NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUEST_REOPENED,
                'email',
                [
                    'taskId' => $taskId,
                    'initiator' => $initiator,
                    'reopenedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                    'payload' => $payload,
                ],
                $context,
                isset($payload['externalOrderId']) ? (string) $payload['externalOrderId'] : null,
                isset($payload['sourceSystem']) ? (string) $payload['sourceSystem'] : null,
            );
        }
    }

    private function findLatestByPositionAndTrigger(string $positionId, string $triggerKey, Context $context): mixed
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('positionId', $positionId));
        $criteria->addFilter(new EqualsFilter('triggerKey', $triggerKey));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        return $this->taskRepository->search($criteria, $context)->first();
    }
}
