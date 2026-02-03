<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\GoodsReceipt;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;

class GoodsReceiptError
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_WMS__GOODS_RECEIPT__';
    public const GOODS_RECEIPT_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'GOODS_RECEIPT_NOT_FOUND';

    public static function goodsReceiptNotFound(string $goodsReceiptId): JsonApiError
    {
        return new LocalizableJsonApiError([
            'code' => self::GOODS_RECEIPT_NOT_FOUND,
            'title' => [
                'en' => 'Goods receipt not found',
                'de' => 'Wareneingang nicht gefunden',
            ],
            'detail' => [
                'en' => sprintf('The goods receipt with ID "%s" was not found.', $goodsReceiptId),
                'de' => sprintf('Der Wareneingang mit der ID "%s" wurde nicht gefunden.', $goodsReceiptId),
            ],
            'meta' => ['goodsReceiptId' => $goodsReceiptId],
        ]);
    }
}
