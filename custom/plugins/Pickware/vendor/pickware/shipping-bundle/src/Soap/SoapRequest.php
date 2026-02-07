<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Soap;

use SoapHeader;

class SoapRequest
{
    /**
     * @param SoapHeader[] $headers
     */
    public function __construct(
        private readonly string $method,
        private readonly array $body = [],
        private readonly array $headers = [],
    ) {}

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * @return SoapHeader[]
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function addingHeaders(array $headers): self
    {
        // Because of the possibility to override the soapRequest we need to use the methods instead of the properties
        // to not lose any information when adding additional headers.
        return new self($this->getMethod(), $this->getBody(), array_merge($this->getHeaders(), $headers));
    }
}
