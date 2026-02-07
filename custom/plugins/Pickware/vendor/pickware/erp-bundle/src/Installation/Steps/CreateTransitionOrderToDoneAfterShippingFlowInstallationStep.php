<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Installation\Steps;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Framework\Context;

class CreateTransitionOrderToDoneAfterShippingFlowInstallationStep
{
    public const FLOW_ID = '35fc0da4ffa2470cb3308f5dfc13b06a';
    public const FLOW_ACTION_SET_ORDER_STATE_SEQUENCE_ID = 'fb4f899646ed403e92e3497f02a3e3a8';

    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function install(Context $context): void
    {
        /** @var ?FlowEntity $existingFlow */
        $existingFlow = $this->entityManager->findByPrimaryKey(
            FlowDefinition::class,
            self::FLOW_ID,
            $context,
        );

        if ($existingFlow) {
            return;
        }

        $this->entityManager->create(
            FlowDefinition::class,
            [
                [
                    'id' => self::FLOW_ID,
                    'name' => 'Pickware ERP Transition order to "Done" after shipping',
                    'description' => 'Ist dieser Flow aktiviert, wird die Bestellung autom. auf "Abgeschlossen" gesetzt wenn die Bestellung versandt wurde.',
                    'eventName' => 'state_enter.order_delivery.state.shipped',
                    'active' => false,
                    'sequences' => [
                        [
                            'id' => self::FLOW_ACTION_SET_ORDER_STATE_SEQUENCE_ID,
                            'actionName' => 'action.set.order.state',
                            'config' => [
                                'order' => 'completed',
                                'order_delivery' => '',
                                'order_transaction' => '',
                                'force_transition' => true,
                            ],
                        ],
                    ],
                ],
            ],
            $context,
        );
    }
}
