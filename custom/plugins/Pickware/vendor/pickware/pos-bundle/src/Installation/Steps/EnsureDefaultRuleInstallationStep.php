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
use Pickware\DalBundle\IdResolver\EntityIdResolver;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\Context;

class EnsureDefaultRuleInstallationStep
{
    private const DEFAULT_RULE_PAYLOAD = [
        'name' => EntityIdResolver::DEFAULT_RULE_NAME,
        'priority' => 100,
        'conditions' => [
            [
                'type' => 'alwaysValid',
                'value' => null,
                'position' => 0,
            ],
        ],
    ];

    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function install(Context $context): void
    {
        $defaultRules = $this->entityManager->findBy(
            RuleDefinition::class,
            ['name' => EntityIdResolver::DEFAULT_RULE_NAME],
            $context,
        );

        if ($defaultRules->count() >= 1) {
            return;
        }

        $this->entityManager->create(RuleDefinition::class, [self::DEFAULT_RULE_PAYLOAD], $context);
    }
}
