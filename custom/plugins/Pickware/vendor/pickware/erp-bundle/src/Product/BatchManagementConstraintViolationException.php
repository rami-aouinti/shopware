<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Product;

use Doctrine\DBAL\Exception as DBALException;
use Pickware\ApiErrorHandlingBundle\ControllerExceptionHandling\ApiControllerHandlable;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\ShopwareExtensionsBundle\Product\ProductException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BatchManagementConstraintViolationException extends ProductException implements ApiControllerHandlable
{
    public static function batchManagementCannotBeEnabledWhenStockManagementIsDisabled(
        DBALException $dbalException,
    ): self {
        return new self(
            jsonApiErrors: new JsonApiErrors([
                new LocalizableJsonApiError([
                    'detail' => [
                        'de' => 'Die Chargenverwaltung kann nicht aktiv sein, während die Lagerverwaltung deaktiviert ist.',
                        'en' => 'Batch management cannot be enabled when stock management is disabled.',
                    ],
                ]),
            ]),
            previous: $dbalException,
        );
    }

    public function handleForApiController(): JsonResponse
    {
        return $this->jsonApiErrors->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
    }
}
