<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Batch\FeatureFlags;

use Pickware\FeatureFlagBundle\DevelopmentFeatureFlag;

class ProductBatchesSupportingBbdDevelopmentFeatureFlag extends DevelopmentFeatureFlag
{
    public const NAME = 'pickware-wms.dev.product-batches-supporting-bbd';

    public function __construct()
    {
        parent::__construct(name: self::NAME, isFeatureReleased: false);
    }
}
