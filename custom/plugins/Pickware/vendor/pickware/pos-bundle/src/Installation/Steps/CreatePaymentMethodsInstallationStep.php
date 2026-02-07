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

use Pickware\DalBundle\EntityManager;
use Pickware\PickwarePos\Installation\PickwarePosInstaller;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\Context;

class CreatePaymentMethodsInstallationStep
{
    private const PAYMENT_METHODS = [
        [
            'id' => PickwarePosInstaller::PAYMENT_METHOD_ID_CARD,
            'technicalName' => PickwarePosInstaller::PAYMENT_METHOD_TECHNICAL_NAME_CARD,
            'name' => [
                'en-GB' => 'Card Payment',
                'de-DE' => 'EC-/Kreditkarte',
            ],
            'description' => [
                'en-GB' => 'Only for use at the Pickware POS. Use this payment method for card payments via a ' .
                    'card terminal.',
                'de-DE' => 'Nur für die Verwendung am Pickware POS. Nutze diese Zahlungsart für EC- oder ' .
                    'Kreditkartenzahlungen über ein Kartenterminal.',
            ],
            'active' => true,
            'afterOrderEnabled' => false,
        ],
        [
            'id' => PickwarePosInstaller::PAYMENT_METHOD_ID_CASH,
            'technicalName' => PickwarePosInstaller::PAYMENT_METHOD_TECHNICAL_NAME_CASH,
            'name' => [
                'en-GB' => 'Cash',
                'de-DE' => 'Bar',
            ],
            'description' => [
                'en-GB' => 'Only for use at the Pickware POS. Use this payment method for cash payments.',
                'de-DE' => 'Nur für die Verwendung am Pickware POS. Nutze diese Zahlungsart für Barzahlungen.',
            ],
            'active' => true,
            'afterOrderEnabled' => false,
        ],
        [
            'id' => PickwarePosInstaller::PAYMENT_METHOD_ID_PAY_ON_COLLECTION,
            'technicalName' => PickwarePosInstaller::PAYMENT_METHOD_TECHNICAL_NAME_PAY_ON_COLLECTION,
            'name' => [
                'en-GB' => 'Pay on collection (Click & Collect)',
                'de-DE' => 'Zahlung bei Abholung (Click & Collect)',
            ],
            'description' => [
                'en-GB' => 'Pay for your order conveniently when you pick it up in our store.',
                'de-DE' => 'Zahle deine Bestellung bequem beim Abholen in unserem Store.',
            ],
            'active' => true,
            'afterOrderEnabled' => false,
        ],
    ];

    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function install(Context $context): void
    {
        $this->entityManager->createIfNotExists(
            PaymentMethodDefinition::class,
            self::PAYMENT_METHODS,
            $context,
        );
    }
}
