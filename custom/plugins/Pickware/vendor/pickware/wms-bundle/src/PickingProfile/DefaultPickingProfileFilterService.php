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

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

class DefaultPickingProfileFilterService
{
    private const FIELD_PAYMENT_STATE = 'transactions.stateId';

    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function makeDefaultFilter(Context $context): array
    {
        $defaultOrderTransactionStateIds = array_values(
            $this->entityManager->findIdsBy(
                StateMachineStateDefinition::class,
                [
                    'stateMachine.technicalName' => OrderTransactionStates::STATE_MACHINE,
                    'technicalName' => [
                        OrderTransactionStates::STATE_PAID,
                        OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
                    ],
                ],
                $context,
            ),
        );

        return [
            'type' => 'multi',
            'operator' => 'and',
            'queries' => [
                [
                    'type' => 'equalsAny',
                    'field' => self::FIELD_PAYMENT_STATE,
                    'value' => $defaultOrderTransactionStateIds,
                ],
            ],
        ];
    }
}
