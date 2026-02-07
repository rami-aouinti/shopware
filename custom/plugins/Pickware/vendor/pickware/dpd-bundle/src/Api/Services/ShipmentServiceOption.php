<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Api\Services;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ShipmentServiceOption
{
    private function __construct(
        private readonly string $serviceName,
        private readonly array|bool $servicePayload = true,
    ) {}

    public static function exWorksDelivery(): self
    {
        return new self('exWorksDelivery');
    }

    public static function food(): self
    {
        return new self('food');
    }

    public static function personalDelivery(string $type, string $name): self
    {
        return new self('personalDelivery', [
            'type' => $type,
            'name' => $name,
        ]);
    }

    public static function predict(string $email, string $language): self
    {
        return new self('predict', [
            'channel' => 1,
            'value' => $email,
            'language' => $language,
        ]);
    }

    /**
     * @param int[] $eventValues
     */
    public static function proactiveNotification(array $eventValues, string $email, string $language): self
    {
        return new self('proactiveNotification', [
            'channel' => 1,
            'value' => $email,
            'rule' => array_sum($eventValues),
            'language' => $language,
        ]);
    }

    public static function saturdayDelivery(): self
    {
        return new self('saturdayDelivery');
    }

    public function applyToShipmentArray(array &$shipmentArray): void
    {
        $shipmentArray['productAndServiceData'][$this->serviceName] = $this->servicePayload;
    }
}
