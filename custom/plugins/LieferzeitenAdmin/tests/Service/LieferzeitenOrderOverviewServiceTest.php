<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use Doctrine\DBAL\Connection;
use LieferzeitenAdmin\Service\LieferzeitenOrderOverviewService;
use PHPUnit\Framework\TestCase;

class LieferzeitenOrderOverviewServiceTest extends TestCase
{
    public function testListOrdersExcludesTestOrdersInWhereClause(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, 'COALESCE(p.is_test_order, 0) = 0')),
                $this->anything()
            )
            ->willReturn('0');

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, 'COALESCE(p.is_test_order, 0) = 0')),
                $this->anything()
            )
            ->willReturn([]);

        $service = new LieferzeitenOrderOverviewService($connection);

        $result = $service->listOrders();

        static::assertSame(0, $result['total']);
    }
}
