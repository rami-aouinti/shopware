<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Api\Services;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ShipmentNotificationOption extends AbstractShipmentOption
{
    private function __construct(
        private readonly string $email,
        private readonly string $countryCode,
    ) {}

    public static function dispatchNotificationOption(string $email, string $countryCode): self
    {
        return new self($email, $countryCode);
    }

    /**
     * @param array{
     *     shipmentNotification?: list<array{
     *          typeCode: 'email',
     *          receiverId: string,
     *          languageCountryCode: string
     *      }>
     * } $shipmentArray
     */
    public function applyToShipmentArray(array &$shipmentArray): void
    {
        $shipmentArray['shipmentNotification'] = [
            [
                'typeCode' => 'email',
                'receiverId' => $this->email,
                'languageCountryCode' => $this->countryCode,
            ],
        ];
    }
}
