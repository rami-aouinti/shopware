<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Notification;

use LieferzeitenAdmin\Service\Audit\AuditLogService;
use LieferzeitenAdmin\Service\Reliability\IntegrationReliabilityService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class NotificationEventService
{
    public function __construct(
        private readonly EntityRepository $notificationEventRepository,
        private readonly NotificationToggleResolver $toggleResolver,
        private readonly LoggerInterface $logger,
        private readonly IntegrationReliabilityService $reliabilityService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function dispatch(
        string $eventKey,
        string $triggerKey,
        string $channel,
        array $payload,
        Context $context,
        ?string $externalOrderId = null,
        ?string $sourceSystem = null,
        ?string $salesChannelId = null,
    ): bool {
        if (!$this->toggleResolver->isEnabled($triggerKey, $channel, $context, $salesChannelId)) {
            return false;
        }

        if ($this->existsByEventKey($eventKey, $context)) {
            return false;
        }

        $this->reliabilityService->executeWithRetry('mails', 'queue_notification_event', function () use ($eventKey, $triggerKey, $channel, $payload, $context, $externalOrderId, $sourceSystem): void {
            $this->notificationEventRepository->create([[
                'id' => Uuid::randomHex(),
                'eventKey' => $eventKey,
                'triggerKey' => $triggerKey,
                'channel' => $channel,
                'externalOrderId' => $externalOrderId,
                'sourceSystem' => $sourceSystem,
                'payload' => $payload,
                'status' => 'queued',
                'dispatchedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ]], $context);
        }, $context, payload: ['eventKey' => $eventKey, 'channel' => $channel]);

        $this->logger->info('Notification event queued.', [
            'eventKey' => $eventKey,
            'triggerKey' => $triggerKey,
            'channel' => $channel,
        ]);

        $this->auditLogService->log('notification_queued', 'notification_event', $eventKey, $context, [
            'triggerKey' => $triggerKey,
            'channel' => $channel,
        ], 'mails');

        return true;
    }

    private function existsByEventKey(string $eventKey, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('eventKey', $eventKey));

        return $this->notificationEventRepository->search($criteria, $context)->first() !== null;
    }
}
