<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use LieferzeitenAdmin\Service\Notification\NotificationEventService;
use LieferzeitenAdmin\Service\Notification\NotificationTriggerCatalog;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class LieferzeitenTaskService
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';
    public const STATUS_REOPENED = 'reopened';

    public function __construct(
        private readonly EntityRepository $taskRepository,
        private readonly NotificationEventService $notificationEventService,
    ) {
    }

    /** @param array<string,mixed> $payload */
    public function createTask(array $payload, ?string $initiator, ?string $assignee, ?\DateTimeInterface $dueDate, Context $context): string
    {
        $id = Uuid::randomHex();
        $this->taskRepository->create([[
            'id' => $id,
            'status' => self::STATUS_OPEN,
            'assignee' => $assignee,
            'dueDate' => $dueDate,
            'initiator' => $initiator,
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
        $this->taskRepository->update([[
            'id' => $taskId,
            'assignee' => $assignee,
            'status' => self::STATUS_IN_PROGRESS,
            'closedAt' => null,
        ]], $context);
    }

    public function closeTask(string $taskId, Context $context): void
    {
        $criteria = new Criteria([$taskId]);
        $task = $this->taskRepository->search($criteria, $context)->first();
        if ($task === null) {
            return;
        }

        $closedAt = new \DateTimeImmutable();
        $this->taskRepository->update([[
            'id' => $taskId,
            'status' => self::STATUS_DONE,
            'closedAt' => $closedAt,
        ]], $context);

        $payload = (array) ($task->get('payload') ?? []);
        if (($payload['taskType'] ?? null) !== 'additional-delivery-request') {
            return;
        }

        $initiator = (string) ($task->get('initiator') ?? '');
        if ($initiator === '') {
            return;
        }

        $eventKey = sprintf('task-close:%s:%s', NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUEST_CLOSED, $taskId);
        $this->notificationEventService->dispatch(
            $eventKey,
            NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUEST_CLOSED,
            'email',
            [
                'taskId' => $taskId,
                'initiator' => $initiator,
                'closedAt' => $closedAt->format(DATE_ATOM),
                'payload' => $payload,
            ],
            $context,
            isset($payload['externalOrderId']) ? (string) $payload['externalOrderId'] : null,
            isset($payload['sourceSystem']) ? (string) $payload['sourceSystem'] : null,
        );
    }

    public function resolveActor(Context $context): string
    {
        $source = $context->getSource();
        if ($source instanceof AdminApiSource && $source->getUserId() !== null) {
            return $source->getUserId();
        }

        return 'system';
    }
}
