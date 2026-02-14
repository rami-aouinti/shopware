<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use LieferzeitenAdmin\Service\LieferzeitenPositionWriteService;
use LieferzeitenAdmin\Service\LieferzeitenTaskService;
use LieferzeitenAdmin\Service\Notification\NotificationEventService;
use LieferzeitenAdmin\Service\WriteEndpointConflictException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class LieferzeitenPositionWriteServiceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['url' => 'sqlite:///:memory:']);
        $this->connection->executeStatement('CREATE TABLE lieferzeiten_position (
            id BLOB PRIMARY KEY,
            comment TEXT NULL,
            current_comment TEXT NULL,
            last_changed_by TEXT NULL,
            last_changed_at TEXT NULL,
            updated_at TEXT NOT NULL
        )');
        $this->connection->executeStatement('CREATE TABLE lieferzeiten_liefertermin_lieferant_history (
            position_id BLOB NOT NULL,
            liefertermin_from TEXT NULL,
            liefertermin_to TEXT NULL,
            liefertermin TEXT NULL,
            created_at TEXT NULL
        )');
        $this->connection->executeStatement('CREATE TABLE lieferzeiten_neuer_liefertermin_history (
            position_id BLOB NOT NULL,
            liefertermin_from TEXT NULL,
            liefertermin_to TEXT NULL,
            liefertermin TEXT NULL,
            created_at TEXT NULL
        )');
    }

    public function testUpdateCommentWithTwoUsersDetectsOptimisticConflictAndReturnsRefreshData(): void
    {
        $positionId = '5ef25f3bd7094ee68d5943dba5d9ca30';
        $initialUpdatedAt = '2026-02-10 09:00:00.000';

        $this->connection->insert('lieferzeiten_position', [
            'id' => hex2bin($positionId),
            'comment' => 'Initial comment',
            'current_comment' => 'Initial comment',
            'last_changed_by' => 'user-initial',
            'last_changed_at' => $initialUpdatedAt,
            'updated_at' => $initialUpdatedAt,
        ]);

        $positionRepository = $this->createPositionRepositoryMock();
        $service = new LieferzeitenPositionWriteService(
            $positionRepository,
            $this->createMock(EntityRepository::class),
            $this->connection,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(LieferzeitenTaskService::class),
            $this->createMock(NotificationEventService::class),
        );

        $contextUserA = Context::createDefaultContext();
        $contextUserB = Context::createDefaultContext();

        $service->updateComment($positionId, 'Comment from user A', $initialUpdatedAt, $contextUserA);

        $this->expectException(WriteEndpointConflictException::class);
        try {
            $service->updateComment($positionId, 'Comment from user B', $initialUpdatedAt, $contextUserB);
        } catch (WriteEndpointConflictException $e) {
            $refresh = $e->getRefresh();
            static::assertTrue(($refresh['exists'] ?? false) === true);
            static::assertSame('Comment from user A', $refresh['comment'] ?? null);
            static::assertSame('system', $refresh['lastChangedBy'] ?? null);
            static::assertIsString($refresh['updatedAt'] ?? null);

            throw $e;
        }
    }

    /** @return EntityRepository&MockObject */
    private function createPositionRepositoryMock(): EntityRepository
    {
        $repository = $this->createMock(EntityRepository::class);

        $repository->method('upsert')->willReturnCallback(function (array $payloads): void {
            foreach ($payloads as $payload) {
                $updatedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
                $this->connection->executeStatement(
                    'UPDATE lieferzeiten_position
                     SET comment = :comment,
                         current_comment = :currentComment,
                         last_changed_by = :lastChangedBy,
                         last_changed_at = :lastChangedAt,
                         updated_at = :updatedAt
                     WHERE id = :id',
                    [
                        'comment' => $payload['comment'] ?? null,
                        'currentComment' => $payload['currentComment'] ?? null,
                        'lastChangedBy' => $payload['lastChangedBy'] ?? null,
                        'lastChangedAt' => ($payload['lastChangedAt'] ?? new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
                        'updatedAt' => $updatedAt,
                        'id' => hex2bin((string) $payload['id']),
                    ],
                );
            }
        });

        return $repository;
    }
}
