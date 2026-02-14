<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use LieferzeitenAdmin\Service\Notification\NotificationEventService;
use LieferzeitenAdmin\Service\Notification\SalesChannelResolver;
use LieferzeitenAdmin\Service\Notification\NotificationTriggerCatalog;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
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
        private readonly SalesChannelResolver $salesChannelResolver,
    ) {
    }

    /** @param array<string,mixed> $payload */
    public function createTask(array $payload, Context $context, ?string $initiator = null, ?string $assignee = null, ?\DateTimeInterface $dueDate = null): string
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

        $contextInitiator = $this->resolveActorUserId($context);
        $payloadInitiatorUserId = isset($payload['initiatorUserId']) && is_string($payload['initiatorUserId']) && Uuid::isValid($payload['initiatorUserId'])
            ? $payload['initiatorUserId']
            : null;
        $resolvedInitiatorUserId = $contextInitiator ?? $payloadInitiatorUserId;

        $payloadInitiatorDisplay = isset($payload['initiatorDisplay']) && is_string($payload['initiatorDisplay'])
            ? trim($payload['initiatorDisplay'])
            : '';
        $resolvedInitiatorDisplay = $payloadInitiatorDisplay !== ''
            ? $payloadInitiatorDisplay
            : ($initiator !== null ? trim($initiator) : '');

        $payload['initiatorUserId'] = $resolvedInitiatorUserId;
        $payload['initiatorDisplay'] = $resolvedInitiatorDisplay !== '' ? $resolvedInitiatorDisplay : null;
        $payload['salesChannelId'] = $this->salesChannelResolver->resolve(
            isset($payload['sourceSystem']) ? (string) $payload['sourceSystem'] : null,
            isset($payload['externalOrderId']) ? (string) $payload['externalOrderId'] : null,
            isset($payload['positionNumber']) ? (string) $payload['positionNumber'] : null,
            isset($payload['salesChannelId']) ? (string) $payload['salesChannelId'] : null,
        );

        $id = Uuid::randomHex();
        $this->taskRepository->create([[
            'id' => $id,
            'status' => self::STATUS_OPEN,
            'assignee' => $assignee,
            'dueDate' => $dueDate,
            'initiator' => $resolvedInitiatorDisplay !== '' ? $resolvedInitiatorDisplay : $resolvedInitiatorUserId,
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

    public function closeLatestOpenTaskByPositionAndTrigger(string $positionId, string $triggerKey, Context $context): void
    {
        $task = $this->findLatestOpenTaskByPositionAndTrigger($positionId, $triggerKey, $context);
        if ($task !== null) {
            $this->closeTask((string) $task->getUniqueIdentifier(), $context);
        }
    }

    /**
     * Closes only when the current actor matches the task assignee.
     *
     * Comparison rule:
     * - UUID assignee: strict equality against AdminApiSource userId.
     * - free-form assignee string: normalized (trim + lowercase) equality against the resolved actor identifier.
     */
    public function closeLatestOpenTaskByPositionAndTriggerIfAssigneeMatches(string $positionId, string $triggerKey, Context $context): void
    {
        $task = $this->findLatestOpenTaskByPositionAndTrigger($positionId, $triggerKey, $context);
        if ($task === null) {
            return;
        }

        $assignee = $task->get('assignee');
        if (!is_string($assignee) || trim($assignee) === '') {
            return;
        }

        if (!$this->actorMatchesAssignee($assignee, $context)) {
            return;
        }

        $this->closeTask((string) $task->getUniqueIdentifier(), $context);
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

        $initiatorDisplay = isset($payload['initiatorDisplay']) && is_string($payload['initiatorDisplay'])
            ? trim($payload['initiatorDisplay'])
            : '';
        $initiator = $initiatorDisplay !== ''
            ? $initiatorDisplay
            : (string) (($task->get('initiator') ?? $payload['initiator'] ?? ''));

        if ($initiator === '') {
            return;
        }

        $initiatorUserId = isset($payload['initiatorUserId']) && is_string($payload['initiatorUserId']) && Uuid::isValid($payload['initiatorUserId'])
            ? $payload['initiatorUserId']
            : (Uuid::isValid($initiator) ? $initiator : null);
        $notificationRecipient = $this->resolveNotificationRecipient($payload, $initiator, $initiatorUserId);
        $salesChannelId = $this->salesChannelResolver->resolve(
            isset($payload['sourceSystem']) ? (string) $payload['sourceSystem'] : null,
            isset($payload['externalOrderId']) ? (string) $payload['externalOrderId'] : null,
            isset($payload['positionNumber']) ? (string) $payload['positionNumber'] : null,
            isset($payload['salesChannelId']) ? (string) $payload['salesChannelId'] : null,
        );

        if ($toStatus === self::STATUS_DONE || $toStatus === self::STATUS_CANCELLED) {
            $eventKey = sprintf('task-close:%s:%s', NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUEST_CLOSED, $taskId);
            $this->notificationEventService->dispatch(
                $eventKey,
                NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUEST_CLOSED,
                'email',
                [
                    'taskId' => $taskId,
                    'initiator' => $initiator,
                    'initiatorUserId' => $initiatorUserId,
                    'recipientUserId' => $notificationRecipient,
                    'manual' => $manual,
                    'status' => $toStatus,
                    'closedAt' => $closedAt?->format(DATE_ATOM),
                    'customerEmail' => $payload['customerEmail'] ?? null,
                    'payload' => $payload,
                ],
                $context,
                isset($payload['externalOrderId']) ? (string) $payload['externalOrderId'] : null,
                isset($payload['sourceSystem']) ? (string) $payload['sourceSystem'] : null,
                null,
                true,
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
                    'initiatorUserId' => $initiatorUserId,
                    'recipientUserId' => $notificationRecipient,
                    'reopenedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                    'customerEmail' => $payload['customerEmail'] ?? null,
                    'payload' => $payload,
                ],
                $context,
                isset($payload['externalOrderId']) ? (string) $payload['externalOrderId'] : null,
                isset($payload['sourceSystem']) ? (string) $payload['sourceSystem'] : null,
                $salesChannelId,
            );
        }
    }


    private function resolveActorUserId(Context $context): ?string
    {
        $source = $context->getSource();
        if ($source instanceof AdminApiSource) {
            $userId = $source->getUserId();
            if (is_string($userId) && Uuid::isValid($userId)) {
                return $userId;
            }
        }

        return null;
    }

    private function resolveNotificationRecipient(array $payload, string $initiator, ?string $initiatorUserId): ?string
    {
        if ($initiatorUserId !== null) {
            return $initiatorUserId;
        }

        if (isset($payload['initiator']) && is_string($payload['initiator']) && Uuid::isValid($payload['initiator'])) {
            return $payload['initiator'];
        }

        if (Uuid::isValid($initiator)) {
            return $initiator;
        }

        return null;
    }

    private function actorMatchesAssignee(string $assignee, Context $context): bool
    {
        $normalizedAssignee = trim($assignee);
        if ($normalizedAssignee === '') {
            return false;
        }

        $actorUserId = $this->resolveActorUserId($context);
        if (Uuid::isValid($normalizedAssignee)) {
            return $actorUserId !== null && $actorUserId === $normalizedAssignee;
        }

        $resolvedActorIdentifier = trim($this->resolveActor($context));
        if ($resolvedActorIdentifier === '') {
            return false;
        }

        return mb_strtolower($normalizedAssignee) === mb_strtolower($resolvedActorIdentifier);
    }

    private function findLatestOpenTaskByPositionAndTrigger(string $positionId, string $triggerKey, Context $context): mixed
    {
        $queries = [
            ['positionId', 'triggerKey'],
            ['payload.positionId', 'payload.triggerKey'],
            ['payload.positionId', 'payload.trigger'],
        ];

        $latestTask = null;
        $latestCreatedAt = null;

        foreach ($queries as [$positionField, $triggerField]) {
            $criteria = new Criteria();
            $criteria->setLimit(1);
            $criteria->addFilter(new EqualsFilter($positionField, $positionId));
            $criteria->addFilter(new EqualsFilter($triggerField, $triggerKey));
            $criteria->addFilter(new EqualsAnyFilter('status', [
                self::STATUS_OPEN,
                self::STATUS_IN_PROGRESS,
                self::STATUS_REOPENED,
            ]));
            $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

            $task = $this->taskRepository->search($criteria, $context)->first();
            if ($task === null) {
                continue;
            }

            $taskCreatedAt = $task->getCreatedAt();
            if ($latestTask === null || ($taskCreatedAt !== null && $latestCreatedAt !== null && $taskCreatedAt > $latestCreatedAt)) {
                $latestTask = $task;
                $latestCreatedAt = $taskCreatedAt;
            }
        }

        if ($latestTask === null && $triggerKey !== 'additional-delivery-request') {
            return $this->findLatestOpenTaskByPositionAndTrigger($positionId, 'additional-delivery-request', $context);
        }

        return $latestTask;
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
