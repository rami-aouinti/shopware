<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Doctrine\DBAL\Connection;
use LieferzeitenAdmin\Service\Notification\NotificationEventService;
use LieferzeitenAdmin\Service\Notification\NotificationTriggerCatalog;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

class LieferzeitenPositionWriteService
{
    public function __construct(
        private readonly EntityRepository $positionRepository,
        private readonly Connection $connection,
        private readonly EntityRepository $lieferterminLieferantHistoryRepository,
        private readonly EntityRepository $neuerLieferterminHistoryRepository,
        private readonly LieferzeitenTaskService $taskService,
        private readonly NotificationEventService $notificationEventService,
    ) {
    }

    public function updateLieferterminLieferant(string $positionId, \DateTimeImmutable $from, \DateTimeImmutable $to, string $expectedUpdatedAt, Context $context): void
    {
        $actor = $this->resolveActor($context);
        $changedAt = new \DateTimeImmutable();

        $this->assertOptimisticLockOrThrow($positionId, $expectedUpdatedAt);
        $this->touchPosition($positionId, $actor, $changedAt, $context);

        $this->lieferterminLieferantHistoryRepository->create([
            [
                'id' => Uuid::randomHex(),
                'positionId' => $positionId,
                'lieferterminFrom' => $from,
                'lieferterminTo' => $to,
                'liefertermin' => $to,
                'lastChangedBy' => $actor,
                'lastChangedAt' => $changedAt,
            ],
        ], $context);
    }

    public function updateNeuerLiefertermin(string $positionId, \DateTimeImmutable $from, \DateTimeImmutable $to, string $expectedUpdatedAt, Context $context): void
    {
        $actor = $this->resolveActor($context);
        $changedAt = new \DateTimeImmutable();

        $this->assertOptimisticLockOrThrow($positionId, $expectedUpdatedAt);
        $this->touchPosition($positionId, $actor, $changedAt, $context);

        $this->neuerLieferterminHistoryRepository->create([
            [
                'id' => Uuid::randomHex(),
                'positionId' => $positionId,
                'lieferterminFrom' => $from,
                'lieferterminTo' => $to,
                'liefertermin' => $to,
                'lastChangedBy' => $actor,
                'lastChangedAt' => $changedAt,
            ],
        ], $context);
    }

    public function getLatestLieferterminLieferantRange(string $positionId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT liefertermin_from, liefertermin_to, liefertermin
             FROM lieferzeiten_liefertermin_lieferant_history
             WHERE position_id = :positionId
             ORDER BY created_at DESC
             LIMIT 1',
            ['positionId' => hex2bin($positionId)],
        );

        if (!is_array($row)) {
            return null;
        }

        $from = isset($row['liefertermin_from']) && $row['liefertermin_from'] !== null
            ? new \DateTimeImmutable((string) $row['liefertermin_from'])
            : null;
        $to = isset($row['liefertermin_to']) && $row['liefertermin_to'] !== null
            ? new \DateTimeImmutable((string) $row['liefertermin_to'])
            : null;

        if ($from === null || $to === null) {
            if (!isset($row['liefertermin']) || $row['liefertermin'] === null) {
                return null;
            }

            $legacyDate = new \DateTimeImmutable((string) $row['liefertermin']);

            return ['from' => $legacyDate, 'to' => $legacyDate];
        }

