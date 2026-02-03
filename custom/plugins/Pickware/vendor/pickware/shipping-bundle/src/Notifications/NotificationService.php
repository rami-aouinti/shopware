<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Notifications;

class NotificationService
{
    /**
     * @var Notification[][]
     */
    private array $notificationsStack = [];

    /**
     * @return Notification[]
     */
    public function collectNotificationsInCallback(callable $callback): array
    {
        array_unshift($this->notificationsStack, []);
        $callback();

        return array_shift($this->notificationsStack);
    }

    public function emit(Notification $notification): void
    {
        foreach ($this->notificationsStack as &$notifications) {
            $notifications[] = $notification;
        }
    }
}
