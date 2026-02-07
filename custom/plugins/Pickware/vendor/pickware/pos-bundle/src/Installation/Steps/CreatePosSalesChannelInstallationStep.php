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
use Pickware\PickwarePos\PickwarePosBundle;
use Pickware\PickwarePos\SalesChannel\PickwarePosSalesChannelService;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class CreatePosSalesChannelInstallationStep
{
    private PickwarePosSalesChannelService $pickwarePosSalesChannelService;
    private EntityManager $entityManager;

    public function __construct(
        PickwarePosSalesChannelService $pickwarePosSalesChannelService,
        EntityManager $entityManager,
    ) {
        $this->pickwarePosSalesChannelService = $pickwarePosSalesChannelService;
        $this->entityManager = $entityManager;
    }

    public function install(Context $context): void
    {
        $existingPosSalesChannels = $this->entityManager->findBy(
            SalesChannelDefinition::class,
            EntityManager::createCriteriaFromArray(['typeId' => PickwarePosBundle::SALES_CHANNEL_TYPE_ID])->setLimit(1),
            $context,
        );
        if ($existingPosSalesChannels->count() !== 0) {
            return;
        }

        $this->pickwarePosSalesChannelService->createNewPickwarePosSalesChannel(
            [
                'paymentMethodId' => PickwarePosInstaller::PAYMENT_METHOD_ID_CASH,
                'shippingMethodId' => PickwarePosInstaller::SHIPPING_METHOD_ID_POS,
            ],
            $context,
        );
    }
}
