<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

class LieferzeitenPositionWriteService
{
    public function __construct(
        private readonly EntityRepository $positionRepository,
        private readonly EntityRepository $lieferterminLieferantHistoryRepository,
        private readonly EntityRepository $neuerLieferterminHistoryRepository,
    ) {
    }

    public function updateLieferterminLieferant(string $positionId, int $days, Context $context): void
    {
        $actor = $this->resolveActor($context);
        $changedAt = new \DateTimeImmutable();

        $this->touchPosition($positionId, $actor, $changedAt, $context);

        $this->lieferterminLieferantHistoryRepository->create([
            [
                'id' => Uuid::randomHex(),
                'positionId' => $positionId,
                'liefertermin' => $changedAt->modify(sprintf('+%d days', $days)),
                'lastChangedBy' => $actor,
                'lastChangedAt' => $changedAt,
            ],
        ], $context);
    }

    public function updateNeuerLiefertermin(string $positionId, int $days, Context $context): void
    {
        $actor = $this->resolveActor($context);
        $changedAt = new \DateTimeImmutable();

        $this->touchPosition($positionId, $actor, $changedAt, $context);

        $this->neuerLieferterminHistoryRepository->create([
            [
                'id' => Uuid::randomHex(),
                'positionId' => $positionId,
                'liefertermin' => $changedAt->modify(sprintf('+%d days', $days)),
                'lastChangedBy' => $actor,
                'lastChangedAt' => $changedAt,
            ],
        ], $context);
    }

    public function updateComment(string $positionId, string $comment, Context $context): void
    {
        $actor = $this->resolveActor($context);
        $changedAt = new \DateTimeImmutable();

        $this->positionRepository->upsert([
            [
                'id' => $positionId,
                'comment' => $comment,
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
