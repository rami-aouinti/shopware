<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;

/**
 * The same as ReferenceVersionField but the flag Required() always set.
 *
 * Always use this class instead of the ReferenceVersionField.
 *
 * Here is why:
 *
 * For optional references (i.e. the reference id and reference version_id are not required) when using Shopware's
 * ReferenceVersionField, the reference version_id field is not auto written. And we cannot easily ensure that the
 * referenced version_id is written "whenever possible".
 *
 * Solution: Use this FixedReferenceVersionField instead of Shopware's ReferenceVersionField for optional and
 * non-optional references. This way, we make sure that the version is always written. We tolerate the case that the
 * version is always written even if the reference id field may be NULL for optional references.
 */
class FixedReferenceVersionField extends ReferenceVersionField
{
    public function __construct(string $definition, ?string $storageName = null)
    {
        parent::__construct($definition, $storageName);
        $this->addFlags(new Required());
    }
}
