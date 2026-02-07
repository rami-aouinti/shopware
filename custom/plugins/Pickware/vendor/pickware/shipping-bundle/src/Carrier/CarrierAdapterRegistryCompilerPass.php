<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Carrier;

use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CarrierAdapterRegistryCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(CarrierAdapterRegistry::class)) {
            return;
        }

        $carrierRegistryDefinition = $container->findDefinition(CarrierAdapterRegistry::class);
        $carriers = $container->findTaggedServiceIds(CarrierAdapterRegistry::CONTAINER_TAG);
        foreach ($carriers as $carrierAdapterClassName => $tagAttributes) {
            $technicalName = $tagAttributes[0]['technicalName'];
            if (isset($tagAttributes[0]['featureFlagNames']) && isset($tagAttributes[0]['featureFlagName'])) {
                throw new LogicException(
                    sprintf(
                        'The carrier adapter "%s" is tagged with both "featureFlagNames" and "featureFlagName". ' .
                        'Only one of these attributes can be used.',
                        $carrierAdapterClassName,
                    ),
                );
            }
            if (isset($tagAttributes[0]['featureFlagName'])) {
                $featureFlagNames = [$tagAttributes[0]['featureFlagName']];
            } elseif (isset($tagAttributes[0]['featureFlagNames'])) {
                $featureFlagNames = $tagAttributes[0]['featureFlagNames'];
            } else {
                $featureFlagNames = null;
            }
            if ($featureFlagNames !== null) {
                $carrierRegistryDefinition->addMethodCall(
                    'addCarrierAdapterWithFeatureFlags',
                    [
                        $technicalName,
                        $featureFlagNames,
                        new Reference($carrierAdapterClassName),
                    ],
                );

                continue;
            }
            $carrierRegistryDefinition->addMethodCall('addCarrierAdapter', [$technicalName, new Reference($carrierAdapterClassName)]);
        }
    }
}
