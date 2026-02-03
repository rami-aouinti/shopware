<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemodataGeneration\Patcher;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigPatcher
{
    private const STOCK_MOVEMENT_COMMENT_GOODS_RECEIPT = 'Wareneingang';
    private const STOCK_MOVEMENT_COMMENT_TRANSFER = 'Umlagerung';
    private const STOCK_MOVEMENT_COMMENT_DEPRECIATION = 'Abschreibung';
    private const STOCK_MOVEMENT_COMMENT_PRODUCT_DAMAGED = 'Produkt beschädigt';
    public const STOCK_MOVEMENT_COMMENTS = [
        self::STOCK_MOVEMENT_COMMENT_GOODS_RECEIPT,
        self::STOCK_MOVEMENT_COMMENT_TRANSFER,
        self::STOCK_MOVEMENT_COMMENT_DEPRECIATION,
        self::STOCK_MOVEMENT_COMMENT_PRODUCT_DAMAGED,
    ];
    private const PICKWARE_ERP_STOCK_MOVEMENT_CONFIG_KEY = 'PickwareErpBundle.global-plugin-config.stockMovementComments';
    private const SHOPWARE_MULTI_INVENTORY_FEATURE_FLAG_NAME = 'MULTI_INVENTORY';
    private const SHOPWARE_RETURNS_MANAGEMENT_FEATURE_FLAG_NAME = 'RETURNS_MANAGEMENT';
    private const INCOMPATIBLE_SHOPWARE_FEATURE_FLAGS = [
        self::SHOPWARE_MULTI_INVENTORY_FEATURE_FLAG_NAME,
        self::SHOPWARE_RETURNS_MANAGEMENT_FEATURE_FLAG_NAME,
    ];

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly FeatureFlagRegistry $featureFlagRegistry,
    ) {}

    public function patch(): void
    {
        $stockMovementComments = implode(PHP_EOL, self::STOCK_MOVEMENT_COMMENTS);
        $this->systemConfigService->set(self::PICKWARE_ERP_STOCK_MOVEMENT_CONFIG_KEY, $stockMovementComments);

        foreach (self::INCOMPATIBLE_SHOPWARE_FEATURE_FLAGS as $featureFlagName) {
            $registeredFeatureFlags = Feature::getRegisteredFeatures();
            if (array_key_exists($featureFlagName, $registeredFeatureFlags)) {
                $this->featureFlagRegistry->disable($featureFlagName);
            }
        }
    }
}
