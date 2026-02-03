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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * The extension of the PickwareApiVersioning bundle. It is only used for collecting API layer class names to
 * automatically cache their annotations (see ApiLayerAnnotationCachingCompilerPass).
 */
class PickwareApiVersioningExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void {}
}
