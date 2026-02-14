<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Notification;

use Doctrine\DBAL\Connection;
use LieferzeitenAdmin\Entity\NotificationEventEntity;
use LieferzeitenAdmin\Service\Audit\AuditLogService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class QueuedNotificationEmailProcessor
{
    public function __construct(
        private readonly EntityRepository $notificationEventRepository,
        private readonly NotificationTemplateResolver $templateResolver,
        private readonly AbstractMailService $mailService,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function run(Context $context, int $batchSize = 50): void
    {
        $criteria = new Criteria();
        $criteria->setLimit($batchSize);
        $criteria->addFilter(new EqualsFilter('channel', 'email'));
        $criteria->addFilter(new EqualsFilter('status', 'queued'));

        $events = $this->notificationEventRepository->search($criteria, $context);
        foreach ($events as $event) {
            if (!$event instanceof NotificationEventEntity) {
                continue;
            }

            $this->processEvent($event, $context);
        }
    }

    private function processEvent(NotificationEventEntity $event, Context $context): void
    {
        if (!$this->claimQueuedEvent($event->getId())) {
            return;
        }

        $payload = $event->getPayload();
        $variables = [
            'eventKey' => $event->getEventKey(),
            'triggerKey' => $event->getTriggerKey(),
            'channel' => $event->getChannel(),
            'externalOrderId' => $event->getExternalOrderId(),
            'sourceSystem' => $event->getSourceSystem(),
            'salesChannelId' => $payload['salesChannelId'] ?? null,
            'languageId' => $payload['languageId'] ?? null,
            'customerEmail' => $payload['customerEmail'] ?? null,
            'occurredAt' => $event->getDispatchedAt()?->format(DATE_ATOM),
        ];

        foreach ($payload as $key => $value) {
            if (!is_string($key) || $key === '' || array_key_exists($key, $variables)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $variables[$key] = $value;
            }
        }

        try {
            $recipient = $this->resolveRecipientEmail($payload);
            if ($recipient === null) {
                throw new \RuntimeException('Missing notification recipient in payload.');
            }

            $template = $this->templateResolver->resolve(
                $event->getTriggerKey(),
                is_string($variables['salesChannelId']) ? $variables['salesChannelId'] : null,
                is_string($variables['languageId']) ? $variables['languageId'] : null,
                $variables,
                $context,
            );

            $data = [
                'recipients' => [$recipient => $recipient],
                'subject' => $template['subject'],
                'contentHtml' => $template['contentHtml'],
                'contentPlain' => $template['contentPlain'],
                'salesChannelId' => $variables['salesChannelId'],
            ];

            $this->mailService->send($data, $context, ['notification' => $variables, 'payload' => $payload]);

            $this->updateStatus($event->getId(), 'sent');
            $this->logger->info('Notification event sent.', ['eventKey' => $event->getEventKey()]);
            $this->auditLogService->log('notification_sent', 'notification_event', $event->getEventKey(), $context, [
                'triggerKey' => $event->getTriggerKey(),
                'channel' => $event->getChannel(),
            ], 'mails');
        } catch (\Throwable $e) {
            $this->updateStatus($event->getId(), 'failed');
            $this->logger->error('Notification event failed.', [
                'eventKey' => $event->getEventKey(),
                'error' => $e->getMessage(),
            ]);
            $this->auditLogService->log('notification_failed', 'notification_event', $event->getEventKey(), $context, [
                'triggerKey' => $event->getTriggerKey(),
                'channel' => $event->getChannel(),
                'error' => $e->getMessage(),
            ], 'mails');
        }
    }

    /** @param array<string,mixed> $payload */
    private function resolveRecipientEmail(array $payload): ?string
    {
        $candidates = [
            isset($payload['recipientEmail']) && is_string($payload['recipientEmail']) ? trim($payload['recipientEmail']) : '',
            isset($payload['customerEmail']) && is_string($payload['customerEmail']) ? trim($payload['customerEmail']) : '',
            isset($payload['initiatorEmail']) && is_string($payload['initiatorEmail']) ? trim($payload['initiatorEmail']) : '',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        $recipientUserId = isset($payload['recipientUserId']) && is_string($payload['recipientUserId'])
            ? trim($payload['recipientUserId'])
            : '';
        if ($recipientUserId === '' || !Uuid::isValid($recipientUserId)) {
            return null;
        }

        $email = $this->connection->fetchOne(
            'SELECT `email` FROM `user` WHERE `id` = :id LIMIT 1',
            ['id' => hex2bin($recipientUserId)],
        );

        if (!is_string($email) || trim($email) === '') {
            return null;
        }

        return trim($email);
    }

    private function claimQueuedEvent(string $id): bool
    {
        return $this->connection->executeStatement(
            'UPDATE `lieferzeiten_notification_event` SET `status` = :status WHERE `id` = :id AND `status` = :queued',
            ['status' => 'processing', 'id' => hex2bin($id), 'queued' => 'queued'],
        ) === 1;
    }

    private function updateStatus(string $id, string $status): void
    {
        $this->connection->executeStatement(
            'UPDATE `lieferzeiten_notification_event` SET `status` = :status, `updated_at` = :updatedAt WHERE `id` = :id',
            ['status' => $status, 'updatedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'), 'id' => hex2bin($id)],
        );
    }
}
