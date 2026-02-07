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
class ShipmentServiceOption extends AbstractShipmentOption
{
    private function __construct(
        private readonly string $serviceCode,
        private readonly ?float $serviceValue = null,
        private readonly ?string $serviceCurrency = null,
    ) {}

    public static function saturdayDelivery(): self
    {
        return new self('AA');
    }

    public static function paperlessTrade(): self
    {
        return new self('WY');
    }

    public static function additionalInsurance(float $amount, string $currency): self
    {
        return new self('II', $amount, $currency);
    }

    public static function returnService(): self
    {
        return new self('PT');
    }

    /**
     * @param array{
     *     valueAddedServices?: list<array{
     *          serviceCode: string,
     *          value?: float,
     *          currency?: string
     *      }>
     * } $shipmentArray
     */
    public function applyToShipmentArray(array &$shipmentArray): void
    {
        $service = [
            'serviceCode' => $this->serviceCode,
        ];

        if ($this->serviceValue && $this->serviceCurrency) {
            $service['value'] = $this->serviceValue;
            $service['currency'] = $this->serviceCurrency;
        }

        $shipmentArray['valueAddedServices'] ??= [];

        $shipmentArray['valueAddedServices'][] = $service;
    }
}
