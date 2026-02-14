<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Notification;

use LieferzeitenAdmin\Entity\PaketEntity;
use LieferzeitenAdmin\Entity\PositionEntity;
use LieferzeitenAdmin\Service\ChannelPdmsThresholdResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ShippingDateOverdueTaskService
{
    public function __construct(
        private readonly EntityRepository $positionRepository,
        private readonly TaskAssignmentRuleResolver $taskAssignmentRuleResolver,
        private readonly EntityRepository $taskRepository,
        private readonly ChannelPdmsThresholdResolver $channelPdmsThresholdResolver,
    ) {
    }

    public function run(Context $context): void
    {
        $now = new \DateTimeImmutable();

        $criteria = new Criteria();
        $criteria->addAssociation('paket');
        $criteria->addFilter(new EqualsFilter('paket.shippingDate', null));

        /** @var iterable<PositionEntity> $positions */
        $positions = $this->positionRepository->search($criteria, $context)->getEntities();

        foreach ($positions as $position) {
            $paket = $position->getPaket();
            if ($paket === null) {
                continue;
            }

            // businessDateTo is treated as "spätester Versandzeitpunkt" from the integration payload.
            // paket.shippingDate cannot be used here because it is the actual shipment timestamp and remains NULL until shipped.
            $businessDateTo = $paket->getBusinessDateTo();
            if (!$businessDateTo instanceof \DateTimeInterface) {
                continue;
            }

            $settings = $this->channelPdmsThresholdResolver->resolveForOrder(
                (string) ($paket->getSourceSystem() ?? 'shopware'),
                $paket->getExternalOrderId(),
                $position->getPositionNumber(),
            );

            $cutoffToday = $this->buildCutoffDate($now, $settings['shipping']['cutoff']);
            if ($now < $cutoffToday) {
                continue;
            }

            $thresholdDate = $this->buildThresholdDate($now, $settings['shipping']);
            if (\DateTimeImmutable::createFromInterface($businessDateTo) >= $thresholdDate) {
                continue;
            }

            $trigger = NotificationTriggerCatalog::SHIPPING_DATE_OVERDUE;
            if ($this->hasActiveTaskForPosition($position->getUniqueIdentifier(), $trigger, $context)) {
                continue;
            }

            $assignmentContext = $this->buildAssignmentContext($position, $paket);
            $rule = $this->taskAssignmentRuleResolver->resolve($trigger, $context, $assignmentContext);
            $dueDate = self::nextBusinessDay($now);

            $this->taskRepository->create([[
                'id' => \Shopware\Core\Framework\Uuid\Uuid::randomHex(),
                'status' => 'open',
                'assignee' => is_array($rule) ? ($rule['assigneeIdentifier'] ?? null) : null,
                'dueDate' => $dueDate,
                'initiator' => 'system',
                'payload' => [
                    'taskType' => 'shipping-date-overdue',
                    'positionId' => $position->getUniqueIdentifier(),
                    'positionNumber' => $position->getPositionNumber(),
                    'articleNumber' => $position->getArticleNumber(),
                    'externalOrderId' => $paket->getExternalOrderId(),
                    'sourceSystem' => $paket->getSourceSystem(),
                    'trigger' => $trigger,
                    'dueDate' => $dueDate->format('Y-m-d'),
                    'assignment' => $rule,
                    'assignmentContext' => $assignmentContext,
                ],
            ]], $context);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAssignmentContext(PositionEntity $position, PaketEntity $paket): array
    {
        return [
            'position' => [
                'id' => $position->getUniqueIdentifier(),
                'number' => $position->getPositionNumber(),
                'status' => $position->getStatus(),
                'articleNumber' => $position->getArticleNumber(),
                'artikelname' => $position->getArtikelname(),
            ],
            'paket' => [
                'id' => $paket->getUniqueIdentifier(),
                'number' => $paket->getPaketNumber(),
                'status' => $paket->getStatus(),
                'sourceSystem' => $paket->getSourceSystem(),
                'externalOrderId' => $paket->getExternalOrderId(),
            ],
            'sourceSystem' => $paket->getSourceSystem(),
            'externalOrderId' => $paket->getExternalOrderId(),
            'salesChannel' => null,
            'status' => $position->getStatus() ?? $paket->getStatus(),
        ];
    }

    private function hasActiveTaskForPosition(string $positionId, string $trigger, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('payload.positionId', $positionId));
        $criteria->addFilter(new EqualsFilter('payload.trigger', $trigger));
        $criteria->addFilter(new EqualsFilter('closedAt', null));

        return $this->taskRepository->search($criteria, $context)->first() !== null;
    }

    public static function nextBusinessDay(\DateTimeImmutable $start): \DateTimeImmutable
    {
        $date = $start->setTime(0, 0)->modify('+1 day');

        while (in_array((int) $date->format('N'), [6, 7], true)) {
            $date = $date->modify('+1 day');
        }

        return $date;
    }

    private function buildCutoffDate(\DateTimeImmutable $now, string $cutoff): \DateTimeImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $cutoff));

        return $now->setTime($hour, $minute);
    }

    /**
     * @param array{workingDays:int,cutoff:string} $settings
     */
    private function buildThresholdDate(\DateTimeImmutable $now, array $settings): \DateTimeImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $settings['cutoff']));
        $threshold = $now->setTime($hour, $minute);

        $remaining = max(0, (int) ($settings['workingDays'] ?? 0));
        while ($remaining > 0) {
            $threshold = $threshold->modify('-1 day');
            $weekday = (int) $threshold->format('N');
            if ($weekday >= 6) {
                continue;
            }

            --$remaining;
        }

        return $threshold;
    }
}
