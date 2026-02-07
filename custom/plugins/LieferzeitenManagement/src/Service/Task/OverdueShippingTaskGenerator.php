<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service\Task;

use LieferzeitenManagement\Core\Content\Package\LieferzeitenPackageDefinition;
use LieferzeitenManagement\Core\Content\Task\LieferzeitenTaskDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class OverdueShippingTaskGenerator
{
    public const TASK_TYPE = 'shipping_overdue';

    /**
     * @param EntityRepository<LieferzeitenPackageDefinition> $packageRepository
     * @param EntityRepository<LieferzeitenTaskDefinition> $taskRepository
     * @param EntityRepository<OrderDefinition> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $packageRepository,
        private readonly EntityRepository $taskRepository,
        private readonly EntityRepository $orderRepository,
        private readonly TaskAssignmentResolver $taskAssignmentResolver
    ) {
    }

    public function generate(Context $context, ?string $area = null): int
    {
        $now = new \DateTimeImmutable();
        $noonCutoff = $now->setTime(12, 0);

        if ($now < $noonCutoff) {
            return 0;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('latestShippingAt', [RangeFilter::LTE => $now->format(DATE_ATOM)]));
        $criteria->addFilter(new EqualsFilter('shippedAt', null));
        $criteria->addAssociation('order');
        $criteria->addAssociation('order.orderCustomer');

        $packages = $this->packageRepository->search($criteria, $context);

        $created = 0;
        foreach ($packages as $package) {
            $orderId = $package->getOrderId();

            if (!$orderId) {
                continue;
            }

            if ($this->taskExists($orderId, $package->getId(), $context)) {
                continue;
            }

            if ($this->isTestOrder($package->getOrder())) {
                continue;
            }

            $salesChannelId = $package->getOrder()?->getSalesChannelId();
            if (!$salesChannelId) {
                $order = $this->orderRepository->search(new Criteria([$orderId]), $context)->first();
                $salesChannelId = $order?->getSalesChannelId();
            }

            $assignedUserId = $this->taskAssignmentResolver->resolveAssignedUserId(
                $salesChannelId,
                self::TASK_TYPE,
                $context,
                $area
            );

            $this->taskRepository->upsert([
                [
                    'id' => Uuid::randomHex(),
                    'orderId' => $orderId,
                    'packageId' => $package->getId(),
                    'type' => self::TASK_TYPE,
                    'status' => 'open',
                    'assignedUserId' => $assignedUserId,
                    'dueDate' => $this->nextBusinessDay($now)->format(DATE_ATOM),
                ],
            ], $context);

            $created++;
        }

        return $created;
    }

    private function isTestOrder(?\Shopware\Core\Checkout\Order\OrderEntity $order): bool
    {
        if (!$order) {
            return false;
        }

        $orderNumber = strtoupper((string) $order->getOrderNumber());
        $customerEmail = strtolower((string) $order->getOrderCustomer()?->getEmail());

        return str_contains($orderNumber, 'TEST') || str_contains($customerEmail, 'test');
    }

    private function taskExists(string $orderId, string $packageId, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addFilter(new EqualsFilter('packageId', $packageId));
        $criteria->addFilter(new EqualsFilter('type', self::TASK_TYPE));
        $criteria->addFilter(new EqualsFilter('status', 'open'));
        $criteria->setLimit(1);

        return $this->taskRepository->searchIds($criteria, $context)->getTotal() > 0;
    }

    private function nextBusinessDay(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $next = $date->modify('+1 day');
        while ((int) $next->format('N') >= 6) {
            $next = $next->modify('+1 day');
        }

        return $next;
    }
}
