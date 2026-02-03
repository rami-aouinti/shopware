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
use Pickware\PickwareErpStarter\OrderShipping\Events\CompletelyShippedEvent;
use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class CreateCompletelyShippedFlowInstallationStep
{
    public const FLOW_ID = '3cdf32da3e164e9f80122fdd05c87493';

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

        $createPayload[] = [
            'id' => self::FLOW_ID,
            'eventName' => CompletelyShippedEvent::EVENT_NAME,
            'name' => 'Pickware ERP Completely Shipped',
            'description' => 'Ist dieser Flow aktiviert, wird der Status der Bestellung automatisch auf versendet gesetzt, wenn die Bestellung vollständig über Pickware ERP versendet wurde.',
            'active' => true,
            'sequences' => [
                [
                    'id' => Uuid::randomHex(),
                    'actionName' => SetOrderStateAction::getName(),
                    'config' => [
                        'order_delivery' => 'shipped',
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
            ['eventName' => CompletelyShippedEvent::EVENT_NAME],
            $context,
        );
    }
}
