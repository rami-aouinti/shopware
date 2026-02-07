<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\HttpUtils;

use Exception;
use LogicException;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSource;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ResponseFactory
{
    public static function createInvalidPosContentType($expectedContentType): JsonResponse
    {
        return (new JsonApiError([
            'status' => (string) Response::HTTP_BAD_REQUEST,
            'title' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
            'detail' => sprintf('The POST content type is invalid. Expected: %s.', $expectedContentType),
        ]))->toJsonApiErrorResponse();
    }

    public static function createEmptyPostContentResponse(): JsonResponse
    {
        return (new JsonApiError([
            'status' => (string) Response::HTTP_BAD_REQUEST,
            'title' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
            'detail' => 'The POST content is empty.',
        ]))->toJsonApiErrorResponse();
    }

    public static function createParameterMissingResponse(string $parameterName): JsonResponse
    {
        return (new JsonApiError([
            'status' => (string) Response::HTTP_BAD_REQUEST,
            'title' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
            'detail' => sprintf('Parameter "%s" is missing.', $parameterName),
        ]))->toJsonApiErrorResponse();
    }

    public static function createParameterInvalidValueResponse(string $parameterName, ?Exception $exception): JsonResponse
    {
        return (new JsonApiError([
            'status' => (string) Response::HTTP_BAD_REQUEST,
            'title' => 'Invalid value for parameter.',
            'detail' => $exception ? $exception->getMessage() : sprintf('Parameter "%s" is has an invalid value.', $parameterName),
            'source' => new JsonApiErrorSource(['parameter' => $parameterName]),
        ]))->toJsonApiErrorResponse();
    }

    public static function createNumericParameterMissingResponse(string $parameterName): JsonResponse
    {
        return (new JsonApiError([
            'status' => (string) Response::HTTP_BAD_REQUEST,
            'title' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
            'detail' => sprintf('Parameter "%s" is missing or not a number.', $parameterName),
        ]))->toJsonApiErrorResponse();
    }

    public static function createUuidParameterMissingResponse(string $parameterName): JsonResponse
    {
        return (new JsonApiError([
            'status' => (string) Response::HTTP_BAD_REQUEST,
            'title' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
            'detail' => sprintf('Parameter %s is missing or is not a UUID.', $parameterName),
        ]))->toJsonApiErrorResponse();
    }

    public static function createFormattedParameterMissingResponse(string $parameterName, string $requiredFormat): JsonResponse
    {
        return (new JsonApiError([
            'status' => (string) Response::HTTP_BAD_REQUEST,
            'title' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
            'detail' => sprintf(
                'Parameter %s is missing or is not in the required format: "%s".',
                $parameterName,
                $requiredFormat,
            ),
        ]))->toJsonApiErrorResponse();
    }

    /**
     * @param string[] $supportedSourceClassName
     */
    public static function createUnsupportedContextSourceResponse(
        string $actualSourceClassName,
        array $supportedSourceClassName,
    ): JsonResponse {
        return (new JsonApiError([
            'status' => (string) Response::HTTP_BAD_REQUEST,
            'title' => 'Context source not supported',
            'detail' => sprintf(
                'Context source "%s" is not supported. Supported context sources: "%s".',
                $actualSourceClassName,
                implode(', ', $supportedSourceClassName),
            ),
        ]))->toJsonApiErrorResponse();
    }

    public static function createNotFoundResponse(): JsonResponse
    {
        return (new JsonApiError([
            'status' => (string) Response::HTTP_NOT_FOUND,
            'title' => Response::$statusTexts[Response::HTTP_NOT_FOUND],
        ]))->toJsonApiErrorResponse();
    }

    public static function createFileUploadErrorResponse(UploadedFile $file, string $parameterName): JsonResponse
    {
        if ($file->isValid()) {
            throw new LogicException(sprintf(
                'The method %s can only be called with a file for which the upload failed.',
                __METHOD__,
            ));
        }

        return (new JsonApiError([
            'status' => (string) Response::HTTP_BAD_REQUEST,
            'title' => 'File upload has failed.',
            'detail' => $file->getErrorMessage(),
            'source' => new JsonApiErrorSource(['parameter' => $parameterName]),
            'meta' => [
                'fileName' => $file->getClientOriginalName(),
            ],
        ]))->toJsonApiErrorResponse();
    }

    public static function createIdMissingForIdempotentCreationResponse(string $entityName): JsonResponse
    {
        return (new JsonApiError([
            'status' => (string) Response::HTTP_BAD_REQUEST,
            'title' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
            'detail' => sprintf(
                'No ID for the creation of entity "%s" was specified. Please pass an ID to ensure ' .
                'idempotency of this action.',
                $entityName,
            ),
        ]))->toJsonApiErrorResponse();
    }
}
