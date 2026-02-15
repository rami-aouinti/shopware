<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class PdmsLieferzeitenMappingService
{
    public function __construct(
        private readonly PdmsLieferzeitenService $pdmsLieferzeitenService,
        private readonly EntityRepository $salesChannelRepository,
    ) {
    }

    /**
     * @return array{salesChannelId:string,salesChannelName:string,lieferzeiten:list<array<string,mixed>>}
     */
    public function getForSalesChannel(string $salesChannelId, Context $context): array
    {
        $criteria = new Criteria([$salesChannelId]);
        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->first();

        $salesChannelName = $salesChannel?->get('name');
        if (!is_string($salesChannelName) || $salesChannelName === '') {
            $salesChannelName = $salesChannelId;
        }

        $lieferzeiten = $this->pdmsLieferzeitenService->getNormalizedLieferzeiten();

        $mapping = json_decode((string) ($salesChannel?->get('customFields')['pdms_lieferzeiten_mapping'] ?? ''), true);
        if (!is_array($mapping)) {
            $mapping = [];
        }

        $mapped = [];
        for ($slot = 1; $slot <= 4; ++$slot) {
            $mappingValue = $mapping[(string) $slot] ?? $mapping['slot' . $slot] ?? null;
            $matched = $this->matchLieferzeit($lieferzeiten, $mappingValue, $slot);

            $mapped[] = [
                'slot' => $slot,
                'mapping' => $mappingValue,
                'lieferzeit' => $matched,
            ];
        }

        return [
            'salesChannelId' => $salesChannelId,
            'salesChannelName' => $salesChannelName,
            'lieferzeiten' => $mapped,
        ];
    }

    /**
     * @param list<array<string,mixed>> $lieferzeiten
     */
    private function matchLieferzeit(array $lieferzeiten, mixed $mappingValue, int $slot): ?array
    {
        if (is_string($mappingValue) && $mappingValue !== '') {
            foreach ($lieferzeiten as $candidate) {
                $id = $candidate['id'] ?? null;
                if ((string) $id === $mappingValue) {
                    return $candidate;
                }
            }
        }

        if (isset($lieferzeiten[$slot - 1]) && is_array($lieferzeiten[$slot - 1])) {
            return $lieferzeiten[$slot - 1];
        }

        return null;
    }
}
