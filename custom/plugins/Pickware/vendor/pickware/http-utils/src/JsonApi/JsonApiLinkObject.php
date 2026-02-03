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
 * A “link object” is an object that represents a web link.
 *
 * Note: This class is post-fixed with "object" because a "link" is defined by the JSON API standard as
 * null|string|“link object”.
 *
 * @link https://jsonapi.org/format/#auto-id--link-objects
 */
class JsonApiLinkObject implements JsonSerializable
{
    /**
     * A string whose value is a URI-reference [RFC3986 Section 4.1] pointing to the link’s target.
     * @link https://datatracker.ietf.org/doc/html/rfc3986#section-4.1
     */
    private readonly string $href;

    /**
     * A string indicating the link’s relation type. The string MUST be a valid link relation type.
     * @link https://datatracker.ietf.org/doc/html/rfc8288#section-2.1
     */
    private readonly ?string $rel;

    /**
     * A link to a description document (e.g. OpenAPI or JSON Schema) for the link target.
     */
    private readonly null|string|self $describedby;

    /**
     * A string which serves as a label for the destination of a link such that it can be used as a human-readable
     * identifier (e.g., a menu entry).
     */
    private readonly ?string $title;

    /**
     * A string indicating the media type of the link’s target.
     */
    private readonly ?string $type;

    /**
     * A string or an array of strings indicating the language(s) of the link’s target. An array of strings
     * indicates that the link’s target is available in multiple languages. Each string MUST be a valid language tag
     * [RFC5646].
     * @link https://datatracker.ietf.org/doc/html/rfc5646
     */
    private readonly null|string|array $hreflang;

    /**
     * A meta object containing non-standard meta-information about the link.
     */
    private readonly ?array $meta;

    public function __construct(
        string $href,
        ?string $rel = null,
        JsonApiLinkObject|string|null $describedby = null,
        ?string $title = null,
        ?string $type = null,
        array|string|null $hreflang = null,
        ?array $meta = null,
    ) {
        $this->href = $href;
        $this->rel = $rel;
        $this->describedby = $describedby;
        $this->title = $title;
        $this->type = $type;
        $this->hreflang = $hreflang;
        $this->meta = $meta;
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'href' => $this->href,
            'rel' => $this->rel,
            'describedby' => $this->describedby,
            'title' => $this->title,
            'type' => $this->type,
            'hreflang' => $this->hreflang,
            'meta' => $this->meta,
        ], fn($value) => $value !== null);
    }

    public static function fromArray(array $data): self
    {
        $describedby = $data['describedby'] ?? null;

        return new self(
            href: $data['href'],
            rel: $data['rel'] ?? null,
            describedby: is_array($describedby) ? self::fromArray($describedby) : $describedby,
            title: $data['title'] ?? null,
            type: $data['type'] ?? null,
            hreflang: $data['hreflang'] ?? null,
            meta: $data['meta'] ?? null,
        );
    }

    public function getHref(): string
    {
        return $this->href;
    }

    public function getRel(): ?string
    {
        return $this->rel;
    }

    public function getDescribedby(): JsonApiLinkObject|string|null
    {
        return $this->describedby;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getHreflang(): array|string|null
    {
        return $this->hreflang;
    }

    public function getMeta(): ?array
    {
        return $this->meta;
    }
}
