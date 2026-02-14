<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Doctrine\DBAL\Connection;
use LieferzeitenAdmin\Service\Notification\NotificationEventService;
use LieferzeitenAdmin\Service\Notification\ShippingDateOverdueTaskService;
use LieferzeitenAdmin\Service\Notification\TaskAssignmentRuleResolver;
use LieferzeitenAdmin\Service\Notification\NotificationTriggerCatalog;
use LieferzeitenAdmin\Service\Notification\SalesChannelResolver;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\User\UserEntity;

class LieferzeitenPositionWriteService
{
    private const DEFAULT_ADDITIONAL_DELIVERY_ASSIGNEE_CONFIG_KEY = 'LieferzeitenAdmin.config.defaultAssigneeLieferterminAnfrageZusaetzlich';
    private const LEGACY_DEFAULT_ADDITIONAL_DELIVERY_ASSIGNEE_CONFIG_KEY = 'LieferzeitenAdmin.config.defaultAdditionalDeliveryAssignee';

    public function __construct(
        private readonly EntityRepository $positionRepository,
        private readonly EntityRepository $paketRepository,
        private readonly Connection $connection,
        private readonly EntityRepository $lieferterminLieferantHistoryRepository,
        private readonly EntityRepository $neuerLieferterminHistoryRepository,
        private readonly EntityRepository $neuerLieferterminPaketHistoryRepository,
        private readonly EntityRepository $userRepository,
        private readonly LieferzeitenTaskService $taskService,
        private readonly NotificationEventService $notificationEventService,
        private readonly TaskAssignmentRuleResolver $taskAssignmentRuleResolver,
        private readonly SystemConfigService $systemConfigService,
        private readonly SalesChannelResolver $salesChannelResolver,
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

        $this->taskService->closeLatestOpenTaskByPositionAndTriggerIfAssigneeMatches(
            $positionId,
            NotificationTriggerCatalog::SHIPPING_DATE_OVERDUE,
            $context,
        );
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

    public function updateNeuerLieferterminByPaket(string $paketId, \DateTimeImmutable $from, \DateTimeImmutable $to, string $expectedUpdatedAt, Context $context): void
    {
        $actor = $this->resolveActor($context);
        $changedAt = new \DateTimeImmutable();

        $this->assertPaketOptimisticLockOrThrow($paketId, $expectedUpdatedAt);

        $positionIds = $this->getPositionIdsByPaketId($paketId);
        if ($positionIds === []) {
            throw new WriteEndpointConflictException([
                'paketId' => $paketId,
                'exists' => false,
            ], 'The paket has no positions. Refresh the row.');
        }

        foreach ($positionIds as $positionId) {
            $this->touchPosition($positionId, $actor, $changedAt, $context);
        }

        $this->touchPaket($paketId, $actor, $changedAt, $context);
        $this->neuerLieferterminPaketHistoryRepository->create([
            [
                'id' => Uuid::randomHex(),
                'paketId' => $paketId,
                'lieferterminFrom' => $from,
                'lieferterminTo' => $to,
                'liefertermin' => $to,
                'lastChangedBy' => $actor,
                'lastChangedAt' => $changedAt,
            ],
        ], $context);
    }

    /** @return list<string> */
    public function getPositionIdsByPaketId(string $paketId): array
    {
        $positionIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) AS id
             FROM lieferzeiten_position
             WHERE paket_id = :paketId',
            ['paketId' => hex2bin($paketId)],
        );

        return array_values(array_filter(array_map(static fn ($id) => is_string($id) ? $id : null, $positionIds)));
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

