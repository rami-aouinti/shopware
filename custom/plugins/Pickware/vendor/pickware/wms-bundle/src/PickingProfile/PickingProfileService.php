<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProfile;

use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareWms\PickingProcess\ApiVersioning\ApiVersion20240712\PickingProfileApiLayer;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileDefinition;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileEntity;
use Pickware\PickwareWms\PickwareWmsBundle;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class PickingProfileService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly SystemConfigService $systemConfigService,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
    ) {}

    public function isPartialDeliveryAllowed(string $pickingProfileId, Context $context): bool
    {
        if ($pickingProfileId === PickingProfileApiLayer::PICKING_PROFILE_ID) {
            // If the system config is not set, partial deliveries are considered allowed.
            return !$this->systemConfigService->get(
                PickwareWmsBundle::GLOBAL_PLUGIN_CONFIG_DOMAIN . '.disallowPartialDeliveries',
            );
        }

        /** @var PickingProfileEntity $pickingProfile */
        $pickingProfile = $this->entityManager->getByPrimaryKey(
            PickingProfileDefinition::class,
            $pickingProfileId,
            $context,
        );

        return $pickingProfile->getIsPartialDeliveryAllowed();
    }

    public function createOrderFilterCriteria(string $pickingProfileId, Context $context): Criteria
    {
        if ($pickingProfileId === PickingProfileApiLayer::PICKING_PROFILE_ID) {
            $filterQueries = [
                // By default all payment methods used to be allowed with the states 'paid' and 'partially_refunded'
                new EqualsAnyFilter(
                    'transactions.stateMachineState.technicalName',
                    [
                        OrderTransactionStates::STATE_PAID,
                        OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
                    ],
                ),
            ];

            /** @var array<string>|null $paymentMethodIdsAllowedWithStateOpen */
            $paymentMethodIdsAllowedWithStateOpen = $this->systemConfigService->get(
                PickwareWmsBundle::GLOBAL_PLUGIN_CONFIG_DOMAIN . '.paymentMethodIdsAllowedForPickingWithPaymentStateOpen',
            );
            if (!empty($paymentMethodIdsAllowedWithStateOpen)) {
                $filterQueries[] = new AndFilter([
                    new EqualsAnyFilter('transactions.paymentMethodId', $paymentMethodIdsAllowedWithStateOpen),
                    new EqualsFilter('transactions.stateMachineState.technicalName', OrderTransactionStates::STATE_OPEN),
                ]);
            }

            $criteria = new Criteria();
            $criteria->addFilter(new OrFilter($filterQueries));

            return $criteria;
        }

        /** @var PickingProfileEntity $pickingProfile */
        $pickingProfile = $this->entityManager->getByPrimaryKey(
            PickingProfileDefinition::class,
            $pickingProfileId,
            $context,
        );

        if (empty($pickingProfile->getFilter())) {
            return new Criteria();
        }

        return $this->criteriaJsonSerializer->deserializeFromArray(
            ['filter' => [$pickingProfile->getFilter()]],
            OrderDefinition::class,
        );
    }
}
