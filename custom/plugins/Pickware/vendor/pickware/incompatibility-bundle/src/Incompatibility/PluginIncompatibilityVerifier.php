<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\IncompatibilityBundle\Incompatibility;

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\Framework\Plugin\PluginEntity;

class PluginIncompatibilityVerifier implements IncompatibilityVerifier
{
    public function __construct(private readonly EntityManager $entityManager) {}

    public function verifyIncompatibilities(array $incompatibilities, Context $context): array
    {
        $incompatiblePluginNames = [];
        foreach ($incompatibilities as $incompatibility) {
            if (!($incompatibility instanceof PluginIncompatibility)) {
                throw new InvalidArgumentException(sprintf(
                    'Can only verify incompatibilities of type %s, %s given.',
                    PluginIncompatibility::class,
                    $incompatibility::class,
                ));
            }
            $incompatiblePluginNames[] = $incompatibility->getConflictingPlugin();
        }

        /** @var PluginCollection $activePlugins */
        $activePlugins = $this->entityManager->findBy(
            PluginDefinition::class,
            [
                'name' => $incompatiblePluginNames,
                'active' => 1,
            ],
            $context,
        );

        return array_filter(
            $incompatibilities,
            fn(PluginIncompatibility $incompatibility) =>
                $activePlugins->filter(
                    function(PluginEntity $plugin) use ($incompatibility): bool {
                        if ($plugin->getName() !== $incompatibility->getConflictingPlugin()) {
                            return false;
                        }

                        $minVersion = $incompatibility->getMinVersion();
                        $maxVersion = $incompatibility->getMaxVersion();

                        return ($minVersion === null || version_compare($plugin->getVersion(), $minVersion, '>='))
                            && ($maxVersion === null || version_compare($plugin->getVersion(), $maxVersion, '<='));
                    },
                )->first() !== null,
        );
    }
}
