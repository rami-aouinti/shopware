<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use LieferzeitenAdmin\Service\LieferzeitenTaskService;
use LieferzeitenAdmin\Service\Notification\NotificationEventService;
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
        $service = new LieferzeitenTaskService($taskRepository, $notificationService);

        $context = new Context(new AdminApiSource('user-123'));

        static::assertSame('user-123', $service->resolveActor($context));
    }

    public function testAssignTaskMovesTaskToInProgress(): void
    {
        $context = Context::createDefaultContext();
        $taskRepository = $this->createMock(EntityRepository::class);
        $taskRepository->expects($this->once())
            ->method('update')
            ->with($this->callback(static function (array $payload): bool {
                return ($payload[0]['status'] ?? null) === LieferzeitenTaskService::STATUS_IN_PROGRESS
                    && ($payload[0]['assignee'] ?? null) === 'agent@example.test';
            }), $context);

        $notificationService = $this->createMock(NotificationEventService::class);
        $service = new LieferzeitenTaskService($taskRepository, $notificationService);

        $service->assignTask('task-1', 'agent@example.test', $context);
    }

    public function testCloseTaskDispatchesNotificationForAdditionalDeliveryRequest(): void
    {
        $context = Context::createDefaultContext();
        $taskId = 'task-123';

        $taskEntity = new class extends Entity {
            protected string $id = 'task-123';
            public function __construct()
            {
                $this->assign([
                    'payload' => [
                        'taskType' => 'additional-delivery-request',
                        'externalOrderId' => 'EXT-100',
                        'sourceSystem' => 'shopware',
                    ],
                    'initiator' => 'buyer@example.test',
                ]);
            }
        };

        $searchResult = new EntitySearchResult('lieferzeiten_task', 1, new EntityCollection([$taskEntity]), null, new Criteria([$taskId]), $context);

        $taskRepository = $this->createMock(EntityRepository::class);
        $taskRepository->expects($this->once())->method('search')->willReturn($searchResult);
        $taskRepository->expects($this->once())
            ->method('update')
            ->with($this->callback(static fn (array $payload): bool => ($payload[0]['status'] ?? null) === LieferzeitenTaskService::STATUS_DONE), $context);

        $notificationService = $this->createMock(NotificationEventService::class);
        $notificationService->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->stringStartsWith('task-close:'),
                NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUEST_CLOSED,
                'email',
                $this->callback(static fn (array $payload): bool => ($payload['taskId'] ?? null) === $taskId),
                $context,
                'EXT-100',
                'shopware',
            );

        $service = new LieferzeitenTaskService($taskRepository, $notificationService);

        $service->closeTask($taskId, $context);
    }
}
