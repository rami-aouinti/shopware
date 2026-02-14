<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use LieferzeitenAdmin\Service\Audit\AuditLogService;
use LieferzeitenAdmin\Service\Notification\NotificationEventService;
use LieferzeitenAdmin\Service\Notification\NotificationToggleResolver;
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
            ->with('date_livraison.attribuee', 'email', $this->isInstanceOf(Context::class), 'sales-channel-1')
            ->willReturn(true);

        $repository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult('lieferzeiten_notification_event', 0, new EntityCollection(), null, new Criteria(), Context::createDefaultContext()));

        $repository->expects($this->once())
            ->method('create')
            ->with($this->callback(static function (array $payload): bool {
                $event = $payload[0] ?? [];

                return (($event['payload']['salesChannelId'] ?? null) === 'sales-channel-1');
            }));

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
            'shopware',
            'sales-channel-1',
        ));
    }
}
