<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use LieferzeitenAdmin\Service\Notification\NotificationTriggerCatalog;
use PHPUnit\Framework\TestCase;

class NotificationTriggerCatalogTest extends TestCase
{
    public function testChannelsOnlyContainEmail(): void
    {
        static::assertSame(['email'], NotificationTriggerCatalog::channels());
    }
}
