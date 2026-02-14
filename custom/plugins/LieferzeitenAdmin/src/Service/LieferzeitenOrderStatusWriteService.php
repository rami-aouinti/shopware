<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class LieferzeitenOrderStatusWriteService
{
    public function __construct(
        private readonly EntityRepository $paketRepository,
        private readonly Connection $connection,
    ) {
    }

    public function updateOrderStatus(string $paketId, int $status, string $expectedUpdatedAt, Context $context): void
    {
        if (!in_array($status, [7, 8], true)) {
            throw new \InvalidArgumentException('Only statuses 7 and 8 are allowed.');
        }

        $this->assertPaketOptimisticLockOrThrow($paketId, $expectedUpdatedAt);

        $actor = $this->resolveActor($context);
        $changedAt = new \DateTimeImmutable();
        $queue = $this->loadStatusPushQueue($paketId);

        $queue[] = [
            'targetStatus' => $status,
            'reason' => sprintf('lms_user_status_%d', $status),
            'triggerSource' => 'user_lms',
            'attempts' => 0,
            'state' => 'pending',
            'nextAttemptAt' => $changedAt->format(DATE_ATOM),
            'createdAt' => $changedAt->format(DATE_ATOM),
            'requestedBy' => $actor,
        ];

        $this->paketRepository->upsert([[
            'id' => $paketId,
            'status' => (string) $status,
            'lastChangedBy' => $actor,
            'lastChangedAt' => $changedAt,
            'statusPushQueue' => $queue,
        ]], $context);
    }

    /** @return array<int, array<string, mixed>> */
    private function loadStatusPushQueue(string $paketId): array
    {
        $raw = $this->connection->fetchOne(
            'SELECT status_push_queue FROM lieferzeiten_paket WHERE id = :id LIMIT 1',
            ['id' => hex2bin($paketId)],
        );

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
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

    private function normalizeDateTime(string $value): string
    {
        return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s.v');
    }

    private function resolveActor(Context $context): string
    {
        $source = $context->getSource();
        if ($source instanceof AdminApiSource && $source->getUserId() !== null) {
            return sprintf('lms:%s', $source->getUserId());
        }

        return 'lms:system';
    }
}
