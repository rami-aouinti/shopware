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

use Pickware\PickwarePos\Installation\PickwarePosInstaller;
use Pickware\PickwarePos\PickwarePosBundle;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CreateDefaultConfigInstallationStep
{
    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function install(): void
    {
        if ($this->systemConfigService->get(PickwarePosBundle::POS_AUTOMATIC_RECEIPT_PRINTING_CONFIG_KEY) !== null) {
            return;
        }

        $config = [
            PickwarePosBundle::POS_AUTOMATIC_RECEIPT_PRINTING_CONFIG_KEY => true,
            PickwarePosBundle::POS_GROUP_PRODUCT_VARIANTS_CONFIG_KEY => true,
            PickwarePosBundle::POS_OVERSELLING_WARNING_CONFIG_KEY => true,
            PickwarePosBundle::POS_RECEIPT_SHOW_LIST_PRICES => true,
            PickwarePosBundle::PLUGIN_CONFIG_KEY_PREFIX . 'posShippingMethodId' => PickwarePosInstaller::SHIPPING_METHOD_ID_POS,
            PickwarePosBundle::PLUGIN_CONFIG_KEY_PREFIX . 'posCashPaymentMethodId' => PickwarePosInstaller::PAYMENT_METHOD_ID_CASH,
            PickwarePosBundle::PLUGIN_CONFIG_KEY_PREFIX . 'posCardPaymentMethodIds' => [PickwarePosInstaller::PAYMENT_METHOD_ID_CARD],
            PickwarePosBundle::PLUGIN_CONFIG_KEY_PREFIX . 'clickAndCollectShippingMethodIds' => [PickwarePosInstaller::SHIPPING_METHOD_ID_CLICK_AND_COLLECT],
            PickwarePosBundle::PLUGIN_CONFIG_KEY_PREFIX . 'clickAndCollectPaymentMethodId' => PickwarePosInstaller::PAYMENT_METHOD_ID_PAY_ON_COLLECTION,
            PickwarePosBundle::PLUGIN_CONFIG_KEY_PREFIX . 'posDefaultCustomerId' => PickwarePosInstaller::CUSTOMER_ID_POS,
            PickwarePosBundle::PLUGIN_CONFIG_KEY_PREFIX . 'posDepositWithdrawalComments' => "Barentnahme\nBareinlage",
        ];

        foreach ($config as $key => $value) {
            $this->systemConfigService->set($key, $value);
        }
    }
}
