<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary;

use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Shopware\Core\Framework\Plugin;

readonly class PluginInstallationState
{
    public function __construct(
        public bool $exists,
        public bool $installed,
        public bool $active,
    ) {}

    /**
     * @param class-string<Plugin> $pluginClass
     */
    public static function getForPlugin(Connection $connection, string $pluginClass): self
    {
        if (class_exists($pluginClass) && !is_subclass_of($pluginClass, Plugin::class)) {
            // Check for subclass is only possible if the class exists. In the other case we just assume that the class
            // is a subclass of Plugin.
            throw new InvalidArgumentException(
                sprintf('Class "%s" is not a subclass of "%s"', $pluginClass, Plugin::class),
            );
        }

        $pluginName = basename(str_replace('\\', '/', $pluginClass));

        $plugin = $connection->fetchAssociative(
            'SELECT `installed_at`, `active` FROM `plugin` WHERE `name` = :pluginName',
            ['pluginName' => $pluginName],
        );

        $installed = false;
        $active = false;
        $exists = false;

        if ($plugin !== false) {
            $exists = true;
            $installed = $plugin['installed_at'] !== null;
            $active = (bool)$plugin['active'];
        }

        return new self($exists, $installed, $active);
    }
}
