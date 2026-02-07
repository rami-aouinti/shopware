<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service\Status;

use LieferzeitenManagement\Core\Content\SyncLog\LieferzeitenSyncLogDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class StatusSyncBackService
{
    /**
     * @param EntityRepository<OrderDefinition> $orderRepository
     * @param EntityRepository<LieferzeitenSyncLogDefinition> $syncLogRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $syncLogRepository,
        private readonly StateMachineRegistry $stateMachineRegistry,
    ) {
    }

    public function syncBackForOrderId(string $orderId, int $statusCode, string $source, Context $context): void
    {
        if (!in_array($statusCode, [StatusResolver::STATUS_SHIPPED, StatusResolver::STATUS_COMPLETED], true)) {
            return;
        }

        if ($this->hasAlreadySynced($orderId, $statusCode, $source, $context)) {
            return;
        }

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociations([
            'deliveries.stateMachineState',
            'stateMachineState',
        ]);

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context)->first();
        if (!$order) {
            return;
        }

        $synced = false;

        if ($statusCode === StatusResolver::STATUS_SHIPPED) {
            $delivery = $order->getDeliveries()?->first();
            if ($delivery !== null) {
                if ($delivery->getStateMachineState()?->getTechnicalName() !== 'shipped') {
                    $this->stateMachineRegistry->transition(
                        new Transition(
                            OrderDeliveryDefinition::ENTITY_NAME,
                            $delivery->getId(),
                            'ship',
                            'stateId'
                        ),
                        $context
                    );
                }
                $synced = true;
            }
        }

        if ($statusCode === StatusResolver::STATUS_COMPLETED) {
            if ($order->getStateMachineState()?->getTechnicalName() !== OrderStates::STATE_COMPLETED) {
                $this->stateMachineRegistry->transition(
                    new Transition(
                        OrderDefinition::ENTITY_NAME,
                        $order->getId(),
                        'complete',
                        'stateId'
                    ),
                    $context
                );
            }
            $synced = true;
        }

        if ($synced) {
            $this->syncLogRepository->create([[
                'orderId' => $orderId,
                'statusCode' => $statusCode,
                'source' => $source,
            ]], $context);
        }
    }

    private function hasAlreadySynced(string $orderId, int $statusCode, string $source, Context $context): bool
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('orderId', $orderId))
            ->addFilter(new EqualsFilter('statusCode', $statusCode))
            ->addFilter(new EqualsFilter('source', $source))
            ->setLimit(1);

        return $this->syncLogRepository->search($criteria, $context)->getTotal() > 0;
    }
}
