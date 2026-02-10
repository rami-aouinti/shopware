<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Doctrine\DBAL\Connection;
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
    ) {
    }

    public function updateLieferterminLieferant(string $positionId, \DateTimeImmutable $from, \DateTimeImmutable $to, Context $context): void
    {
        $actor = $this->resolveActor($context);
        $changedAt = new \DateTimeImmutable();

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

    public function updateNeuerLiefertermin(string $positionId, \DateTimeImmutable $from, \DateTimeImmutable $to, Context $context): void
    {
        $actor = $this->resolveActor($context);
        $changedAt = new \DateTimeImmutable();

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

    public function updateComment(string $positionId, string $comment, Context $context): void
    {
        $actor = $this->resolveActor($context);
        $changedAt = new \DateTimeImmutable();

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

        $this->positionRepository->upsert([
            [
                'id' => $positionId,
                'additionalDeliveryRequestAt' => $changedAt,
                'additionalDeliveryRequestInitiator' => $initiator,
                'lastChangedBy' => $actor,
                'lastChangedAt' => $changedAt,
            ],
        ], $context);

        $this->taskService->createTask(
            [
                'taskType' => 'additional-delivery-request',
                'triggerKey' => 'additional-delivery-request',
                'positionId' => $positionId,
                'createdBy' => $actor,
                'createdAt' => $changedAt->format(DATE_ATOM),
            ],
            $initiator,
            null,
            null,
            $context,
        );
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
}
