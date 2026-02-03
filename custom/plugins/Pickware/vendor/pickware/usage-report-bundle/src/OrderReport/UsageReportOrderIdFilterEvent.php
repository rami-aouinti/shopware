<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\OrderReport;

use Shopware\Core\Framework\Context;

class UsageReportOrderIdFilterEvent
{
    private readonly UsageReportOrderTypeCollection $orderTypeCollection;

    /**
     * @param array<string> $orderIds
     */
    public function __construct(
        array $orderIds,
        private readonly Context $context,
    ) {
        $this->orderTypeCollection = new UsageReportOrderTypeCollection($orderIds);
    }

    public function getOrderTypeCollection(): UsageReportOrderTypeCollection
    {
        return $this->orderTypeCollection;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
