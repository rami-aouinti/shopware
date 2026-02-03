<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config;

use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\Config\Model\DatevConfigDefinition;
use Pickware\DatevBundle\Config\Model\DatevConfigEntity;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Shopware\Core\Framework\Context;

class ConfigService
{
    /**
     * Fixed id used for the default datev config
     */
    public const DEFAULT_CONFIG_ID = '00000000000000000000000000000001';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    public function getConfig(?string $salesChannelId, Context $context): DatevConfigEntity
    {
        if (!$this->featureFlagService->isActive(ConfigurationPerSalesChannelProductionFeatureFlag::NAME)) {
            return $this->getDefaultConfig($context);
        }

        $critera = [
            'salesChannelId' => $salesChannelId,
        ];

        /** @var DatevConfigEntity|null $config */
        $config = $this->entityManager->findOneBy(
            DatevConfigDefinition::class,
            $critera,
            $context,
        );

        if ($config !== null) {
            return $config;
        }

        return $this->getDefaultConfig($context);
    }

    private function getDefaultConfig(Context $context): DatevConfigEntity
    {
        /** @var DatevConfigEntity $defaultConfig */
        $defaultConfig = $this->entityManager->getByPrimaryKey(
            DatevConfigDefinition::class,
            self::DEFAULT_CONFIG_ID,
            $context,
        );

        return $defaultConfig;
    }
}
