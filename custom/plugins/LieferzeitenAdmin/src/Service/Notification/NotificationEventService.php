<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Notification;

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

        $this->logger->info('Notification event queued.', [
            'eventKey' => $eventKey,
            'triggerKey' => $triggerKey,
            'channel' => $channel,
        ]);

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
