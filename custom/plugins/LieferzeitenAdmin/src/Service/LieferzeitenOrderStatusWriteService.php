<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Doctrine\DBAL\Connection;
use LieferzeitenAdmin\Entity\PaketEntity;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class LieferzeitenOrderStatusWriteService
{
    private const TRIGGER_SOURCE_LMS_USER = 'lms_user';

    public function __construct(
        private readonly EntityRepository $paketRepository,
        private readonly Connection $connection,
    ) {
    }

    /** @return array<string, mixed> */
    public function updateOrderStatus(string $paketId, int $targetStatus, Context $context): array
    {
        if (!in_array($targetStatus, [7, 8], true)) {
            throw new \InvalidArgumentException('Only statuses 7 and 8 are allowed.');
        }

        $paket = $this->findPaketById($paketId, $context);
        if (!$paket instanceof PaketEntity) {
            throw new \RuntimeException('Order not found.');
        }

        $actor = $this->resolveActor($context);
        $now = new \DateTimeImmutable();
        $queue = is_array($paket->getStatusPushQueue()) ? $paket->getStatusPushQueue() : [];
        $queue = $this->enqueueStatusPush($queue, $targetStatus, 'lms_user_status_change');

        $this->paketRepository->upsert([[
            'id' => $paketId,
            'status' => (string) $targetStatus,
            'lastChangedBy' => $actor,
            'lastChangedAt' => $now,
            'statusPushQueue' => $queue,
        ]], $context);

        $updatedAt = $this->connection->fetchOne(
            'SELECT updated_at FROM lieferzeiten_paket WHERE id = :id LIMIT 1',
            ['id' => hex2bin($paketId)],
        );

        return [
            'id' => $paketId,
            'status' => (string) $targetStatus,
            'lastChangedBy' => $actor,
            'lastChangedAt' => $now->format(DATE_ATOM),
            'updatedAt' => $updatedAt ? (new \DateTimeImmutable((string) $updatedAt))->format(DATE_ATOM) : null,
        ];
    }

    /** @return array<int, array<string,mixed>> */
    private function enqueueStatusPush(array $queue, int $targetStatus, string $reason): array
    {
        foreach ($queue as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ((int) ($item['targetStatus'] ?? 0) === $targetStatus && (string) ($item['state'] ?? '') === 'pending') {
                return $queue;
            }
        }

        $queue[] = [
            'targetStatus' => $targetStatus,
            'reason' => $reason,
            'triggerSource' => self::TRIGGER_SOURCE_LMS_USER,
            'attempts' => 0,
            'state' => 'pending',
            'nextAttemptAt' => date(DATE_ATOM),
            'createdAt' => date(DATE_ATOM),
        ];

        return $queue;
    }

    private function findPaketById(string $paketId, Context $context): ?PaketEntity
    {
        $criteria = new Criteria([$paketId]);

        /** @var PaketEntity|null $entity */
        $entity = $this->paketRepository->search($criteria, $context)->first();

        return $entity;
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
