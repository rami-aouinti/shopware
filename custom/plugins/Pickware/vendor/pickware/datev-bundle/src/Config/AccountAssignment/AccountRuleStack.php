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
class AccountRuleStack
{
    /**
     * @param array<Rule<Item>> $sortedRules The rules that should be checked for matches sequentially. Once a matched
     * rule transforms the provided state such that an account is given, matching stops entirely.
     */
    public function __construct(
        private readonly array $sortedRules,
    ) {}

    /**
     * @param ImmutableCollection<Item> $items
     * @return AccountAssignmentCollection<Item>
     */
    public function map(ImmutableCollection $items, AccountAssignmentMetadata $metadata): AccountAssignmentCollection
    {
        $accountAssignments = $items->map(
            /**
             * @param Item $item
             * @return AccountAssignment<Item>
             */
            function($item) use ($metadata): AccountAssignment {
                $state = new AccountAssignmentState(accountDetermination: AccountDetermination::createForStaticAccount(null), messages: ImmutableCollection::create());
                // Applies rules until an account is found, i.e. when the first account rule matches
                foreach ($this->sortedRules as $rule) {
                    $state = $rule->apply($state, $item, $metadata);

                    if ($state->getAccountDetermination()->getAccount() !== null) {
                        break;
                    }
                }

                return new AccountAssignment(
                    accountDetermination: $state->getAccountDetermination(),
                    messages: $state->getMessages()->asArray(),
                    item: $item,
                );
            },
        );

        return new AccountAssignmentCollection($accountAssignments);
    }
}
