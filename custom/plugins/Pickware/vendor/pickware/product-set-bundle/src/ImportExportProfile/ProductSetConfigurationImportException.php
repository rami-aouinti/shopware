<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\ImportExportProfile;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportExportException;

class ProductSetConfigurationImportException extends ImportExportException
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_PRODUCT_SET__PRODUCT_SET_IMPORTER__';
    public const ERROR_CODE_PRODUCT_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'PRODUCT_NOT_FOUND';

    public static function productNotFoundError(string $productNumber): JsonApiError
    {
        return new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_PRODUCT_NOT_FOUND,
            'title' => [
                'de' => 'Produkt nicht gefunden',
                'en' => 'Product not found',
            ],
            'detail' => [
                'de' => sprintf('Das Produkt mit der Nummer "%s" wurde nicht gefunden.', $productNumber),
                'en' => sprintf('The product with the number "%s" was not found.', $productNumber),
            ],
            'meta' => [
                'productNumber' => $productNumber,
            ],
        ]);
    }
}
