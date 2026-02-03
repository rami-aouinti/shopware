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
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Framework\Context;

class CreatePosCustomerGroupFallbackInstallationStep
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function install(Context $context): void
    {
        $this->entityManager->createIfNotExists(CustomerGroupDefinition::class, [
            [
                'id' => PickwarePosInstaller::CUSTOMER_GROUP_FALLBACK_ID,
                'name' => [
                    'de-DE' => 'Standard-Kundengruppe',
                    'en-GB' => 'Standard customer group',
                ],
                'displayGross' => true,
            ],
        ], $context);
    }
}
