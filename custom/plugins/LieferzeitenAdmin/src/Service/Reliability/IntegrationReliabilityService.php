<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Reliability;

use LieferzeitenAdmin\Service\Audit\AuditLogService;
use LieferzeitenAdmin\Service\CorrelationIdProvider;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;

class IntegrationReliabilityService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly DeadLetterService $deadLetterService,
        private readonly CorrelationIdProvider $correlationIdProvider,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @template T
     * @param callable():T $operation
     * @param array<string,mixed> $payload
     * @return T
     */
    public function executeWithRetry(string $system, string $name, callable $operation, ?Context $context = null, int $maxAttempts = 3, array $payload = []): mixed
    {
        $correlationId = $this->correlationIdProvider->current($context);
        $attempt = 0;

        while (true) {
            ++$attempt;
            try {
                $this->logger->info('Integration call started.', [
                    'system' => $system,
                    'operation' => $name,
                    'attempt' => $attempt,
                    'correlationId' => $correlationId,
                ]);

                $result = $operation();

                $this->logger->info('Integration call succeeded.', [
                    'system' => $system,
                    'operation' => $name,
                    'attempt' => $attempt,
                    'correlationId' => $correlationId,
                ]);

                $this->auditLogService->log(
                    action: 'integration_success',
                    targetType: $system,
                    targetId: $name,
                    context: $context,
                    payload: ['attempt' => $attempt],
                    sourceSystem: $system,
                    correlationId: $correlationId,
                );

                return $result;
            } catch (\Throwable $e) {
                $this->logger->warning('Integration call failed.', [
                    'system' => $system,
                    'operation' => $name,
                    'attempt' => $attempt,
                    'maxAttempts' => $maxAttempts,
                    'correlationId' => $correlationId,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt >= $maxAttempts) {
                    $this->deadLetterService->add($system, $name, $e->getMessage(), $attempt, $correlationId, $payload);
                    $this->auditLogService->log(
                        action: 'integration_dead_letter',
                        targetType: $system,
                        targetId: $name,
                        context: $context,
                        payload: ['attempts' => $attempt, 'error' => $e->getMessage()],
                        sourceSystem: $system,
                        correlationId: $correlationId,
                    );

                    throw $e;
                }

                usleep($attempt * 200000);
            }
        }
    }
}
