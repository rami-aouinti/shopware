<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Rest;

use Closure;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Promise\Create;
use Psr\Http\Message\RequestInterface;
use Throwable;

class BadResponseExceptionHandlingMiddleware
{
    /**
     * This handler must take a {@link ClientException} as an argument and return a {@link Throwable}.
     *
     * @var callable
     */
    protected $clientExceptionHandler;

    /**
     * This handler must take a {@link ServerException} as an argument and return a {@link Throwable}.
     *
     * @var callable
     */
    protected $serverExceptionHandler;

    public function __construct(callable $clientExceptionHandler, callable $serverExceptionHandler)
    {
        $this->clientExceptionHandler = $clientExceptionHandler;
        $this->serverExceptionHandler = $serverExceptionHandler;
    }

    public function __invoke(callable $handler): Closure
    {
        return fn(RequestInterface $request, array $options) => $handler($request, $options)->then(
            null,
            $this->onFailure(),
        );
    }

    protected function onFailure(): Closure
    {
        return function(Throwable $reason) {
            if ($reason instanceof ClientException) {
                return Create::rejectionFor(($this->clientExceptionHandler)($reason));
            }

            if ($reason instanceof ServerException) {
                return Create::rejectionFor(($this->serverExceptionHandler)($reason));
            }

            return Create::rejectionFor($reason);
        };
    }
}
