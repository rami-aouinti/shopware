<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\AccountAssignment;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @template Item
 */
#[Exclude]
interface Rule
{
    /**
     * @param Item $item
     */
    public function apply(AccountAssignmentState $state, $item, AccountAssignmentMetadata $metadata): AccountAssignmentState;
}
