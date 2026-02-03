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
use Shopware\Core\Framework\Context;
use Shopware\Core\System\DeliveryTime\DeliveryTimeDefinition;

class CreateDeliveryTimeInstallationStep
{
    private const DELIVERY_TIMES = [
        [
            'id' => PickwarePosInstaller::DELIVERY_TIME_ID_SELF_COLLECTION,
            'name' => [
                'en-GB' => 'Self collection',
                'de-DE' => 'Selbstabholung',
            ],
            'min' => 1,
            'max' => 3,
            'unit' => 'day',
        ],
        [
            'id' => PickwarePosInstaller::DELIVERY_TIME_ID_INSTANT,
            'name' => [
                'en-GB' => 'Instant (POS)',
                'de-DE' => 'Sofort (POS)',
            ],
            'min' => 0,
            'max' => 0,
            'unit' => 'day',
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
            DeliveryTimeDefinition::class,
            self::DELIVERY_TIMES,
            $context,
        );
    }
}
