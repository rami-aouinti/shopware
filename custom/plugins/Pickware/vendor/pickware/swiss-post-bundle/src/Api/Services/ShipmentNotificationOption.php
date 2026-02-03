<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SwissPostBundle\Api\Services;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ShipmentNotificationOption extends AbstractShipmentOption
{
    private function __construct(
        private readonly array $notificationPayload,
    ) {}

    /**
     * @param int[] $eventValues
     */
    public static function notifications(array $eventValues, string $email): array
    {
        $notifications = [];
        foreach ($eventValues as $eventValue) {
            $notifications[] = new self([
                'communication' => [
                    'email' => $email,
                ],
                'service' => $eventValue,
                'language' => 'DE',
                'type' => 'EMAIL',
            ]);
        }

        return $notifications;
    }

    public function applyToShipmentArray(array &$shipmentArray): void
    {
        $shipmentArray['item']['notification'][] = $this->notificationPayload;
    }
}
