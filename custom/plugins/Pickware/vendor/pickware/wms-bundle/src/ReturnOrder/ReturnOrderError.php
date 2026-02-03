<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\ReturnOrder;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;

class ReturnOrderError
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_WMS__RETURN_ORDER__';
    public const CODE_INCOMPATIBLE_PICKWARE_ERP_STARTER_INSTALLED = self::ERROR_CODE_NAMESPACE . 'INCOMPATIBLE_PICKWARE_ERP_STARTER_INSTALLED';

    public static function incompatiblePickwareErpStarterInstalled(): JsonApiError
    {
        return new LocalizableJsonApiError([
            'code' => self::CODE_INCOMPATIBLE_PICKWARE_ERP_STARTER_INSTALLED,
            'title' => [
                'en' => 'Incompatible version of Pickware ERP installed',
                'de' => 'Inkompatible Version von Pickware ERP installiert',
            ],
            'detail' => [
                'en' => 'The installed version of "Pickware ERP" does not support this function. Please update the ' .
                    'plugin "Pickware ERP".',
                'de' => 'Die installierte Version von "Pickware ERP" unterstützt diese Funktion nicht. Bitte ' .
                    'aktualisiere das Plugin "Pickware ERP".',
            ],
        ]);
    }
}
