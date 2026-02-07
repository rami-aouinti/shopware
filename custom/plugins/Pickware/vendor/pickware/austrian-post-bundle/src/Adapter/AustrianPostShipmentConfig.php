<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Adapter;

use Pickware\AustrianPostBundle\Api\AustrianPostProduct;
use Pickware\AustrianPostBundle\Api\Services\AbstractShipmentServiceOption;
use Pickware\AustrianPostBundle\Api\Services\CashOnDeliveryServiceOption;
use Pickware\AustrianPostBundle\Api\Services\FragileServiceOption;
use Pickware\AustrianPostBundle\Api\Services\FreshServiceOption;
use Pickware\AustrianPostBundle\Api\Services\InsuredServiceOption;
use Pickware\AustrianPostBundle\Config\AustrianPostConfig;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AustrianPostShipmentConfig
{
    public function __construct(private readonly array $shipmentConfig) {}

    public function getProduct(): AustrianPostProduct
    {
        return AustrianPostProduct::from($this->shipmentConfig['product'] ?? '');
    }

    public function shouldCreateExportDocuments(): bool
    {
        return (bool) ($this->shipmentConfig['createExportDocuments'] ?? false);
    }

    public function getDeliveryInstruction(): ?string
    {
        return $this->shipmentConfig['deliveryInstruction'] ?? null;
    }

    /**
     * @return AbstractShipmentServiceOption[]
     */
    public function getShipmentServiceOptions(
        ?AustrianPostConfig $austrianPostConfig = null,
    ): array {
        $serviceOptions = [];
        if ($this->shipmentConfig['fragile'] ?? false) {
            $serviceOptions[] = new FragileServiceOption();
        }

        if ($this->shipmentConfig['fresh'] ?? false) {
            $serviceOptions[] = new FreshServiceOption();
        }

        if ($this->shipmentConfig['insured'] ?? false) {
            $serviceOptions[] = new InsuredServiceOption(
                amount: (float) $this->shipmentConfig['insuranceAmount'],
                currency: 'EUR',
            );
        }

        if ($this->shipmentConfig['codEnabled'] ?? false) {
            $serviceOptions[] = new CashOnDeliveryServiceOption(
                amount: (float) $this->shipmentConfig['codAmount'],
                currency: 'EUR',
                bankAccountData: $austrianPostConfig?->getBankTransferData()?->getAccountInformationString() ?? '',
            );
        }

        return $serviceOptions;
    }
}
