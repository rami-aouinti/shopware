<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service;

use LieferzeitenManagement\Core\Content\OrderPosition\LieferzeitenOrderPositionDefinition;
use LieferzeitenManagement\Core\Content\Package\LieferzeitenPackageDefinition;
use LieferzeitenManagement\Core\Content\PackagePosition\LieferzeitenPackagePositionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class OrderPositionSyncService
{
    /**
     * @param EntityRepository<OrderDefinition> $orderRepository
     * @param EntityRepository<LieferzeitenOrderPositionDefinition> $orderPositionRepository
     * @param EntityRepository<LieferzeitenPackageDefinition> $packageRepository
     * @param EntityRepository<LieferzeitenPackagePositionDefinition> $packagePositionRepository
     */
    public function __construct(
        private readonly San6Client $san6Client,
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $orderPositionRepository,
        private readonly EntityRepository $packageRepository,
        private readonly EntityRepository $packagePositionRepository
    ) {
    }

    public function sync(Context $context): void
    {
        $san6Data = $this->indexSan6Positions($this->san6Client->fetchOrderPositions());

        $criteria = new Criteria();
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('orderCustomer');

        $orders = $this->orderRepository->search($criteria, $context);

        if ($orders->count() === 0) {
            return;
        }

        $lineItemIds = $this->collectLineItemIds($orders->getElements());

        if ($lineItemIds === []) {
            return;
        }

        $existingOrderPositions = $this->fetchExistingOrderPositions($lineItemIds, $context);

        $orderPositionPayloads = [];
        $orderPositionIdsByLineItemId = [];
        $orderPositionIdsBySan6Position = [];

        foreach ($orders as $order) {
            if ($this->isTestOrder($order)) {
                continue;
            }

            foreach ($order->getLineItems() ?? [] as $lineItem) {
                if (!$lineItem->getId()) {
                    continue;
                }

                $lineItemId = $lineItem->getId();
                $san6Position = $san6Data['byLineItemId'][$lineItemId] ?? null;
                $orderPositionId = $existingOrderPositions[$lineItemId] ?? Uuid::randomHex();

                $orderPositionPayloads[] = [
                    'id' => $orderPositionId,
                    'orderId' => $order->getId(),
                    'orderLineItemId' => $lineItemId,
                    'san6OrderNumber' => $san6Position['san6OrderNumber'] ?? null,
                    'san6PositionNumber' => $san6Position['san6PositionNumber'] ?? null,
                    'quantity' => $san6Position['quantity'] ?? $lineItem->getQuantity(),
                ];

                $orderPositionIdsByLineItemId[$lineItemId] = $orderPositionId;

                if (!empty($san6Position['san6PositionNumber'])) {
                    $orderPositionIdsBySan6Position[$order->getId() . '|' . $san6Position['san6PositionNumber']] = $orderPositionId;
                }
            }
        }

        if ($orderPositionPayloads !== []) {
            $this->orderPositionRepository->upsert($orderPositionPayloads, $context);
        }

        $packagePositionPayloads = $this->buildPackagePositionPayloads(
            $san6Data['packagePositions'],
            $orderPositionIdsByLineItemId,
            $orderPositionIdsBySan6Position,
            $context
        );

        if ($packagePositionPayloads !== []) {
            $this->packagePositionRepository->upsert($packagePositionPayloads, $context);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $san6Positions
     *
     * @return array{
     *     byLineItemId: array<string, array<string, mixed>>,
     *     packagePositions: array<int, array<string, mixed>>
     * }
     */
    private function indexSan6Positions(array $san6Positions): array
    {
        $byLineItemId = [];
        $packagePositions = [];

        foreach ($san6Positions as $position) {
            $lineItemId = $position['orderLineItemId'] ?? $position['lineItemId'] ?? null;

            if (is_string($lineItemId) && $lineItemId !== '') {
                $byLineItemId[$lineItemId] = $position;
            }

            $packages = $position['packagePositions'] ?? $position['packages'] ?? null;
            if (is_array($packages)) {
                foreach ($packages as $package) {
                    if (!is_array($package)) {
                        continue;
                    }

                    $packagePositions[] = [
                        'orderLineItemId' => $lineItemId,
                        'orderId' => $position['orderId'] ?? null,
                        'san6PositionNumber' => $position['san6PositionNumber'] ?? null,
                        'quantity' => $package['quantity'] ?? $package['shippedQuantity'] ?? $position['shippedQuantity'] ?? $position['quantity'] ?? null,
                        'splitType' => $package['splitType'] ?? $position['splitType'] ?? null,
                        'packageId' => $package['packageId'] ?? null,
                        'san6PackageNumber' => $package['san6PackageNumber'] ?? null,
                    ];
                }

                continue;
            }

            if (!empty($position['packageId']) || !empty($position['san6PackageNumber'])) {
                $packagePositions[] = [
                    'orderLineItemId' => $lineItemId,
                    'orderId' => $position['orderId'] ?? null,
                    'san6PositionNumber' => $position['san6PositionNumber'] ?? null,
                    'quantity' => $position['shippedQuantity'] ?? $position['quantity'] ?? null,
                    'splitType' => $position['splitType'] ?? null,
                    'packageId' => $position['packageId'] ?? null,
                    'san6PackageNumber' => $position['san6PackageNumber'] ?? null,
                ];
            }
        }

        return [
            'byLineItemId' => $byLineItemId,
            'packagePositions' => $packagePositions,
        ];
    }

    /**
     * @param OrderEntity[] $orders
     *
     * @return string[]
     */
    private function collectLineItemIds(array $orders): array
    {
        $lineItemIds = [];

        foreach ($orders as $order) {
            if ($this->isTestOrder($order)) {
                continue;
            }

            foreach ($order->getLineItems() ?? [] as $lineItem) {
                if (!$lineItem->getId()) {
                    continue;
                }

                $lineItemIds[] = $lineItem->getId();
            }
        }

        return array_values(array_unique($lineItemIds));
    }

    /**
     * @param string[] $lineItemIds
     *
     * @return array<string, string>
     */
    private function fetchExistingOrderPositions(array $lineItemIds, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('orderLineItemId', $lineItemIds));

        $existing = $this->orderPositionRepository->search($criteria, $context);

        $positionsByLineItemId = [];
        foreach ($existing as $position) {
            if (!$position->getOrderLineItemId() || !$position->getId()) {
                continue;
            }

            $positionsByLineItemId[$position->getOrderLineItemId()] = $position->getId();
        }

        return $positionsByLineItemId;
    }

    /**
     * @param array<int, array<string, mixed>> $packagePositions
     * @param array<string, string> $orderPositionIdsByLineItemId
     * @param array<string, string> $orderPositionIdsBySan6Position
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPackagePositionPayloads(
        array $packagePositions,
        array $orderPositionIdsByLineItemId,
        array $orderPositionIdsBySan6Position,
        Context $context
    ): array {
        if ($packagePositions === []) {
            return [];
        }

        $packageNumbers = [];
        foreach ($packagePositions as $packagePosition) {
            if (!empty($packagePosition['san6PackageNumber'])) {
                $packageNumbers[] = $packagePosition['san6PackageNumber'];
            }
        }

        $packageIdsByNumber = $this->loadPackageIdsBySan6Number($packageNumbers, $context);

        $payloads = [];
        $packageIds = [];
        $orderPositionIds = [];

        foreach ($packagePositions as $packagePosition) {
            $orderPositionId = null;

            if (!empty($packagePosition['orderLineItemId']) && isset($orderPositionIdsByLineItemId[$packagePosition['orderLineItemId']])) {
                $orderPositionId = $orderPositionIdsByLineItemId[$packagePosition['orderLineItemId']];
            } elseif (!empty($packagePosition['orderId']) && !empty($packagePosition['san6PositionNumber'])) {
                $key = $packagePosition['orderId'] . '|' . $packagePosition['san6PositionNumber'];
                $orderPositionId = $orderPositionIdsBySan6Position[$key] ?? null;
            }

            if (!$orderPositionId) {
                continue;
            }

            $packageId = $packagePosition['packageId'] ?? null;

            if (!$packageId && !empty($packagePosition['san6PackageNumber'])) {
                $packageId = $packageIdsByNumber[$packagePosition['san6PackageNumber']] ?? null;
            }

            if (!$packageId) {
                continue;
            }

            $packageIds[] = $packageId;
            $orderPositionIds[] = $orderPositionId;
            $payloads[] = [
                'orderPositionId' => $orderPositionId,
                'packageId' => $packageId,
                'quantity' => $packagePosition['quantity'] ?? null,
                'splitType' => $packagePosition['splitType'] ?? null,
            ];
        }

        if ($payloads === []) {
            return [];
        }

        $existingPackagePositions = $this->fetchExistingPackagePositions($packageIds, $orderPositionIds, $context);

        foreach ($payloads as $index => $payload) {
            $key = $payload['packageId'] . '|' . $payload['orderPositionId'];
            $payloads[$index]['id'] = $existingPackagePositions[$key] ?? Uuid::randomHex();
        }

        return $payloads;
    }

    /**
     * @param string[] $packageNumbers
     *
     * @return array<string, string>
     */
    private function loadPackageIdsBySan6Number(array $packageNumbers, Context $context): array
    {
        $packageNumbers = array_values(array_unique(array_filter($packageNumbers)));
        if ($packageNumbers === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('san6PackageNumber', $packageNumbers));

        $packages = $this->packageRepository->search($criteria, $context);

        $result = [];
        foreach ($packages as $package) {
            if (!$package->getSan6PackageNumber() || !$package->getId()) {
                continue;
            }

            $result[$package->getSan6PackageNumber()] = $package->getId();
        }

        return $result;
    }

    /**
     * @param string[] $packageIds
     * @param string[] $orderPositionIds
     *
     * @return array<string, string>
     */
    private function fetchExistingPackagePositions(array $packageIds, array $orderPositionIds, Context $context): array
    {
        $packageIds = array_values(array_unique(array_filter($packageIds)));
        $orderPositionIds = array_values(array_unique(array_filter($orderPositionIds)));

        if ($packageIds === [] || $orderPositionIds === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('packageId', $packageIds));
        $criteria->addFilter(new EqualsAnyFilter('orderPositionId', $orderPositionIds));

        $existing = $this->packagePositionRepository->search($criteria, $context);

        $result = [];
        foreach ($existing as $packagePosition) {
            if (!$packagePosition->getPackageId() || !$packagePosition->getOrderPositionId() || !$packagePosition->getId()) {
                continue;
            }

            $result[$packagePosition->getPackageId() . '|' . $packagePosition->getOrderPositionId()] = $packagePosition->getId();
        }

        return $result;
    }

    private function isTestOrder(OrderEntity $order): bool
    {
        $orderNumber = strtoupper((string) $order->getOrderNumber());
        $customerEmail = strtolower((string) $order->getOrderCustomer()?->getEmail());

        return str_contains($orderNumber, 'TEST') || str_contains($customerEmail, 'test');
    }
}
