<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Reliability;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

class DeadLetterService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @param array<string,mixed> $payload */
    public function add(string $system, string $operation, string $errorMessage, int $attempts, ?string $correlationId, array $payload = []): void
    {
        $this->connection->insert('lieferzeiten_dead_letter', [
            'id' => Uuid::fromHexToBytes(Uuid::randomHex()),
            'system' => $system,
            'operation' => $operation,
            'error_message' => $errorMessage,
            'attempts' => $attempts,
            'correlation_id' => $correlationId,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }
}
