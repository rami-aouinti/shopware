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
use Pickware\PickwareErpStarter\ReturnOrder\Events\PartiallyReturnedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class CreatePartiallyReturnedFlowInstallationStep
{
    public const FLOW_ID = '6d58b50339bb4d6d836e6c5c57620d1f';

    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function install(Context $context): void
    {
        /** @var FlowEntity|null $existingFlow */
        $existingFlow = $this->entityManager->findByPrimaryKey(
            FlowDefinition::class,
            self::FLOW_ID,
            $context,
        );
        if ($existingFlow) {
            return;
        }

        /** @var ?StateMachineStateEntity $returnedPartiallyStateMachineState */
        $returnedPartiallyStateMachineState = $this->entityManager->findOneBy(
            StateMachineStateDefinition::class,
            [
                'technicalName' => OrderDeliveryStates::STATE_PARTIALLY_RETURNED,
                'stateMachine.technicalName' => OrderDeliveryStates::STATE_MACHINE,
            ],
            $context,
        );
        if (!$returnedPartiallyStateMachineState) {
            return;
        }

        $createPayload[] = [
            'id' => self::FLOW_ID,
            'eventName' => PartiallyReturnedEvent::EVENT_NAME,
            'name' => 'Pickware ERP Partially Returned',
            'description' => 'Ist dieser Flow aktiviert, wird der Status der Bestellung automatisch auf teilweise retourniert gesetzt, wenn über Pickware ERP eine Teilretoure generiert wird.',
            'active' => true,
            'sequences' => [
                [
                    'id' => Uuid::randomHex(),
                    'actionName' => SetOrderStateAction::getName(),
                    'config' => [
                        'order_delivery' => 'returned_partially',
                        // Must be force-transition. See https://github.com/pickware/shopware-plugins/issues/4071
                        'force_transition' => true,
                    ],
                ],
            ],
        ];

        $this->entityManager->runInTransactionWithRetry(function() use ($createPayload, $context): void {
            $this->entityManager->create(FlowDefinition::class, $createPayload, $context);
        });
    }

    public function uninstall(Context $context): void
    {
        // Delete all flow with the CompletelyReturnedEvent as a trigger
        $this->entityManager->deleteByCriteria(
            FlowDefinition::class,
            ['eventName' => PartiallyReturnedEvent::EVENT_NAME],
            $context,
        );
    }
}
