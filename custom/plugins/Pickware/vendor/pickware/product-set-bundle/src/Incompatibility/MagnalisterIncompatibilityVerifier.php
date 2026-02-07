<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Incompatibility;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use InvalidArgumentException;
use Pickware\IncompatibilityBundle\Incompatibility\IncompatibilityVerifier;
use Pickware\IncompatibilityBundle\Incompatibility\PluginIncompatibilityVerifier;
use Shopware\Core\Framework\Context;

class MagnalisterIncompatibilityVerifier implements IncompatibilityVerifier
{
    public function __construct(
        private readonly PluginIncompatibilityVerifier $pluginIncompatibilityVerifier,
        private readonly Connection $connection,
    ) {}

    public function verifyIncompatibilities(array $incompatibilities, Context $context): array
    {
        $activePluginIncompatibilities = $this->pluginIncompatibilityVerifier->verifyIncompatibilities($incompatibilities, $context);
        if (empty($activePluginIncompatibilities)) {
            // Early return if the magnalister plugin is not installed and active.
            return [];
        }

        $configKeys = [];
        foreach ($activePluginIncompatibilities as $incompatibility) {
            if (!($incompatibility instanceof MagnalisterIncompatibility)) {
                throw new InvalidArgumentException(sprintf(
                    'Can only verify incompatibilities of type %s, %s given.',
                    MagnalisterIncompatibility::class,
                    $incompatibility::class,
                ));
            }
            $configKeys[] = $incompatibility->getConfigKey();
        }

        try {
            $configValues = $this->connection->fetchAllKeyValue(
                'SELECT `mkey`, `value` FROM `magnalister_config` WHERE `mkey` IN (:configKeys);',
                ['configKeys' => $configKeys],
                ['configKeys' => ArrayParameterType::STRING],
            );
        } catch (TableNotFoundException | InvalidFieldNameException) {
            // Do not crash if magnalister decides to rename the table or columns.
            return [];
        }

        return array_filter(
            $activePluginIncompatibilities,
            fn(MagnalisterIncompatibility $incompatibility) => array_key_exists($incompatibility->getConfigKey(), $configValues)
                && $configValues[$incompatibility->getConfigKey()] === $incompatibility->getConfigValue(),
        );
    }
}
