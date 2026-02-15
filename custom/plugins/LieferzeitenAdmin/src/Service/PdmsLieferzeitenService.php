<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

class PdmsLieferzeitenService
{
    public function __construct(
        private readonly PdmsLieferzeitenClient $pdmsClient,
    ) {
    }

    /**
     * @return list<array{id:string,name:string,minDays:?int,maxDays:?int,raw:array<string,mixed>}>
     */
    public function getNormalizedLieferzeiten(): array
    {
        $rows = $this->pdmsClient->fetchLieferzeiten();

        $normalized = [];
        foreach ($rows as $row) {
            $normalizedRow = $this->normalizeRow($row);
            if ($normalizedRow === null) {
                continue;
            }

            $normalized[] = $normalizedRow;

            if (\count($normalized) >= 4) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $row
     *
     * @return array{id:string,name:string,minDays:?int,maxDays:?int,raw:array<string,mixed>}|null
     */
    private function normalizeRow(array $row): ?array
    {
        $idCandidates = [
            $row['id'] ?? null,
            $row['uuid'] ?? null,
            $row['code'] ?? null,
            $row['key'] ?? null,
        ];

        $nameCandidates = [
            $row['name'] ?? null,
            $row['label'] ?? null,
            $row['title'] ?? null,
            $row['lieferzeit'] ?? null,
            $row['deliveryTime'] ?? null,
        ];

        $id = $this->firstNonEmptyString($idCandidates);
        $name = $this->firstNonEmptyString($nameCandidates);

        if ($id === null && $name === null) {
            return null;
        }

        return [
            'id' => $id ?? strtolower(preg_replace('/\s+/', '-', $name ?? '') ?: uniqid('pdms-', false)),
            'name' => $name ?? sprintf('%s-%s', $this->normalizeInt($row['minDays'] ?? null) ?? '?', $this->normalizeInt($row['maxDays'] ?? null) ?? '?'),
            'minDays' => $this->normalizeInt($row['minDays'] ?? $row['min_days'] ?? $row['from'] ?? null),
            'maxDays' => $this->normalizeInt($row['maxDays'] ?? $row['max_days'] ?? $row['to'] ?? null),
            'raw' => $row,
        ];
    }

    /**
     * @param list<mixed> $values
     */
    private function firstNonEmptyString(array $values): ?string
    {
        foreach ($values as $value) {
            if (!\is_scalar($value)) {
                continue;
            }

            $normalized = trim((string) $value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeInt(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && $value !== '' && is_numeric($value)) {
            return (int) $value;
        }

        if (\is_float($value)) {
            return (int) $value;
        }

        return null;
    }
}

