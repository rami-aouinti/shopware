<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Stocking;

use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\Stocking\StockingRequest;
use Pickware\PickwareErpStarter\Stocking\StockingStrategy;
use Pickware\PickwareWms\PickwareWmsBundle;
use RuntimeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class WmsStockingStrategy implements StockingStrategy
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly ChaoticWarehousingStockingStrategy $chaoticWarehousingStockingStrategy,
        private readonly StaticWarehousingStockingStrategy $staticWarehousingStockingStrategy,
    ) {}

    public function calculateStockingSolution(
        StockingRequest $stockingRequest,
        Context $context,
    ): ProductQuantityLocationImmutableCollection {
        $selectedStockingStrategyConfigValue = $this->systemConfigService->get(
            PickwareWmsBundle::GLOBAL_PLUGIN_CONFIG_DOMAIN . '.selectedStockingStrategy',
        );

        $selectedStockingStrategy = match ($selectedStockingStrategyConfigValue) {
            'chaotic' => $this->chaoticWarehousingStockingStrategy,
            'static' => $this->staticWarehousingStockingStrategy,
            default => throw new RuntimeException(
                'Unknown stocking strategy in pickware-wms plugin config: ' . $selectedStockingStrategyConfigValue,
            ),
        };

        return $selectedStockingStrategy->calculateStockingSolution(
            stockingRequest: $stockingRequest,
            context: $context,
        );
    }
}
