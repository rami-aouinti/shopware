<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use LieferzeitenAdmin\Service\Audit\AuditLogService;
use LieferzeitenAdmin\Service\Notification\NotificationEventService;
use LieferzeitenAdmin\Service\Notification\NotificationToggleResolver;
use LieferzeitenAdmin\Service\Notification\NotificationTriggerCatalog;
use LieferzeitenAdmin\Service\Reliability\IntegrationReliabilityService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class NotificationEventServiceTest extends TestCase
{
    public function testDispatchQueuesEventWhenToggleEnabled(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $toggleResolver = $this->createMock(NotificationToggleResolver::class);
        $reliability = $this->createMock(IntegrationReliabilityService::class);

        $toggleResolver->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $repository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult('lieferzeiten_notification_event', 0, new EntityCollection(), null, new Criteria(), Context::createDefaultContext()));

        $repository->expects($this->once())
            ->method('create');

        $reliability->expects($this->once())
            ->method('executeWithRetry')
            ->willReturnCallback(static fn (string $domain, string $operation, callable $callback): mixed => $callback());

        $service = new NotificationEventService(
            $repository,
            $toggleResolver,
            $this->createMock(LoggerInterface::class),
            $reliability,
            $this->createMock(AuditLogService::class),
        );

        static::assertTrue($service->dispatch(
            'event-key-1',
            'date_livraison.attribuee',
            'email',
            ['externalOrderId' => 'EXT-100'],
            Context::createDefaultContext(),
            'EXT-100',
            'shopware'
        ));
    }

    public function testDispatchQueuesCriticalTriggerWhenForcedAndToggleDisabled(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $toggleResolver = $this->createMock(NotificationToggleResolver::class);
        $reliability = $this->createMock(IntegrationReliabilityService::class);

        $toggleResolver->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);

        $repository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult('lieferzeiten_notification_event', 0, new EntityCollection(), null, new Criteria(), Context::createDefaultContext()));

        $repository->expects($this->once())
            ->method('create');

        $reliability->expects($this->once())
            ->method('executeWithRetry')
            ->willReturnCallback(static fn (string $domain, string $operation, callable $callback): mixed => $callback());

        $service = new NotificationEventService(
            $repository,
            $toggleResolver,
            $this->createMock(LoggerInterface::class),
            $reliability,
            $this->createMock(AuditLogService::class),
        );

        static::assertTrue($service->dispatch(
            'event-key-critical',
            NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUEST_CLOSED,
            'email',
            ['externalOrderId' => 'EXT-200'],
            Context::createDefaultContext(),
            'EXT-200',
            'shopware',
            null,
            true,
        ));
    }

    public function testDispatchDoesNotQueueNonCriticalTriggerWhenForcedAndToggleDisabled(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $toggleResolver = $this->createMock(NotificationToggleResolver::class);
        $reliability = $this->createMock(IntegrationReliabilityService::class);

        $toggleResolver->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);

        $repository->expects($this->never())
            ->method('search');

        $repository->expects($this->never())
            ->method('create');

        $reliability->expects($this->never())
            ->method('executeWithRetry');

        $service = new NotificationEventService(
            $repository,
            $toggleResolver,
            $this->createMock(LoggerInterface::class),
            $reliability,
            $this->createMock(AuditLogService::class),
        );

        static::assertFalse($service->dispatch(
            'event-key-noncritical',
            'date_livraison.attribuee',
            'email',
            ['externalOrderId' => 'EXT-201'],
            Context::createDefaultContext(),
            'EXT-201',
            'shopware',
            null,
            true,
        ));
    }
}
