<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Installation\Steps;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Framework\Context;

class ActivatePickwareErpStarterTransitionOrderToDoneAfterShippingFlowInstallationStep
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function activateErpStarterFlow(Context $context): void
    {
        /** @var ?FlowEntity $existingFlow */
        $existingFlow = $this->entityManager->findByPrimaryKey(
            FlowDefinition::class,
            '35fc0da4ffa2470cb3308f5dfc13b06a',
            $context,
        );

        if (!$existingFlow) {
            return;
        }

        $this->entityManager->update(
            FlowDefinition::class,
            [
                [
                    'id' => '35fc0da4ffa2470cb3308f5dfc13b06a',
                    'active' => true,
                ],
            ],
            $context,
        );
    }
}
