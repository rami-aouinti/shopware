<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareAccountBundle\ApiClient;

use Closure;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\TransferException;
use JsonException;
use Pickware\PhpStandardLibrary\Json\Json;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PickwareAccountApiErrorHandlingMiddleware
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
            throw PickwareAccountApiClientException::createInvalidResponseCodeError($response);
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
                    $pickwareAccountJsonApiErrors = $jsonResponse['errors'] ?? null;

                    if ($pickwareAccountJsonApiErrors && count($pickwareAccountJsonApiErrors) > 0) {
                        throw PickwareAccountApiClientException::createInvalidRequestError($pickwareAccountJsonApiErrors[0]);
                    }

                    throw PickwareAccountApiClientException::unknownError($reason);
                } catch (JsonException $exception) {
                    throw PickwareAccountApiClientException::unknownError($exception);
                }
            }

            throw PickwareAccountApiClientException::createInvalidResponseCodeError($reason->getResponse());
        }
        if ($reason instanceof TransferException) {
            throw PickwareAccountApiNotReachableError::pickwareAccountNotReachable($reason);
        }

        throw PickwareAccountApiClientException::unknownError($reason);
    }
}