    public function hasNeuerLieferterminHistoryForPosition(string $positionId): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*)
             FROM lieferzeiten_neuer_liefertermin_paket_history nph
             INNER JOIN lieferzeiten_position p ON p.paket_id = nph.paket_id
             WHERE p.id = :positionId',
            ['positionId' => hex2bin($positionId)],
        );

        return (int) $count > 0;
    }

    /** @return array{from: \DateTimeImmutable, to: \DateTimeImmutable}|null */
    public function getSupplierRangeBoundsByPaketId(string $paketId): ?array
    {
        $positionIds = $this->getPositionIdsByPaketId($paketId);
        if ($positionIds === []) {
            return null;
        }

        $minTo = null;
        $maxFrom = null;

        foreach ($positionIds as $positionId) {
            $range = $this->getLatestLieferterminLieferantRange($positionId);
            if ($range === null) {
                return null;
            }

            if ($maxFrom === null || $range['from'] > $maxFrom) {
                $maxFrom = $range['from'];
            }

            if ($minTo === null || $range['to'] < $minTo) {
                $minTo = $range['to'];
            }
        }

        if ($maxFrom === null || $minTo === null || $maxFrom > $minTo) {
            return null;
        }

        return ['from' => $maxFrom, 'to' => $minTo];
    }


    public function canUpdateNeuerLieferterminForPaket(string $paketId): bool
    {
        $status = $this->connection->fetchOne(
            'SELECT status FROM lieferzeiten_paket WHERE id = :id LIMIT 1',
            ['id' => hex2bin($paketId)],
        );

        if ($status === false || $status === null) {
            return false;
        }

        $normalized = strtolower(trim((string) $status));

        return !in_array($normalized, ['closed', 'done', 'completed', 'shipped', 'delivered', '8'], true);
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

    public function createAdditionalDeliveryRequest(
        string $positionId,
        ?string $initiator,
        Context $context,
        ?string $initiatorUserId = null,
        ?string $initiatorDisplay = null,
    ): string {
        $actor = $this->resolveActor($context);
        $changedAt = new \DateTimeImmutable();
        $notificationContext = $this->fetchNotificationContext($positionId);

        $resolvedContextUserId = $this->resolveActorUserId($context);
        $providedInitiatorUserId = (is_string($initiatorUserId) && Uuid::isValid($initiatorUserId)) ? $initiatorUserId : null;

        $resolvedInitiatorDisplay = is_string($initiatorDisplay) && trim($initiatorDisplay) !== ''
            ? trim($initiatorDisplay)
            : null;
        if ($resolvedInitiatorDisplay === null && is_string($initiator) && trim($initiator) !== '') {
            $resolvedInitiatorDisplay = trim($initiator);
        }

        $hasExplicitInitiator = $resolvedInitiatorDisplay !== null || $providedInitiatorUserId !== null;
        $resolvedInitiatorUserId = $hasExplicitInitiator
            ? $providedInitiatorUserId
            : ($resolvedContextUserId ?? $providedInitiatorUserId);

        $initiatorDisplay = $resolvedInitiatorDisplay ?? $actor;
        $initiatorUserId = $resolvedInitiatorUserId;

        $triggerKey = NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUESTED;
        $rule = $this->taskAssignmentRuleResolver->resolve($triggerKey, $context);
        $assigneeIdentifier = $this->resolveAdditionalDeliveryAssignee($rule);
        $dueDate = ShippingDateOverdueTaskService::nextBusinessDay($changedAt);

        $this->positionRepository->upsert([
            [
                'id' => $positionId,
                'additionalDeliveryRequestAt' => $changedAt,
                'additionalDeliveryRequestInitiator' => $initiatorDisplay,
                'lastChangedBy' => $actor,
                'lastChangedAt' => $changedAt,
            ],
        ], $context);

        $taskPayload = [
            'taskType' => 'additional-delivery-request',
            'triggerKey' => $triggerKey,
            'positionId' => $positionId,
            'createdBy' => $actor,
            'createdAt' => $changedAt->format(DATE_ATOM),
            'initiatorDisplay' => $initiatorDisplay,
            'initiatorUserId' => $initiatorUserId,
            'customerEmail' => $notificationContext['customerEmail'],
            'externalOrderId' => $notificationContext['externalOrderId'],
            'sourceSystem' => $notificationContext['sourceSystem'],
            'salesChannelId' => $notificationContext['salesChannelId'],
            'positionNumber' => $notificationContext['positionNumber'],
        ];

        $this->taskService->createTask(
            $taskPayload,
            $context,
            $initiatorDisplay,
            $assigneeIdentifier,
            $dueDate,
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
                $notificationContext['salesChannelId'],
            );
        }

        return $initiatorDisplay;
    }

    /** @param array<string, mixed>|null $rule */
    private function resolveAdditionalDeliveryAssignee(?array $rule): string
    {
        $ruleIsActive = (bool) ($rule['active'] ?? true);
        $ruleAssignee = is_string($rule['assigneeIdentifier'] ?? null)
            ? trim((string) $rule['assigneeIdentifier'])
            : '';

        if ($ruleIsActive && $ruleAssignee !== '') {
            return $ruleAssignee;
        }

        $defaultAssignee = trim((string) $this->systemConfigService->get(self::DEFAULT_ADDITIONAL_DELIVERY_ASSIGNEE_CONFIG_KEY));
        if ($defaultAssignee === '') {
            $defaultAssignee = trim((string) $this->systemConfigService->get(self::LEGACY_DEFAULT_ADDITIONAL_DELIVERY_ASSIGNEE_CONFIG_KEY));
        }

        if ($defaultAssignee !== '') {
            return $defaultAssignee;
        }

        throw new AdditionalDeliveryAssigneeMissingException('No assignee available for additional delivery request task. Configure an active assignment rule or set LieferzeitenAdmin.config.defaultAssigneeLieferterminAnfrageZusaetzlich.');
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

    private function assertPaketOptimisticLockOrThrow(string $paketId, string $expectedUpdatedAt): void
    {
        $normalizedExpected = $this->normalizeDateTime($expectedUpdatedAt);

        $currentUpdatedAt = $this->connection->fetchOne(
            'SELECT updated_at FROM lieferzeiten_paket WHERE id = :id LIMIT 1',
            ['id' => hex2bin($paketId)],
        );

        if ($currentUpdatedAt === false) {
            throw new WriteEndpointConflictException([
                'paketId' => $paketId,
                'exists' => false,
            ], 'The paket no longer exists. Refresh the row.');
        }

        $normalizedCurrent = $this->normalizeDateTime((string) $currentUpdatedAt);
        if ($normalizedExpected === $normalizedCurrent) {
            return;
        }

        throw new WriteEndpointConflictException([
            'paketId' => $paketId,
            'exists' => true,
            'updatedAt' => $normalizedCurrent,
        ], 'Concurrent update detected. Refresh the row and retry your edit.');
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
             FROM lieferzeiten_neuer_liefertermin_paket_history
             WHERE paket_id = (
                SELECT paket_id FROM lieferzeiten_position WHERE id = :positionId LIMIT 1
             )
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

    private function touchPaket(string $paketId, string $actor, \DateTimeImmutable $changedAt, Context $context): void
    {
        $this->paketRepository->upsert([
            [
                'id' => $paketId,
                'lastChangedBy' => $actor,
                'lastChangedAt' => $changedAt,
            ],
        ], $context);
    }


    private function resolveActorUserId(Context $context): ?string
    {
        $source = $context->getSource();
        if (!($source instanceof AdminApiSource)) {
            return null;
        }

        $userId = $source->getUserId();

        return is_string($userId) && Uuid::isValid($userId) ? $userId : null;
    }

    private function resolveActor(Context $context): string
    {
        $source = $context->getSource();
        if (!($source instanceof AdminApiSource)) {
            return 'system';
        }

        $userId = $source->getUserId();
        if ($userId === null) {
            return 'system';
        }

        $user = $this->userRepository->search(new Criteria([$userId]), $context)->first();
        if (!($user instanceof UserEntity)) {
            return 'system';
        }

        $fullName = trim(sprintf('%s %s', (string) ($user->getFirstName() ?? ''), (string) ($user->getLastName() ?? '')));
        if ($fullName !== '') {
            return $fullName;
        }

        $username = trim((string) ($user->getUsername() ?? ''));

        return $username !== '' ? $username : 'system';
    }

    /** @return array{externalOrderId:?string,sourceSystem:?string,salesChannelId:?string,customerEmail:?string,positionNumber:?string} */
    private function fetchNotificationContext(string $positionId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT p.position_number, paket.external_order_id, paket.source_system, paket.sales_channel_id, paket.customer_email
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
                'salesChannelId' => null,
                'customerEmail' => null,
                'positionNumber' => null,
            ];
        }

        $sourceSystem = isset($row['source_system']) ? (string) $row['source_system'] : null;
        $externalOrderId = isset($row['external_order_id']) ? (string) $row['external_order_id'] : null;
        $salesChannelId = $this->salesChannelResolver->resolve(
            $sourceSystem,
            $externalOrderId,
            isset($row['position_number']) ? (string) $row['position_number'] : null,
            isset($row['sales_channel_id']) ? (string) $row['sales_channel_id'] : null,
        );

        return [
            'externalOrderId' => $externalOrderId,
            'sourceSystem' => $sourceSystem,
            'salesChannelId' => $salesChannelId,
            'customerEmail' => isset($row['customer_email']) ? (string) $row['customer_email'] : null,
            'positionNumber' => isset($row['position_number']) ? (string) $row['position_number'] : null,
        ];
    }
}
