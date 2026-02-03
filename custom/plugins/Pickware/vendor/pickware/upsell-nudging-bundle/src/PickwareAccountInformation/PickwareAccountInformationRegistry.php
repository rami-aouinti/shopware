<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsellNudgingBundle\PickwareAccountInformation;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

readonly class PickwareAccountInformationRegistry
{
    public const DI_CONTAINER_TAG = 'pickware_upsell_nudging.pickware_account_information';

    /**
     * @param PickwareAccountInformation[] $pickwareAccountInformations
     */
    public function __construct(
        #[TaggedIterator(tag: self::DI_CONTAINER_TAG)]
        private iterable $pickwareAccountInformations,
    ) {}

    public function getPickwareAccountInformation(): ?PickwareAccountInformation
    {
        foreach ($this->pickwareAccountInformations as $firstElement) {
            // The tagged iterator is ordered by priority, so we can just return the first element to get the highest
            // priority information.
            return $firstElement;
        }

        return null;
    }
}
