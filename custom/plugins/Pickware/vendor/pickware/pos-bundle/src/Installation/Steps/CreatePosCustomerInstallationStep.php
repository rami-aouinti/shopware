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
use Pickware\DalBundle\IdResolver\EntityIdResolver;
use Pickware\PickwarePos\Installation\PickwarePosInstaller;
use Pickware\PickwarePos\PickwarePosBundle;
use Pickware\ShopwareExtensionsBundle\Mail\MailSendSuppressionService;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class CreatePosCustomerInstallationStep
{
    private EntityManager $entityManager;
    private EntityIdResolver $entityIdResolver;
    private NumberRangeValueGeneratorInterface $numberRangeValueGenerator;
    private MailSendSuppressionService $mailSendSuppressionService;

    public function __construct(
        EntityManager $entityManager,
        EntityIdResolver $entityIdResolver,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        MailSendSuppressionService $mailSendSuppressionService,
    ) {
        $this->entityManager = $entityManager;
        $this->entityIdResolver = $entityIdResolver;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->mailSendSuppressionService = $mailSendSuppressionService;
    }

    public function install(Context $context): void
    {
        $existingCustomer = $this->entityManager->findByPrimaryKey(
            CustomerDefinition::class,
            PickwarePosInstaller::CUSTOMER_ID_POS,
            $context,
        );
        /** @var SalesChannelCollection $existingPosSalesChannels */
        $existingPosSalesChannels = $this->entityManager->findBy(SalesChannelDefinition::class, [
            'typeId' => PickwarePosBundle::SALES_CHANNEL_TYPE_ID,
        ], $context);
        $salesChannel = $existingPosSalesChannels->first();
        if ($existingCustomer || !$salesChannel) {
            return;
        }
        $customerNumber = $this->numberRangeValueGenerator->getValue('customer', $context, null);
        $salutationId = $this->entityIdResolver->resolveIdForSalutation('not_specified');
        $address = [
            'salutationId' => $salutationId,
            'firstName' => 'POS',
            'lastName' => 'Laufkunde',
            'zipcode' => '12345',
            'city' => 'POS',
            'street' => 'Beispielstraße 1',
            'countryId' => $this->entityIdResolver->resolveIdForCountry('DE'),
        ];
        $this->mailSendSuppressionService->runWithMailSendDisabled(
            fn() => $this->entityManager->create(
                CustomerDefinition::class,
                [
                    [
                        'id' => PickwarePosInstaller::CUSTOMER_ID_POS,
                        'groupId' => PickwarePosInstaller::CUSTOMER_GROUP_ID_POS,
                        'defaultPaymentMethodId' => PickwarePosInstaller::PAYMENT_METHOD_ID_CASH,
                        'lastPaymentMethodId' => PickwarePosInstaller::PAYMENT_METHOD_ID_CASH,
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'salesChannelId' => $salesChannel->getId(),
                        'defaultShippingAddress' => $address,
                        'defaultBillingAddress' => $address,
                        'customerNumber' => $customerNumber,
                        'salutationId' => $salutationId,
                        'firstName' => 'POS',
                        'lastName' => 'Laufkunde',
                        'email' => 'laufkunde@example.com',
                        'active' => false,
                    ],
                ],
                $context,
            ),
            $context,
        );
    }
}
