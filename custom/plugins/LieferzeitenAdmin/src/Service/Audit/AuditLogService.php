<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Audit;

use Doctrine\DBAL\Connection;
use LieferzeitenAdmin\Service\CorrelationIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class AuditLogService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CorrelationIdProvider $correlationIdProvider,
    ) {
    }

    /** @param array<string,mixed> $payload */
    public function log(
        string $action,
        string $targetType,
        ?string $targetId,
        ?Context $context,
        array $payload = [],
        ?string $sourceSystem = null,
        ?string $userId = null,
        ?string $correlationId = null,
    ): void {
        $source = $context?->getSource();
        $resolvedUserId = $userId;
        if ($resolvedUserId === null && $source !== null && method_exists($source, 'getUserId')) {
            $resolvedUserId = $source->getUserId();
        }

        $this->connection->insert('lieferzeiten_audit_log', [
            'id' => Uuid::fromHexToBytes(Uuid::randomHex()),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'source_system' => $sourceSystem,
            'user_id' => $resolvedUserId,
            'correlation_id' => $correlationId ?? $this->correlationIdProvider->current($context),
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }
}
