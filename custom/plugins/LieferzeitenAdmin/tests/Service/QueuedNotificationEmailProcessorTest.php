<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use Doctrine\DBAL\Connection;
use LieferzeitenAdmin\Entity\NotificationEventEntity;
use LieferzeitenAdmin\Service\Audit\AuditLogService;
use LieferzeitenAdmin\Service\Notification\NotificationTemplateResolver;
use LieferzeitenAdmin\Service\Notification\QueuedNotificationEmailProcessor;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class QueuedNotificationEmailProcessorTest extends TestCase
{
    public function testRunSendsQueuedEmailWithDefaultTriggerTemplateAndMarksSent(): void
    {
        $event = new NotificationEventEntity();
        $event->setId(Uuid::randomHex());
        $event->setEventKey('delivery-date-updated:EXT-200:email');
        $event->setTriggerKey('livraison.date.modifiee');
        $event->setChannel('email');
        $event->setExternalOrderId('EXT-200');
        $event->setSourceSystem('shopware');
        $event->setStatus('queued');
        $event->setPayload([
            'customerEmail' => 'buyer@example.com',
            'deliveryDate' => '2026-02-22 10:00:00',
            'previousDeliveryDate' => '2026-02-21 10:00:00',
        ]);

        $notificationEventRepository = $this->createMock(EntityRepository::class);
        $notificationEventRepository->expects($this->once())
            ->method('search')
            ->with($this->isInstanceOf(Criteria::class), $this->isInstanceOf(Context::class))
            ->willReturn(new EntitySearchResult(
                'lieferzeiten_notification_event',
                1,
                new EntityCollection([$event]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ));

        $templateRepository = $this->createMock(EntityRepository::class);
        $templateRepository->method('search')->willReturn(new EntitySearchResult(
            'lieferzeiten_notification_template',
            0,
            new EntityCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $templateResolver = new NotificationTemplateResolver($templateRepository);

        $mailService = $this->createMock(AbstractMailService::class);
        $mailService->expects($this->once())
            ->method('send')
            ->with(
                $this->callback(static function (array $data): bool {
                    return ($data['subject'] ?? null) === '[shopware] Date de livraison modifiée - EXT-200'
                        && ($data['recipients']['buyer@example.com'] ?? null) === 'buyer@example.com';
                }),
                $this->isInstanceOf(Context::class),
                $this->isType('array')
            );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params = []): int {
                if (str_contains($sql, 'WHERE `id` = :id AND `status` = :queued')) {
                    return 1;
                }

                if (str_contains($sql, 'SET `status` = :status, `updated_at` = :updatedAt')) {
                    return 1;
                }

                return 0;
            });

        $processor = new QueuedNotificationEmailProcessor(
            $notificationEventRepository,
            $templateResolver,
            $mailService,
            $connection,
            $this->createMock(LoggerInterface::class),
            $this->createMock(AuditLogService::class),
        );

        $processor->run(Context::createDefaultContext(), 10);
    }
}
