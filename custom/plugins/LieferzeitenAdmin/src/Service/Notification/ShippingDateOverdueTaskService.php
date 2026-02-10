<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Notification;

use LieferzeitenAdmin\Entity\PositionEntity;
use LieferzeitenAdmin\Entity\TaskAssignmentRuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class ShippingDateOverdueTaskService
{
    public function __construct(
        private readonly EntityRepository $positionRepository,
        private readonly EntityRepository $taskAssignmentRuleRepository,
        private readonly NotificationEventService $notificationEventService,
    ) {
    }

    public function run(Context $context): void
    {
        $now = new \DateTimeImmutable();
        if ((int) $now->format('H') < 12) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addAssociation('paket');
        $criteria->addFilter(new EqualsFilter('paket.shippingDate', null));
        $criteria->addFilter(new RangeFilter('paket.calculatedDeliveryDate', [RangeFilter::LT => $now->format(DATE_ATOM)]));

        /** @var iterable<PositionEntity> $positions */
        $positions = $this->positionRepository->search($criteria, $context)->getEntities();

        foreach ($positions as $position) {
            $paket = $position->getPaket();
            if ($paket === null) {
                continue;
            }

            $trigger = NotificationTriggerCatalog::SHIPPING_DATE_OVERDUE;
            $eventKey = sprintf('task:%s:%s', $trigger, $position->getUniqueIdentifier());

            $rule = $this->resolveAssignmentRule($trigger, $context);
            $dueDate = $this->nextBusinessDay($now)->format('Y-m-d');

            $payload = [
                'taskType' => 'shipping-date-overdue',
                'positionId' => $position->getUniqueIdentifier(),
                'positionNumber' => $position->getPositionNumber(),
                'articleNumber' => $position->getArticleNumber(),
                'externalOrderId' => $paket->getExternalOrderId(),
                'trigger' => $trigger,
                'dueDate' => $dueDate,
                'assignment' => $rule,
            ];

            foreach (NotificationTriggerCatalog::channels() as $channel) {
                $this->notificationEventService->dispatch(
                    $eventKey . ':' . $channel,
                    $trigger,
                    $channel,
                    $payload,
                    $context,
                    $paket->getExternalOrderId(),
                    $paket->getSourceSystem(),
                );
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveAssignmentRule(string $trigger, Context $context): ?array
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('triggerKey', $trigger));
        $criteria->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING));

        /** @var TaskAssignmentRuleEntity|null $rule */
        $rule = $this->taskAssignmentRuleRepository->search($criteria, $context)->first();
        if ($rule === null) {
            return null;
        }

        return [
            'id' => $rule->getUniqueIdentifier(),
            'name' => $rule->getName(),
            'ruleId' => $rule->getRuleId(),
            'assigneeType' => $rule->getAssigneeType(),
            'assigneeIdentifier' => $rule->getAssigneeIdentifier(),
            'conditions' => $rule->getConditions(),
        ];
    }

    private function nextBusinessDay(\DateTimeImmutable $start): \DateTimeImmutable
    {
        $date = $start->setTime(0, 0)->modify('+1 day');

        while (in_array((int) $date->format('N'), [6, 7], true)) {
            $date = $date->modify('+1 day');
        }

        return $date;
    }
}
