<?php declare(strict_types=1);

namespace ExternalOrders\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class ExternalOrderTestDataService
{
    public function __construct(
        private readonly EntityRepository $externalOrderRepository,
        private readonly FakeExternalOrderProvider $fakeExternalOrderProvider,
    ) {
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
