<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\HttpUtils\JsonApi;

use JsonSerializable;

/**
 * An object containing references to the source of the error
 */
class JsonApiErrorSource implements JsonSerializable
{
    /**
     * A JSON Pointer [RFC6901] to the associated entity in the request document
     * [e.g. "/data" for a primary data object, or "/data/attributes/title" for a specific attribute].
     */
    private ?string $pointer;

    /**
     * A string indicating which URI query parameter caused the error.
     */
    private ?string $parameter;

    public function __construct(array $properties = [])
    {
        $this->setPointer($properties['pointer'] ?? null);
        $this->setParameter($properties['parameter'] ?? null);
    }

    public function jsonSerialize(): array
    {
        return array_filter(get_object_vars($this), fn($value) => $value !== null);
    }

    public function getPointer(): ?string
    {
        return $this->pointer;
    }

    public function setPointer(?string $pointer): void
    {
        $this->pointer = $pointer;
    }

    public function getParameter(): ?string
    {
        return $this->parameter;
    }

    public function setParameter(?string $parameter): void
    {
        $this->parameter = $parameter;
    }
}
