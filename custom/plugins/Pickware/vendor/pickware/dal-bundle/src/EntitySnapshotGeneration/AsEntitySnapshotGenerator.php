<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle\EntitySnapshotGeneration;

use Attribute;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\AsEntitySnapshotGenerator as ShopwareExtensionsBundleAsEntitySnapshotGenerator;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @deprecated will be removed in 6.0.0. Use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\AsEntitySnapshotGenerator instead
 */
#[Exclude]
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AsEntitySnapshotGenerator extends ShopwareExtensionsBundleAsEntitySnapshotGenerator {}
