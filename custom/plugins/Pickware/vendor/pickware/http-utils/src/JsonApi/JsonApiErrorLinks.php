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
 * a links object containing the following members:
 * - "about": a link that leads to further details about this particular occurrence of the problem. When derefenced,
 *   this URI SHOULD return a human-readable description of the error.
 * - type: a link that identifies the type of error that this particular error is an instance of. This URI SHOULD be
 *   dereferencable to a human-readable explanation of the general error.
 */
class JsonApiErrorLinks implements JsonSerializable
{
    /**
     * a link that leads to further details about this particular occurrence of the problem. When derefenced, this URI
     * SHOULD return a human-readable description of the error.
     */
    private readonly string|JsonApiLinkObject|null $about;

    /**
     * a link that identifies the type of error that this particular error is an instance of. This URI SHOULD be
     * dereferencable to a human-readable explanation of the general error.
     */
    private readonly string|JsonApiLinkObject|null $type;

    public function __construct(string|JsonApiLinkObject|null $about = null, string|JsonApiLinkObject|null $type = null)
    {
        $this->about = $about;
        $this->type = $type;
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'about' => $this->about,
            'type' => $this->type,
        ], fn($value) => $value !== null);
    }

    public static function fromArray(array $data): self
    {
        $about = $data['about'] ?? null;
        $type = $data['type'] ?? null;

        return new self(
            about: is_array($about) ? JsonApiLinkObject::fromArray($about) : $about,
            type: is_array($type) ? JsonApiLinkObject::fromArray($type) : $type,
        );
    }

    public function getAbout(): string|JsonApiLinkObject|null
    {
        return $this->about;
    }

    public function getType(): string|JsonApiLinkObject|null
    {
        return $this->type;
    }
}