        return ['from' => $from, 'to' => $to];
    }

    public function updateComment(string $positionId, string $comment, string $expectedUpdatedAt, Context $context): void
    {
        $actor = $this->resolveActor($context);
        $changedAt = new \DateTimeImmutable();

        $this->assertOptimisticLockOrThrow($positionId, $expectedUpdatedAt);

        $this->positionRepository->upsert([
            [
                'id' => $positionId,
                'comment' => $comment,
                'currentComment' => $comment,
                'lastChangedBy' => $actor,
                'lastChangedAt' => $changedAt,
            ],
        ], $context);
    }

    public function createAdditionalDeliveryRequest(string $positionId, string $initiator, Context $context): void
    {
        $actor = $this->resolveActor($context);
        $changedAt = new \DateTimeImmutable();
        $notificationContext = $this->fetchNotificationContext($positionId);

        $this->positionRepository->upsert([
            [
                'id' => $positionId,
                'additionalDeliveryRequestAt' => $changedAt,
                'additionalDeliveryRequestInitiator' => $initiator,
                'lastChangedBy' => $actor,
                'lastChangedAt' => $changedAt,
            ],
        ], $context);

        $taskPayload = [
            'taskType' => 'additional-delivery-request',
            'triggerKey' => NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUESTED,
            'positionId' => $positionId,
            'createdBy' => $actor,
            'createdAt' => $changedAt->format(DATE_ATOM),
            'initiator' => $initiator,
            'customerEmail' => $notificationContext['customerEmail'],
            'externalOrderId' => $notificationContext['externalOrderId'],
            'sourceSystem' => $notificationContext['sourceSystem'],
            'positionNumber' => $notificationContext['positionNumber'],
        ];

        $this->taskService->createTask(
            $taskPayload,
            $initiator,
            null,
            null,
            $context,
        );

        foreach (NotificationTriggerCatalog::channels() as $channel) {
            $this->notificationEventService->dispatch(
                sprintf('additional-request:%s:%s', $positionId, $channel),
                NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUESTED,
                $channel,
                array_merge($taskPayload, ['requestedAt' => $changedAt->format(DATE_ATOM)]),
                $context,
                $notificationContext['externalOrderId'],
                $notificationContext['sourceSystem'],
            );
        }
    }

    private function assertOptimisticLockOrThrow(string $positionId, string $expectedUpdatedAt): void
    {
        $normalizedExpected = $this->normalizeDateTime($expectedUpdatedAt);

        $currentUpdatedAt = $this->connection->fetchOne(
            'SELECT updated_at FROM lieferzeiten_position WHERE id = :id LIMIT 1',
            ['id' => hex2bin($positionId)],
        );

        if ($currentUpdatedAt === false) {
            throw new WriteEndpointConflictException([
                'positionId' => $positionId,
                'exists' => false,
            ], 'The position no longer exists. Refresh the row.');
        }

        $normalizedCurrent = $this->normalizeDateTime((string) $currentUpdatedAt);
        if ($normalizedExpected === $normalizedCurrent) {
            return;
        }

        throw new WriteEndpointConflictException($this->buildRefreshSnapshot($positionId), 'Concurrent update detected. Refresh the row and retry your edit.');
    }

    /** @return array<string, mixed> */
    private function buildRefreshSnapshot(string $positionId): array
    {
        $position = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(id)) AS id, comment, current_comment, last_changed_by, last_changed_at, updated_at
             FROM lieferzeiten_position
             WHERE id = :id
             LIMIT 1',
            ['id' => hex2bin($positionId)],
        );

        if (!is_array($position)) {
            return [
                'positionId' => $positionId,
                'exists' => false,
            ];
        }

        $supplierRange = $this->getLatestLieferterminLieferantRange($positionId);

        $newRange = $this->connection->fetchAssociative(
            'SELECT liefertermin_from, liefertermin_to, liefertermin
             FROM lieferzeiten_neuer_liefertermin_history
             WHERE position_id = :positionId
             ORDER BY created_at DESC
             LIMIT 1',
            ['positionId' => hex2bin($positionId)],
        );

        $formatRange = static function (?array $range): ?array {
            if ($range === null) {
                return null;
            }

            return [
                'from' => $range['from']->format('Y-m-d'),
                'to' => $range['to']->format('Y-m-d'),
            ];
        };

        $newRangeValue = null;
        if (is_array($newRange)) {
            $from = $newRange['liefertermin_from'] !== null ? new \DateTimeImmutable((string) $newRange['liefertermin_from']) : null;
            $to = $newRange['liefertermin_to'] !== null ? new \DateTimeImmutable((string) $newRange['liefertermin_to']) : null;

            if ($from !== null && $to !== null) {
                $newRangeValue = ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')];
            } elseif ($newRange['liefertermin'] !== null) {
                $legacy = new \DateTimeImmutable((string) $newRange['liefertermin']);
                $newRangeValue = ['from' => $legacy->format('Y-m-d'), 'to' => $legacy->format('Y-m-d')];
            }
        }

        return [
            'positionId' => (string) $position['id'],
            'exists' => true,
            'updatedAt' => $this->normalizeDateTime((string) $position['updated_at']),
            'lastChangedAt' => $position['last_changed_at'] !== null ? $this->normalizeDateTime((string) $position['last_changed_at']) : null,
            'lastChangedBy' => $position['last_changed_by'],
            'comment' => $position['comment'],
            'currentComment' => $position['current_comment'],
            'lieferterminLieferant' => $formatRange($supplierRange),
            'neuerLiefertermin' => $newRangeValue,
        ];
    }

    private function normalizeDateTime(string $value): string
    {
        return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s.v');
    }

    private function touchPosition(string $positionId, string $actor, \DateTimeImmutable $changedAt, Context $context): void
    {
        $this->positionRepository->upsert([
            [
                'id' => $positionId,
                'lastChangedBy' => $actor,
                'lastChangedAt' => $changedAt,
            ],
        ], $context);
    }

    private function resolveActor(Context $context): string
    {
        $source = $context->getSource();
        if ($source instanceof AdminApiSource && $source->getUserId() !== null) {
            return $source->getUserId();
        }

        return 'system';
    }

    /** @return array{externalOrderId:?string,sourceSystem:?string,customerEmail:?string,positionNumber:?string} */
    private function fetchNotificationContext(string $positionId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT p.position_number, paket.external_order_id, paket.source_system, paket.customer_email
             FROM lieferzeiten_position p
             LEFT JOIN lieferzeiten_paket paket ON paket.id = p.paket_id
             WHERE p.id = :positionId
             LIMIT 1',
            ['positionId' => hex2bin($positionId)],
        );

        if (!is_array($row)) {
            return [
                'externalOrderId' => null,
                'sourceSystem' => null,
                'customerEmail' => null,
                'positionNumber' => null,
            ];
        }

        return [
            'externalOrderId' => isset($row['external_order_id']) ? (string) $row['external_order_id'] : null,
            'sourceSystem' => isset($row['source_system']) ? (string) $row['source_system'] : null,
            'customerEmail' => isset($row['customer_email']) ? (string) $row['customer_email'] : null,
            'positionNumber' => isset($row['position_number']) ? (string) $row['position_number'] : null,
        ];
    }
}
