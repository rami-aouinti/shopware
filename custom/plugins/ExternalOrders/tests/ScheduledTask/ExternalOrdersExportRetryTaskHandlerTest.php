<?php declare(strict_types=1);

namespace ExternalOrders\Tests\ScheduledTask;

use ExternalOrders\ScheduledTask\ExternalOrdersExportRetryTask;
use ExternalOrders\ScheduledTask\ExternalOrdersExportRetryTaskHandler;
use ExternalOrders\Service\TopmSan6OrderExportService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

class ExternalOrdersExportRetryTaskHandlerTest extends TestCase
{
    public function testRunProcessesRetriesOnceWithDefaultContext(): void
    {
        $exportService = $this->createMock(TopmSan6OrderExportService::class);
        $exportService->expects(static::once())
            ->method('processRetries')
            ->with(static::callback(function (Context $context): bool {
                static::assertSame(Context::createDefaultContext()->getVersionId(), $context->getVersionId());

                return true;
            }));

        $handler = new ExternalOrdersExportRetryTaskHandler($exportService);

        $handler->run();
    }

    public function testGetHandledMessagesReturnsRetryTaskClass(): void
    {
        static::assertSame([ExternalOrdersExportRetryTask::class], ExternalOrdersExportRetryTaskHandler::getHandledMessages());
    }
}
