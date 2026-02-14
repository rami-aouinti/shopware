<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use LieferzeitenAdmin\Service\LieferzeitenTaskService;
use LieferzeitenAdmin\Service\Notification\NotificationEventService;
use LieferzeitenAdmin\Service\Notification\SalesChannelResolver;
use LieferzeitenAdmin\Service\Notification\NotificationTriggerCatalog;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class LieferzeitenTaskServiceTest extends TestCase
{
    public function testResolveActorReturnsAdminUserIdForTaskWorkflow(): void
    {
        $taskRepository = $this->createMock(EntityRepository::class);
        $notificationService = $this->createMock(NotificationEventService::class);
        $service = new LieferzeitenTaskService($taskRepository, $notificationService, $this->createMock(SalesChannelResolver::class));

        $context = new Context(new AdminApiSource('user-123'));

        static::assertSame('user-123', $service->resolveActor($context));
    }


    public function testCreateTaskPrioritizesContextUserAsInitiatorAndPersistsInitiatorFields(): void
    {
        $contextUserId = 'd2f0c2db4cfe4fd39189a8f5d13d54d1';
        $context = new Context(new AdminApiSource($contextUserId));

        $taskRepository = $this->createMock(EntityRepository::class);
        $taskRepository->expects($this->once())->method('search')->willReturn(new EntitySearchResult('lieferzeiten_task', 0, new EntityCollection(), null, new Criteria(), $context));
        $taskRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(static function (array $payload) use ($contextUserId): bool {
                $task = $payload[0] ?? [];
                $taskPayload = $task['payload'] ?? [];

                return ($task['initiator'] ?? null) === 'UI User'
                    && ($taskPayload['initiatorUserId'] ?? null) === $contextUserId
                    && ($taskPayload['initiatorDisplay'] ?? null) === 'UI User';
            }), $context);

        $notificationService = $this->createMock(NotificationEventService::class);
        $resolver = $this->createMock(SalesChannelResolver::class);
        $resolver->method('resolve')->willReturn('sales-channel-1');
        $service = new LieferzeitenTaskService($taskRepository, $notificationService, $resolver);

        $service->createTask([
            'positionId' => 'pos-1',
            'triggerKey' => 'additional-delivery-request',
            'initiatorDisplay' => 'UI User',
            'initiatorUserId' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        ], $context);
    }

    public function testAssignTaskMovesTaskToInProgress(): void
    {
        $context = Context::createDefaultContext();
        $taskEntity = $this->buildTaskEntity('task-1', LieferzeitenTaskService::STATUS_OPEN);
        $searchResult = new EntitySearchResult('lieferzeiten_task', 1, new EntityCollection([$taskEntity]), null, new Criteria(['task-1']), $context);

        $taskRepository = $this->createMock(EntityRepository::class);
        $taskRepository->expects($this->once())->method('search')->willReturn($searchResult);
        $taskRepository->expects($this->once())
            ->method('update')
            ->with($this->callback(static function (array $payload): bool {
                return ($payload[0]['status'] ?? null) === LieferzeitenTaskService::STATUS_IN_PROGRESS
                    && ($payload[0]['assignee'] ?? null) === 'agent@example.test';
            }), $context);

        $notificationService = $this->createMock(NotificationEventService::class);
        $service = new LieferzeitenTaskService($taskRepository, $notificationService, $this->createMock(SalesChannelResolver::class));

        $service->assignTask('task-1', 'agent@example.test', $context);
    }

    public function testCloseTaskDispatchesNotificationForAdditionalDeliveryRequest(): void
    {
        $context = Context::createDefaultContext();
        $taskId = 'task-123';

        $taskEntity = $this->buildTaskEntity($taskId, LieferzeitenTaskService::STATUS_IN_PROGRESS, [
            'taskType' => 'additional-delivery-request',
            'externalOrderId' => 'EXT-100',
            'sourceSystem' => 'shopware',
            'initiatorDisplay' => 'Buyer Name',
            'initiatorUserId' => 'd2f0c2db4cfe4fd39189a8f5d13d54d1',
        ], 'buyer@example.test');

        $searchResult = new EntitySearchResult('lieferzeiten_task', 1, new EntityCollection([$taskEntity]), null, new Criteria([$taskId]), $context);

        $taskRepository = $this->createMock(EntityRepository::class);
        $taskRepository->expects($this->once())->method('search')->willReturn($searchResult);
        $taskRepository->expects($this->once())
            ->method('update')
            ->with($this->callback(static fn (array $payload): bool => ($payload[0]['status'] ?? null) === LieferzeitenTaskService::STATUS_DONE), $context);

        $notificationService = $this->createMock(NotificationEventService::class);
        $resolver = $this->createMock(SalesChannelResolver::class);
        $resolver->method('resolve')->willReturn('sales-channel-1');

        $notificationService->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->stringStartsWith('task-close:'),
                NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUEST_CLOSED,
                'email',
                $this->callback(static fn (array $payload): bool => ($payload['taskId'] ?? null) === $taskId
                    && ($payload['recipientUserId'] ?? null) === 'd2f0c2db4cfe4fd39189a8f5d13d54d1'
                    && ($payload['initiator'] ?? null) === 'Buyer Name'),
                $context,
                'EXT-100',
                'shopware',
                'sales-channel-1',
            );

        $service = new LieferzeitenTaskService($taskRepository, $notificationService, $resolver);

        $service->closeTask($taskId, $context);
    }

    public function testReopenTaskFromDoneDispatchesNotification(): void
    {
        $context = Context::createDefaultContext();
        $taskId = 'task-456';

        $taskEntity = $this->buildTaskEntity($taskId, LieferzeitenTaskService::STATUS_DONE, [
            'taskType' => 'additional-delivery-request',
        ], 'buyer@example.test');

        $searchResult = new EntitySearchResult('lieferzeiten_task', 1, new EntityCollection([$taskEntity]), null, new Criteria([$taskId]), $context);

        $taskRepository = $this->createMock(EntityRepository::class);
        $taskRepository->expects($this->once())->method('search')->willReturn($searchResult);
        $taskRepository->expects($this->once())
            ->method('update')
            ->with($this->callback(static fn (array $payload): bool => ($payload[0]['status'] ?? null) === LieferzeitenTaskService::STATUS_REOPENED), $context);

        $notificationService = $this->createMock(NotificationEventService::class);
        $resolver = $this->createMock(SalesChannelResolver::class);
        $resolver->method('resolve')->willReturn('sales-channel-2');

        $notificationService->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->stringStartsWith('task-reopen:'),
                NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUEST_REOPENED,
                'email',
                $this->callback(static fn (array $payload): bool => ($payload['taskId'] ?? null) === $taskId),
                $context,
                null,
                null,
                'sales-channel-2',
            );

        $service = new LieferzeitenTaskService($taskRepository, $notificationService, $resolver);

        $service->reopenTask($taskId, $context);
    }

    public function testInvalidTransitionDoesNotUpdateTask(): void
    {
        $context = Context::createDefaultContext();
        $taskId = 'task-789';

        $taskEntity = $this->buildTaskEntity($taskId, LieferzeitenTaskService::STATUS_OPEN);
        $searchResult = new EntitySearchResult('lieferzeiten_task', 1, new EntityCollection([$taskEntity]), null, new Criteria([$taskId]), $context);

        $taskRepository = $this->createMock(EntityRepository::class);
        $taskRepository->expects($this->once())->method('search')->willReturn($searchResult);
        $taskRepository->expects($this->never())->method('update');

        $notificationService = $this->createMock(NotificationEventService::class);
        $service = new LieferzeitenTaskService($taskRepository, $notificationService, $this->createMock(SalesChannelResolver::class));

        $service->reopenTask($taskId, $context);
    }

    private function buildTaskEntity(string $id, string $status, array $payload = [], ?string $initiator = null): Entity
    {
        return new class($id, $status, $payload, $initiator) extends Entity {
            public function __construct(string $id, string $status, array $payload, ?string $initiator)
            {
                $this->assign([
                    'id' => $id,
                    'status' => $status,
                    'payload' => $payload,
                    'initiator' => $initiator,
                ]);
            }
        };
    }
}
