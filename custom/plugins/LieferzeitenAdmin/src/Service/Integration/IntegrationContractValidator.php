<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Integration;

class IntegrationContractValidator
{
    /** @var array<string,list<string>> */
    private const API_REQUIRED_FIELDS = [
        'shopware' => ['externalId|id|orderNumber', 'status', 'date|orderDate'],
        'gambio' => ['externalId|id|orderNumber', 'status', 'date|orderDate'],
        'san6' => ['orderNumber', 'shippingDate|deliveryDate'],
        'dhl' => ['trackingNumber', 'status', 'eventTime|timestamp'],
        'gls' => ['trackingNumber', 'status', 'eventTime|timestamp'],
    ];

    /** @var array<string,list<string>> */
    private const PERSISTENCE_REQUIRED_FIELDS = [
        'paket' => ['externalOrderId|externalId|orderNumber', 'paketNumber|packageNumber|orderNumber', 'sourceSystem'],
        'position' => ['positionNumber|orderNumber|externalId', 'status'],
        'tracking_history' => ['trackingNumber|sendenummer', 'status', 'eventTime|timestamp'],
    ];

    /**
     * @param array<string,mixed> $payload
     * @return list<string>
     */
    public function validateApiPayload(string $source, array $payload): array
    {
        $source = mb_strtolower($source);
        $requirements = self::API_REQUIRED_FIELDS[$source] ?? [];

        return $this->validateAgainstRequirements($payload, $requirements);
    }

    /**
     * @param array<string,mixed> $payload
     * @return list<string>
     */
    public function validatePersistencePayload(string $recordType, array $payload): array
    {
        $recordType = mb_strtolower($recordType);
        $requirements = self::PERSISTENCE_REQUIRED_FIELDS[$recordType] ?? [];

        return $this->validateAgainstRequirements($payload, $requirements);
    }

    /**
     * Priorité fonctionnelle par défaut pour les champs métier en conflit:
     * 1) san6, 2) tracking (DHL/GLS), 3) shop (Shopware/Gambio)
     */
    public function resolveValueByPriority(mixed $shopValue, mixed $trackingValue, mixed $san6Value): mixed
    {
        if ($this->isFilled($san6Value)) {
            return $san6Value;
        }

        if ($this->isFilled($trackingValue)) {
            return $trackingValue;
        }

        return $shopValue;
    }

    /**
     * @param array<string,mixed> $payload
     * @param list<string> $requirements
     * @return list<string>
     */
    private function validateAgainstRequirements(array $payload, array $requirements): array
    {
        $violations = [];

        foreach ($requirements as $requiredFieldSet) {
            $keys = array_map('trim', explode('|', $requiredFieldSet));
            $hasOne = false;

            foreach ($keys as $key) {
                if (!$this->isFilled($payload[$key] ?? null)) {
                    continue;
                }

                $hasOne = true;
                break;
            }

            if (!$hasOne) {
                $violations[] = sprintf('Missing required field: %s', $requiredFieldSet);
            }
        }

        return $violations;
    }

    private function isFilled(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }
}
