<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\SalesChannel;

use Pickware\DalBundle\EntityCollectionExtension;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\IdResolver\EntityIdResolver;
use Pickware\PickwarePos\Installation\PickwarePosInstaller;
use Pickware\PickwarePos\PickwarePosBundle;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class PickwarePosSalesChannelService
{
    private EntityManager $entityManager;
    private EntityIdResolver $entityIdResolver;

    public function __construct(EntityManager $entityManager, EntityIdResolver $entityIdResolver)
    {
        $this->entityManager = $entityManager;
        $this->entityIdResolver = $entityIdResolver;
    }

    /**
     * Creates a new sales channel with Pickware POS sales channel type and corresponding defaults.
     *
     * It is required to pass the default payment method and the default shipping method in $payload parameter:
     * [
     *     'shippingMethodId' => 'some-uuid',
     *     'paymentMethodId' => 'some-uuid',
     * ]
     */
    public function createNewPickwarePosSalesChannel(array $payload, Context $context): string
    {
        $firstActiveCountryId = $this->getFirstActiveCountryId($context);

        $defaultValues = [
            'id' => Uuid::randomHex(),
            'name' => [
                'de-DE' => 'Pickware POS',
                'en-GB' => 'Pickware POS',
            ],
            'shortName' => 'POS',
            'active' => true,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'taxCalculationType' => SalesChannelDefinition::CALCULATION_TYPE_HORIZONTAL,
            'shippingMethods' => array_map(
                fn(string $id) => ['id' => $id],
                $this->getPreInstalledPosShippingMethodIds($context),
            ),
            'paymentMethods' => array_map(
                fn(string $id) => ['id' => $id],
                $this->getPreInstalledPosPaymentMethodIds($context),
            ),
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
            ],
            'currencies' => [
                ['id' => Defaults::CURRENCY],
            ],
            'countries' => [
                ['id' => $firstActiveCountryId],
            ],
        ];
        $payload = array_merge($defaultValues, $payload);

        // Enforce the correct sales channel type
        unset($payload['type']);
        $payload['typeId'] = PickwarePosBundle::SALES_CHANNEL_TYPE_ID;

        if (!isset($payload['language']) && !isset($payload['languageId'])) {
            $payload['languageId'] = Defaults::LANGUAGE_SYSTEM;
        }
        if (!isset($payload['currency']) && !isset($payload['currencyId'])) {
            $payload['currencyId'] = Defaults::CURRENCY;
        }
        if (!isset($payload['country']) && !isset($payload['countryId'])) {
            $payload['countryId'] = $firstActiveCountryId;
        }
        if (!isset($payload['customerGroup']) && !isset($payload['customerGroupId'])) {
            $payload['customerGroupId'] = $this->getPosCustomerGroupId($context);
        }
        if (!isset($payload['navigationCategory']) && !isset($payload['navigationCategoryId'])) {
            $payload['navigationCategoryId'] = $this->entityIdResolver->getRootCategoryId();
        }

        $this->entityManager->create(SalesChannelDefinition::class, [$payload], $context);

        return $payload['id'];
    }

    /**
     * @return string[]
     */
    private function getPreInstalledPosShippingMethodIds(Context $context): array
    {
        /** @var ShippingMethodCollection $shippingMethods */
        $shippingMethods = $this->entityManager->findBy(
            ShippingMethodDefinition::class,
            ['id' => PickwarePosInstaller::SHIPPING_METHOD_IDS_AVAILABLE_AT_POS],
            $context,
        );

        return EntityCollectionExtension::getField($shippingMethods, 'id');
    }

    /**
     * @return string[]
     */
    private function getPreInstalledPosPaymentMethodIds(Context $context): array
    {
        /** @var PaymentMethodCollection $paymentMethods */
        $paymentMethods = $this->entityManager->findBy(
            PaymentMethodDefinition::class,
            ['id' => PickwarePosInstaller::PAYMENT_METHOD_IDS_AVAILABLE_AT_POS],
            $context,
        );

        return EntityCollectionExtension::getField($paymentMethods, 'id');
    }

    private function getFirstActiveCountryId(Context $context): string
    {
        // This is duplicated logic from Shopware's SalesChannelCreator
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('position'));

        return $this->entityManager->getOneBy(CountryDefinition::class, $criteria, $context)->getId();
    }

    private function getPosCustomerGroupId(Context $context): string
    {
        /** @var CustomerGroupEntity|null $posCustomerGroup */
        $posCustomerGroup = $this->entityManager->findByPrimaryKey(
            CustomerGroupDefinition::class,
            PickwarePosInstaller::CUSTOMER_GROUP_ID_POS,
            $context,
        );
        if (!$posCustomerGroup) {
            // Fallback logic in Shopware style (see Shopware's SalesChannelCreator)
            $posCustomerGroup = $this->entityManager->getOneBy(
                CustomerGroupDefinition::class,
                (new Criteria())->setLimit(1),
                $context,
            );
        }

        return $posCustomerGroup->getId();
    }
}
