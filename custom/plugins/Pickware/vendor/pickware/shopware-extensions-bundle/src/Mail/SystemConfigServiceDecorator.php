<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\Mail;

use Closure;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * To avoid unwanted side effects when disabling mail delivery (because the state is saved in DB)
 * we need this Decorator to override the 'core.mailerSettings.disableDelivery' on request level.
 * Use this Decorator in combination with the following Service:
 * @see MailSendSuppressionService
 */
class SystemConfigServiceDecorator extends SystemConfigService
{
    /**
     * In Shopware in src/Core/Framework/Test/TestCaseBase/SystemConfigTestBehaviour.php in
     * versions <= 6.4.9.0, there is direct access to $configs: `$property = $reflection->getProperty('configs');`.
     * This is why we have to declare the property, even though we do not use it, as otherwise
     * tests that include src/Core/Framework/Test/TestCaseBase/IntegrationTestBehaviour.php will fail.
     */
    public $configs = [];

    /**
     * The pickware key is just saved in the current request.
     */
    public const PICKWARE_DISABLE_MAIL_DELIVERY = 'PickwareShopwareExtensionsBundle.mailerSettings.disableDelivery';

    /**
     * When the user asks for the setting of 'core.mailerSettings.disableDelivery' the decorator
     * checks state against the pickware custom property ($disableMailDelivery) and the original key (from database).
     */
    private const SHOPWARE_DISABLE_MAIL_DELIVERY = 'core.mailerSettings.disableDelivery';

    private bool $disableMailDelivery = false;

    public function __construct(
        private readonly SystemConfigService $decoratedSystemConfigService,
    ) {}

    public function get(string $key, ?string $salesChannelId = null)
    {
        // For 'core.mailerSettings.disableDelivery' check pickware custom override property and then shopware setting.
        if ($key === self::SHOPWARE_DISABLE_MAIL_DELIVERY) {
            return $this->disableMailDelivery || $this->decoratedSystemConfigService->get($key, $salesChannelId);
        }

        return $this->decoratedSystemConfigService->get($key, $salesChannelId);
    }

    public function getString(string $key, ?string $salesChannelId = null): string
    {
        return $this->decoratedSystemConfigService->getString($key, $salesChannelId);
    }

    public function getInt(string $key, ?string $salesChannelId = null): int
    {
        return $this->decoratedSystemConfigService->getInt($key, $salesChannelId);
    }

    public function getFloat(string $key, ?string $salesChannelId = null): float
    {
        return $this->decoratedSystemConfigService->getFloat($key, $salesChannelId);
    }

    public function getBool(string $key, ?string $salesChannelId = null): bool
    {
        // For 'core.mailerSettings.disableDelivery' check pickware custom override property and then shopware setting.
        if ($key === self::SHOPWARE_DISABLE_MAIL_DELIVERY) {
            return $this->disableMailDelivery || $this->decoratedSystemConfigService->getBool($key, $salesChannelId);
        }

        return $this->decoratedSystemConfigService->getBool($key, $salesChannelId);
    }

    public function all(?string $salesChannelId = null): array
    {
        return $this->decoratedSystemConfigService->all($salesChannelId);
    }

    public function getDomain(string $domain, ?string $salesChannelId = null, bool $inherit = false): array
    {
        return $this->decoratedSystemConfigService->getDomain($domain, $salesChannelId, $inherit);
    }

    public function set(string $key, $value, ?string $salesChannelId = null): void
    {
        // The pickware key is not saved in the database! The key is scoped to the request.
        if ($key === self::PICKWARE_DISABLE_MAIL_DELIVERY) {
            $this->disableMailDelivery = (bool) $value;

            return;
        }

        $this->decoratedSystemConfigService->set($key, $value, $salesChannelId);
    }

    public function setMultiple(array $values, ?string $salesChannelId = null): void
    {
        $this->decoratedSystemConfigService->setMultiple($values, $salesChannelId);
    }

    public function delete(string $key, ?string $salesChannel = null): void
    {
        $this->decoratedSystemConfigService->delete($key, $salesChannel);
    }

    public function savePluginConfiguration(Bundle $bundle, bool $override = false): void
    {
        $this->decoratedSystemConfigService->savePluginConfiguration($bundle, $override);
    }

    public function saveConfig(array $config, string $prefix, bool $override): void
    {
        $this->decoratedSystemConfigService->saveConfig($config, $prefix, $override);
    }

    public function deletePluginConfiguration(Bundle $bundle): void
    {
        $this->decoratedSystemConfigService->deletePluginConfiguration($bundle);
    }

    public function deleteExtensionConfiguration(string $extensionName, array $config): void
    {
        $this->decoratedSystemConfigService->deleteExtensionConfiguration($extensionName, $config);
    }

    public function trace(string $key, Closure $param)
    {
        return $this->decoratedSystemConfigService->trace($key, $param);
    }

    public function getTrace(string $key): array
    {
        return $this->decoratedSystemConfigService->getTrace($key);
    }

    public function reset(): void
    {
        $this->decoratedSystemConfigService->reset();
    }
}
