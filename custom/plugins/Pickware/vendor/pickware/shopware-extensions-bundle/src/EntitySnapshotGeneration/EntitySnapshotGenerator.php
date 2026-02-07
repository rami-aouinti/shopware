<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration;

use Shopware\Core\Framework\Context;

/**
 * @template Snapshot of array
 */
interface EntitySnapshotGenerator
{
    /**
     * @param string[] $ids
     * @return array<string, Snapshot> The entity snapshots indexed by the IDs of their entity.
     */
    public function generateSnapshots(array $ids, Context $context): array;
}
