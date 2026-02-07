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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ApiLayerAnnotationCachingCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $containerBuilder): void
    {
        $taggedTransformations = $containerBuilder->findTaggedServiceIds(
            ApiLayerCollectionCompilerPass::API_LAYER_TAG,
        );
        $transformationClassNames = array_map(
            fn(string $id) => $containerBuilder->getDefinition($id)->getClass(),
            array_keys($taggedTransformations),
        );

        // The bundle's extension name is generated as: bundle name without its `Bundle` suffix, converted to snake_case
        $extension = $containerBuilder->getExtension('pickware_api_versioning');
        $extension->addAnnotatedClassesToCompile($transformationClassNames);
    }
}
