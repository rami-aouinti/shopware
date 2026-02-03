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
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;

/**
 * This is an implementation of the error object of the JSON:API standard. Please do not add unrelated properties or
 * methods to this class and keep the properties according to the standard.
 *
 * Error objects provide additional information about problems encountered while performing an operation. Error objects
 * MUST be returned as an array keyed by errors in the top level of a JSON:API document.
 *
 * @link https://jsonapi.org/format/#errors
 */
class JsonApiError implements JsonSerializable
{
    /**
     * A unique identifier for this particular occurrence of the problem.
     */
    private mixed $id = null;

    /**
     * The HTTP status code applicable to this problem, expressed as an string value.
     */
    private ?string $status = null;

    /**
     * An application-specific error code, expressed as a string value.
     */
    private ?string $code = null;

    /**
     * A short, human-readable summary of the problem that SHOULD NOT change from occurrence to
     * occurrence of the problem, except for purposes of localization.
     */
    private ?string $title = null;

    /**
     * A human-readable explanation specific to this occurrence of the problem. Like title, this field's
     *  value can be localized.
     */
    private ?string $detail = null;

    private ?JsonApiErrorLinks $links = null;
    private ?JsonApiErrorSource $source = null;
    private ?array $meta = null;

    /**
     * @param array{
     *     id?: mixed,
     *     links?: JsonApiErrorLinks|null,
     *     status?: string|int|null,
     *     code?: string|null,
     *     title?: string|null,
     *     detail?: string|null,
     *     source?: JsonApiErrorSource|null,
     *     meta?: array<string, mixed>|null,
     * } $properties
     */
    public function __construct(array $properties = [])
    {
        if ((is_array($properties['title'] ?? null) || (is_array($properties['detail'] ?? null)))) {
            trigger_error(
                sprintf(
                    'Constructing a %s with an array for title or detail is deprecated. Use %s::__construct instead.',
                    self::class,
                    LocalizableJsonApiError::class,
                ),
                E_USER_DEPRECATED,
            );
            if (class_exists(LocalizableJsonApiError::class)) {
                // Just a save guard, but the class should always exist.
                $localizableJsonApiError = new LocalizableJsonApiError($properties);
                $this->id = $localizableJsonApiError->id;
                $this->links = $localizableJsonApiError->links;
                $this->status = $localizableJsonApiError->status;
                $this->code = $localizableJsonApiError->code;
                $this->title = $localizableJsonApiError->title;
                $this->detail = $localizableJsonApiError->detail;
                $this->source = $localizableJsonApiError->source;
                $this->meta = $localizableJsonApiError->meta;

                return;
            }

            $properties['title'] = $properties['title']['en'] ?? $properties['title'] ?? null;
            $properties['detail'] = $properties['detail']['en'] ?? $properties['detail'] ?? null;
        }

        $links = $properties['links'] ?? null;
        if (is_array($links)) {
            $links = JsonApiErrorLinks::fromArray($links);
        }
        $this->setId($properties['id'] ?? null);
        $this->setLinks($links);
        $this->setStatus($properties['status'] ?? null);
        $this->setCode($properties['code'] ?? null);
        $this->setTitle($properties['title'] ?? null);
        $this->setDetail($properties['detail'] ?? null);
        $this->setSource($properties['source'] ?? null);
        $this->setMeta($properties['meta'] ?? null);
    }

    public function jsonSerialize(): array
    {
        return array_filter(get_object_vars($this), fn($value) => $value !== null);
    }

    public static function fromArray(array $array): static
    {
        $source = $array['source'] ?? null;
        if (is_array($source)) {
            $array['source'] = new JsonApiErrorSource($array['source']);
        }

        return new static($array);
    }

    /**
     * @deprecated Removed with 5.x. Use fromArray() instead
     */
    public static function createFromJsonArray(array $array): static
    {
        return static::fromArray($array);
    }

    /**
     * @deprecated tag:next-major Will be removed with next major because JsonApiError does not implement JsonApiErrorSerializable anymore.
     */
    public function serializeToJsonApiError(): JsonApiError
    {
        return $this;
    }

    /**
     * Returns a JsonApiErrorResponse with (only) this JsonApiError. If a new status code was given, this JsonApiError
     * status code is updated an in turn the code of the returned response.
     */
    public function toJsonApiErrorResponse(?int $status = null): JsonApiErrorResponse
    {
        if ($status) {
            $this->setStatus($status);
        }

        return (new JsonApiErrors([$this]))->toJsonApiErrorResponse();
    }

    public function getId(): mixed
    {
        return $this->id;
    }

    public function setId(mixed $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getLinks(): ?JsonApiErrorLinks
    {
        return $this->links;
    }

    public function setLinks(?JsonApiErrorLinks $links): self
    {
        $this->links = $links;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(null|int|string $status): self
    {
        $this->status = ($status !== null) ? (string) $status : null;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function setDetail(?string $detail): self
    {
        $this->detail = $detail;

        return $this;
    }

    public function getSource(): ?JsonApiErrorSource
    {
        return $this->source;
    }

    public function setSource(?JsonApiErrorSource $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getMeta(): ?array
    {
        return $this->meta;
    }

    public function setMeta(?array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }
}
