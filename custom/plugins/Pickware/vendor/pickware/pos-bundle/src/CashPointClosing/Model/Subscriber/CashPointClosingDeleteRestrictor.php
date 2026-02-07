<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing\Model\Subscriber;

use Pickware\DalBundle\EntityDeleteRestrictor;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingDefinition;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionDefinition;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionLineItemDefinition;
use Shopware\Core\Framework\Context;

/**
 * A delete restrictor class for all CashPointClosing entities.
 */
class CashPointClosingDeleteRestrictor extends EntityDeleteRestrictor
{
    public function __construct()
    {
        parent::__construct(
            [
                CashPointClosingDefinition::ENTITY_NAME,
                CashPointClosingTransactionDefinition::ENTITY_NAME,
                CashPointClosingTransactionLineItemDefinition::ENTITY_NAME,
            ],
            [
                Context::USER_SCOPE,
                Context::SYSTEM_SCOPE,
            ],
        );
    }
}
