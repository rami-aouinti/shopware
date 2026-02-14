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

class QueuedNotificationEmailProcessorTest extends TestCase
{
    public function testRunSendsMailUsingResolvedTemplate(): void
    {
        $event = new NotificationEventEntity();
        $event->setUniqueIdentifier('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $event->setEventKey('evt-1');
        $event->setTriggerKey('date_livraison.modifiee');
        $event->setChannel('email');
        $event->setExternalOrderId('EXT-44');
        $event->setSourceSystem('shopware');
        $event->setPayload([
            'customerEmail' => 'buyer@example.org',
            'deliveryDate' => '2026-02-20',
        ]);
        $event->setStatus('queued');

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult('lieferzeiten_notification_event', 1, new EntityCollection([$event]), null, new Criteria(), Context::createDefaultContext()));

        $templateResolver = $this->createMock(NotificationTemplateResolver::class);
        $templateResolver->expects($this->once())
            ->method('resolve')
            ->willReturn([
                'subject' => 'Sujet test',
                'contentHtml' => '<p>HTML test</p>',
                'contentPlain' => 'TXT test',
            ]);

        $mailService = $this->createMock(AbstractMailService::class);
        $mailService->expects($this->once())
            ->method('send')
            ->with($this->callback(static fn (array $data): bool => ($data['subject'] ?? null) === 'Sujet test'));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnOnConsecutiveCalls(1, 1);

        $processor = new QueuedNotificationEmailProcessor(
            $repository,
            $templateResolver,
            $mailService,
            $connection,
            $this->createMock(LoggerInterface::class),
            $this->createMock(AuditLogService::class),
        );

        $processor->run(Context::createDefaultContext());
    }
}
