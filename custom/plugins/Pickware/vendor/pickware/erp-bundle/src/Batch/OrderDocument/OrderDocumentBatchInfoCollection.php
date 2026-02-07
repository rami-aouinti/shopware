<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch\OrderDocument;

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;

/**
 * @extends ImmutableCollection<OrderDocumentBatchInfo>
 */
class OrderDocumentBatchInfoCollection extends ImmutableCollection
{
    public function getTotalQuantity(): int
    {
        return $this->map(fn(OrderDocumentBatchInfo $info) => $info->quantity)->sum();
    }

    /**
     * @return list<array{batchNumber: ?string, bestBeforeDate: ?string, quantity: int}>
     */
    public function toPayload(): array
    {
        return $this->map(fn(OrderDocumentBatchInfo $info) => [
            'batchNumber' => $info->batchNumber,
            'bestBeforeDate' => $info->bestBeforeDate,
            'quantity' => $info->quantity,
        ])->asArray();
    }
}
