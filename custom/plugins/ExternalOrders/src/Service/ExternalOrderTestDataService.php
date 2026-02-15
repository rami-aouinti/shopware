<?php declare(strict_types=1);

namespace ExternalOrders\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class ExternalOrderTestDataService
{
    public function __construct(
        private readonly EntityRepository $externalOrderRepository,
        private readonly FakeExternalOrderProvider $fakeExternalOrderProvider,
    ) {
    }

    public function hasSeededFakeOrders(Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter($this->buildDemoExternalIdFilter());
        $criteria->setLimit(1);

        return $this->externalOrderRepository->search($criteria, $context)->getTotal() > 0;
    }

    public function removeSeededFakeOrders(Context $context): int
    {
        $criteria = new Criteria();
        $criteria->addFilter($this->buildDemoExternalIdFilter());
        $criteria->setLimit(5000);

        $result = $this->externalOrderRepository->search($criteria, $context);
        $deletePayload = [];

        foreach ($result->getEntities() as $entity) {
            $deletePayload[] = ['id' => $entity->getId()];
        }

        if ($deletePayload === []) {
            return 0;
        }

        $this->externalOrderRepository->delete($deletePayload, $context);

        return count($deletePayload);
    }

    public function seedFakeOrdersOnce(Context $context): int
    {
        $payloads = $this->fakeExternalOrderProvider->getSeedPayloads();
        if ($payloads === []) {
            return 0;
        }

        $externalIds = [];
        foreach ($payloads as $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $externalId = $this->resolveExternalId($payload);
            if ($externalId !== null) {
                $externalIds[] = $externalId;
            }
        }

        $externalIds = array_values(array_unique($externalIds));
        if ($externalIds === []) {
            return 0;
        }

        $existingIds = $this->fetchExistingIds($externalIds, $context);
        $upsertPayload = [];

        foreach ($payloads as $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $externalId = $this->resolveExternalId($payload);
            if ($externalId === null || isset($existingIds[$externalId])) {
                continue;
            }

            $upsertPayload[] = [
                'id' => Uuid::randomHex(),
                'externalId' => $externalId,
                'payload' => $payload,
            ];
        }

        if ($upsertPayload === []) {
            return 0;
        }

        $this->externalOrderRepository->upsert($upsertPayload, $context);

        return count($upsertPayload);
    }

    /**
     * @return array<int, string>
     */
    public function getDemoExternalOrderIds(): array
    {
        $payloads = $this->fakeExternalOrderProvider->getSeedPayloads();
        $externalIds = [];

        foreach ($payloads as $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $externalId = $this->resolveExternalId($payload);
            if ($externalId === null) {
                continue;
            }

            $externalIds[] = $externalId;
        }

        return array_values(array_unique($externalIds));
    }


    private function buildDemoExternalIdFilter(): MultiFilter
    {
        return new MultiFilter(MultiFilter::CONNECTION_OR, [
            new PrefixFilter('externalId', FakeExternalOrderProvider::DEMO_ORDER_PREFIX),
            new PrefixFilter('externalId', 'fake-'),
        ]);
    }

    /**
     * @param array<int, string> $externalIds
     * @return array<string, string>
     */
    private function fetchExistingIds(array $externalIds, Context $context): array
    {
        $mapping = [];
        $chunkSize = 500;

        foreach (array_chunk($externalIds, $chunkSize) as $chunk) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsAnyFilter('externalId', $chunk));
            $criteria->setLimit(count($chunk));

            $result = $this->externalOrderRepository->search($criteria, $context);

            foreach ($result->getEntities() as $entity) {
                $mapping[$entity->getExternalId()] = $entity->getId();
            }
        }

        return $mapping;
    }

    /**
     * @param array<mixed> $order
     */
    private function resolveExternalId(array $order): ?string
    {
        $externalId = $order['externalId'] ?? $order['id'] ?? $order['orderNumber'] ?? null;

        if (!is_string($externalId) || $externalId === '') {
            return null;
        }

        return $externalId;
    }
}
