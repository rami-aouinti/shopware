<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Installation\Steps;

use LogicException;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\IdResolver\EntityIdResolver;
use Pickware\PickwarePos\Installation\PickwarePosInstaller;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;

class CreateShippingMethodsInstallationStep
{
    private const SHIPPING_METHOD_PAYLOADS = [
        [
            'id' => PickwarePosInstaller::SHIPPING_METHOD_ID_POS,
            'technicalName' => PickwarePosInstaller::SHIPPING_METHOD_TECHNICAL_NAME_POS,
            'name' => [
                'en-GB' => 'POS',
                'de-DE' => 'POS',
            ],
            'description' => [
                'en-GB' => 'Only for use at Pickware POS. Use this shipping method for sales at POS.',
                'de-DE' => 'Nur für die Verwendung am Pickware POS. Nutze diese Versandart für Verkäufe am POS.',
            ],
            'active' => true,
            'deliveryTimeId' => PickwarePosInstaller::DELIVERY_TIME_ID_INSTANT,
        ],
        [
            'id' => PickwarePosInstaller::SHIPPING_METHOD_ID_CLICK_AND_COLLECT,
            'technicalName' => PickwarePosInstaller::SHIPPING_METHOD_TECHNICAL_NAME_CLICK_AND_COLLECT,
            'name' => [
                'en-GB' => 'Self Pickup (Click & Collect)',
                'de-DE' => 'Selbstabholung (Click & Collect)',
            ],
            'description' => [
                'en-GB' => 'Just pick up your order at our store.',
                'de-DE' => 'Hole Deine Bestellung einfach in unserem Store ab.',
            ],
            'active' => true,
            'deliveryTimeId' => PickwarePosInstaller::DELIVERY_TIME_ID_SELF_COLLECTION,
            'prices' => [
                [
                    // calculation 2 means "cart price from .. to"
                    'calculation' => 2,
                    'currencyPrice' => [
                        'c' . Defaults::CURRENCY => [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 0.0,
                            'gross' => 0.0,
                            'linked' => false,
                        ],
                    ],
                    'quantityStart' => 0,
                ],
            ],
        ],
    ];

    private EntityManager $entityManager;
    private EntityIdResolver $entityIdResolver;

    public function __construct(EntityManager $entityManager, EntityIdResolver $entityIdResolver)
    {
        $this->entityManager = $entityManager;
        $this->entityIdResolver = $entityIdResolver;
    }

    public function install(Context $context): void
    {
        $defaultRuleId = $this->entityIdResolver->getDefaultRuleId();
        if (!$defaultRuleId) {
            throw new LogicException(
                'The default rule does not exists but should exist at this point as a prior installation step should ' .
                'ensure that it exists. Please check whether the corresponding installation step has been executed.',
            );
        }

        $shippingMethodPayloads = array_map(
            fn(array $payload) => array_merge($payload, ['availabilityRuleId' => $defaultRuleId]),
            self::SHIPPING_METHOD_PAYLOADS,
        );

        $this->entityManager->createIfNotExists(
            ShippingMethodDefinition::class,
            $shippingMethodPayloads,
            $context,
        );
    }
}
