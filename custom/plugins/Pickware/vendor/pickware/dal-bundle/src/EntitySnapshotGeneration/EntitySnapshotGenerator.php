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

use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\EntitySnapshotGenerator as ShopwareExtensionsBundleEntitySnapshotGenerator;

/**
 * @deprecated will be removed in 6.0.0. Use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\EntitySnapshotGenerator instead
 * @template Snapshot of array
 * @extends ShopwareExtensionsBundleEntitySnapshotGenerator<Snapshot>
 */
interface EntitySnapshotGenerator extends ShopwareExtensionsBundleEntitySnapshotGenerator {}
