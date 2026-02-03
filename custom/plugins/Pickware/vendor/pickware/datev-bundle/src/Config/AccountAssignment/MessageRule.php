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

use Pickware\DatevBundle\EntryBatch\EntryBatchLogMessage;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @template Item
 * @implements Rule<Item>
 */
#[Exclude]
class MessageRule implements Rule
{
    /**
     * @var callable(Item, AccountAssignmentMetadata): ImmutableCollection<EntryBatchLogMessage> $messageFactory
     */
    private $messageFactory;

    /**
     * @param callable(Item, AccountAssignmentMetadata): ImmutableCollection<EntryBatchLogMessage> $messageFactory
     * @param ImmutableCollection<Condition<Item>> $conditions
     */
    public function __construct(
        callable $messageFactory,
        private readonly ImmutableCollection $conditions,
    ) {
        $this->messageFactory = $messageFactory;
    }

    /**
     * @param Item $item
     */
    public function apply(AccountAssignmentState $state, $item, AccountAssignmentMetadata $metadata): AccountAssignmentState
    {
        if (
            $this->conditions->containsElementSatisfying(
                /** @param Condition<Item> $condition */
                fn(Condition $condition) => !$condition->matches($item),
            )
        ) {
            return $state;
        }

        return new AccountAssignmentState(
            $state->getAccountDetermination(),
            $state->getMessages()->merge(call_user_func($this->messageFactory, $item, $metadata)),
        );
    }
}
