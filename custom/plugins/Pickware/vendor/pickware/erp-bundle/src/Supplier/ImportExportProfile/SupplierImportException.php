<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\ImportExportProfile;

use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportExportException;

class SupplierImportException extends ImportExportException
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__SUPPLIER_IMPORTER__';
    private const ERROR_CODE_LANGUAGE_NOT_FOUND_BY_NAME = self::ERROR_CODE_NAMESPACE . 'LANGUAGE_NOT_FOUND_BY_NAME';
    private const ERROR_CODE_COUNTRY_NOT_FOUND_BY_ISO = self::ERROR_CODE_NAMESPACE . 'COUNTRY_NOT_FOUND_BY_ISO';

    public static function createLanguageNotFoundError(string $languageName): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_LANGUAGE_NOT_FOUND_BY_NAME,
            'title' => 'Language not found',
            'detail' => sprintf('The language with the name "%s" could not be found.', $languageName),
            'meta' => [
                'name' => $languageName,
            ],
        ]);
    }

    /**
     * @param string $isoCode ISO2 code of a country
     */
    public static function createCountryIsoNotFoundError(string $isoCode): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_COUNTRY_NOT_FOUND_BY_ISO,
            'title' => 'Country not found',
            'detail' => sprintf('The country with the iso code "%s" could not be found.', $isoCode),
            'meta' => [
                'isoCode' => $isoCode,
            ],
        ]);
    }
}
