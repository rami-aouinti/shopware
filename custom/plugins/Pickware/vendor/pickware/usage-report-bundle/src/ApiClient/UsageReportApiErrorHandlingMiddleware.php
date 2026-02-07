<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\ApiClient;

use Closure;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\TransferException;
use JsonException;
use Pickware\PhpStandardLibrary\Json\Json;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UsageReportApiErrorHandlingMiddleware
{
    public function __invoke(callable $handler): Closure
    {
        return fn(RequestInterface $request, array $options) => $handler($request, $options)->then(
            $this->onSuccess(...),
            $this->onFailure(...),
        );
    }

    public function onSuccess(ResponseInterface $response): ResponseInterface
    {
        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw UsageReportApiClientException::createInvalidResponseCodeError($response);
        }

        return $response;
    }

    public function onFailure(Throwable $reason): void
    {
        if ($reason instanceof BadResponseException) {
            if ($reason->getCode() === Response::HTTP_BAD_REQUEST) {
                $response = $reason->getResponse();
                try {
                    $jsonResponse = Json::decodeToArray((string)$response->getBody());
                    $usageReportJsonApiErrors = $jsonResponse['errors'] ?? null;

                    if ($usageReportJsonApiErrors && count($usageReportJsonApiErrors) > 0) {
                        throw UsageReportApiClientException::createInvalidRequestError($usageReportJsonApiErrors[0]);
                    }

                    throw UsageReportApiClientException::unknownError($reason);
                } catch (JsonException $exception) {
                    throw UsageReportApiClientException::unknownError($exception);
                }
            }

            throw UsageReportApiClientException::createInvalidResponseCodeError($reason->getResponse());
        }
        if ($reason instanceof TransferException) {
            throw UsageReportApiNotReachableError::pickwareAccountNotReachable($reason);
        }

        throw UsageReportApiClientException::unknownError($reason);
    }
}
