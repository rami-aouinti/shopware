<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle\ExceptionHandling;

use Exception;
use Pickware\ApiErrorHandlingBundle\ControllerExceptionHandling\ApiControllerHandlable;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorsSerializable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UniqueIndexHttpException extends Exception implements ApiControllerHandlable, JsonApiErrorsSerializable
{
    private readonly JsonApiErrors $jsonApiErrors;

    public function __construct(
        JsonApiError $jsonApiError,
        ?Throwable $previous = null,
    ) {
        $this->jsonApiErrors = new JsonApiErrors([$jsonApiError]);

        parent::__construct(
            message: $this->jsonApiErrors->getThrowableMessage(),
            previous: $previous,
        );
    }

    public function getErrorCode(): string
    {
        return $this->jsonApiErrors->getErrors()[0]->getCode();
    }

    public function serializeToJsonApiErrors(): JsonApiErrors
    {
        return $this->jsonApiErrors;
    }

    public static function create(
        UniqueIndexExceptionMapping $uniqueIndexExceptionMapping,
        Throwable $previousException,
    ): self {
        return new self(
            new LocalizableJsonApiError([
                'code' => $uniqueIndexExceptionMapping->getErrorCodeToAssign(),
                'detail' => $uniqueIndexExceptionMapping->getDetailMessage()?->jsonSerialize() ?? [
                    'en' => vsprintf(
                        'Entity "%s" with given fields (%s) already exists.',
                        [
                            $uniqueIndexExceptionMapping->getEntityName(),
                            implode(', ', $uniqueIndexExceptionMapping->getFields()),
                        ],
                    ),
                    'de' => vsprintf(
                        'Entität "%s" mit den Feldern (%s) existiert bereits.',
                        [
                            $uniqueIndexExceptionMapping->getEntityName(),
                            implode(', ', $uniqueIndexExceptionMapping->getFields()),
                        ],
                    ),
                ],
                'meta' => [
                    'index' => $uniqueIndexExceptionMapping->getUniqueIndexName(),
                    'entity' => $uniqueIndexExceptionMapping->getEntityName(),
                    'fields' => $uniqueIndexExceptionMapping->getFields(),
                ],
            ]),
            $previousException,
        );
    }

    /**
     * @param string $primaryKeyIdentifier e.g. "pickware_wms_picking_process.PRIMARY"
     */
    public static function createForPrimaryKeyConstraintViolation(
        string $primaryKeyIdentifier,
        Throwable $previousException,
    ): self {
        return new self(
            new LocalizableJsonApiError([
                'detail' => [
                    'en' => sprintf(
                        'Entry could not be written. Primary key constraint violation for key "%s".',
                        $primaryKeyIdentifier,
                    ),
                    'de' => sprintf(
                        'Eintrag konnte nicht gespeichert werden. Primärschlüsselverletzung für Schlüssel "%s".',
                        $primaryKeyIdentifier,
                    ),
                ],
                'meta' => [
                    'primaryKeyIdentifier' => $primaryKeyIdentifier,
                ],
            ]),
            $previousException,
        );
    }

    public function handleForApiController(): JsonResponse
    {
        return $this->jsonApiErrors->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
    }
}
