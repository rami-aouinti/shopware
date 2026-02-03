<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockMovementProcess;

use Pickware\PickwareErpStarter\StockMovementProcess\Model\StockMovementProcessType;

class AutomaticallyShippedStockMovementProcessType extends StockMovementProcessType
{
    public const TECHNICAL_NAME = 'shipped_automatically';

    public function __construct()
    {
        parent::__construct(
            technicalName: self::TECHNICAL_NAME,
            referencedEntityFieldName: 'orders',
            referencedEntityDefinitionClass: 'Shopware\\Core\\Checkout\\Order\\OrderDefinition',
        );
    }
}
