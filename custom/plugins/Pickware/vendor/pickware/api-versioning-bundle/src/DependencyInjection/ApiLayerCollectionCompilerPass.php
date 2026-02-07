<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiVersioningBundle\DependencyInjection;

use Pickware\ApiVersioningBundle\ApiVersioningRequestSubscriber;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ApiLayerCollectionCompilerPass implements CompilerPassInterface
{
    public const API_LAYER_TAG = 'pickware_api_versioning_bundle.api_layer';

    public function process(ContainerBuilder $containerBuilder): void
    {
        if (!$containerBuilder->has(ApiVersioningRequestSubscriber::class)) {
            return;
        }

        $requestSubscriber = $containerBuilder->findDefinition(ApiVersioningRequestSubscriber::class);
        $taggedApiLayers = $containerBuilder->findTaggedServiceIds(self::API_LAYER_TAG);
        foreach ($taggedApiLayers as $id => $tags) {
            $requestSubscriber->addMethodCall('addApiLayer', [new Reference($id), $id]);
        }
    }
}
