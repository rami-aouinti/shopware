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

use Pickware\UsageReportBundle\Model\UsageReportOrderType;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class UsageReportOrderTypeCollection
{
    /**
     * @var array<string, UsageReportOrderType> Map of orderId => orderType
     */
    private array $orderTypes = [];

    /**
     * @param array<string> $orderIds
     */
    public function __construct(array $orderIds)
    {
        foreach ($orderIds as $orderId) {
            $this->orderTypes[$orderId] = UsageReportOrderType::Regular;
        }
    }

    /**
     * @param array<string> $orderIds
     */
    public function setOrderType(UsageReportOrderType $orderType, array $orderIds): void
    {
        foreach ($orderIds as $orderId) {
            if (isset($this->orderTypes[$orderId])) {
                $this->orderTypes[$orderId] = $orderType;
            }
        }
    }

    /**
     * @return string[]
     */
    public function getOrderIdsByType(UsageReportOrderType $type): array
    {
        $result = [];
        foreach ($this->orderTypes as $orderId => $orderType) {
            if ($orderType === $type) {
                $result[] = $orderId;
            }
        }

        return $result;
    }
}
