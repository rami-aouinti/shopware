<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder;

use Pickware\InstallationLibrary\NumberRange\NumberRange;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;

class SupplierOrderNumberRange extends NumberRange
{
    public const TECHNICAL_NAME = SupplierOrderDefinition::ENTITY_NAME;
    private const START = 1000;
    private const TRANSLATIONS = [
        'de-DE' => 'Lieferantenbestellungen',
        'en-GB' => 'Supplier orders',
    ];
    private const PATTERN = '{n}';

    public function __construct()
    {
        parent::__construct(self::TECHNICAL_NAME, self::PATTERN, self::START, self::TRANSLATIONS);
    }
}
