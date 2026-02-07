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

use Pickware\DatevBundle\Config\AccountAssignment\Item\AccountAssignment;
use Pickware\DatevBundle\Config\AccountAssignment\Item\AccountRequestItem;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @template Item of AccountRequestItem
 */
#[Exclude]
class AccountAssignmentCollection
{
    /**
     * @var array<string, AccountAssignment<Item>> $accountAssignmentsByKey
     */
    private array $accountAssignmentsByKey;

    /**
     * @param ImmutableCollection<AccountAssignment<Item>> $accountAssignments
     */
    public function __construct(ImmutableCollection $accountAssignments)
    {
        $this->accountAssignmentsByKey = [];
        foreach ($accountAssignments as $accountAssignment) {
            $this->accountAssignmentsByKey[$accountAssignment->getKey()] = $accountAssignment;
        }
    }

    /**
     * @return AccountAssignment<Item>
     */
    public function getByItem(AccountRequestItem $item): AccountAssignment
    {
        return $this->accountAssignmentsByKey[$item->getKey()];
    }

    /**
     * @return ImmutableCollection<AccountAssignment<Item>>
     */
    public function getAsImmutableCollection(): ImmutableCollection
    {
        return ImmutableCollection::create($this->accountAssignmentsByKey);
    }
}
