<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\IncompatibilityBundle\Incompatibility;

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class SalesChannelIncompatibilityVerifier implements IncompatibilityVerifier
{
    public function __construct(private readonly EntityManager $entityManager) {}

    public function verifyIncompatibilities(array $incompatibilities, Context $context): array
    {
        $incompatibleSalesChannelTypeIds = [];
        foreach ($incompatibilities as $incompatibility) {
            if (!($incompatibility instanceof SalesChannelIncompatibility)) {
                throw new InvalidArgumentException(sprintf(
                    'Can only verify incompatibilities of type %s, %s given.',
                    SalesChannelIncompatibility::class,
                    $incompatibility::class,
                ));
            }
            $incompatibleSalesChannelTypeIds[] = $incompatibility->getSalesChannelTypeId();
        }

        $activeSalesChannelTypeIds = array_map(
            fn(SalesChannelEntity $salesChannel) => $salesChannel->getTypeId(),
            $this->entityManager->findBy(
                SalesChannelDefinition::class,
                [
                    'typeId' => $incompatibleSalesChannelTypeIds,
                    'active' => true,
                ],
                $context,
            )->getElements(),
        );

        return array_filter(
            $incompatibilities,
            fn(SalesChannelIncompatibility $incompatibility) => in_array($incompatibility->getSalesChannelTypeId(), $activeSalesChannelTypeIds),
        );
    }
}
