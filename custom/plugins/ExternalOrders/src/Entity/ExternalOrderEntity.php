<?php declare(strict_types=1);

namespace ExternalOrders\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ExternalOrderEntity extends Entity
{
    use EntityIdTrait;

    protected string $externalId;

    /** @var array<mixed> */
    protected array $payload;

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): void
    {
        $this->externalId = $externalId;
    }

    /** @return array<mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** @param array<mixed> $payload */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }
}
