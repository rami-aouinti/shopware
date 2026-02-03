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

use Countable;
use JsonSerializable;
use function Pickware\PhpStandardLibrary\Language\makeSentence;
use Shopware\Core\Framework\ShopwareException;
use Shopware\Core\Framework\ShopwareHttpException;
use Throwable;

class JsonApiErrors implements JsonSerializable, Countable
{
    /**
     * @var JsonApiError[]
     */
    private array $errors;

    public function __construct(array $errors = [])
    {
        $this->errors = $errors;
    }

    public static function noError(): self
    {
        return new self();
    }

    /**
     * Tries its best to convert any Throwable into JsonApiErrors.
     *
     * Exceptions that already return something similar or equal to a JsonApiError are converted into those.
     */
    public static function fromThrowable(Throwable $exception): self
    {
        if ($exception instanceof JsonApiErrorsSerializable) {
            return $exception->serializeToJsonApiErrors();
        }

        if ($exception instanceof JsonApiErrorSerializable) {
            return new self([$exception->serializeToJsonApiError()]);
        }

        if ($exception instanceof ShopwareHttpException) {
            $errors = iterator_to_array($exception->getErrors(false));

            return new JsonApiErrors(array_map(fn(array $error) => new JsonApiError($error), $errors));
        }

        $error = new JsonApiError([
            'title' => 'Internal Server Error',
            'detail' => $exception->getMessage(),
            'code' => ($exception->getCode()) ? ((string) $exception->getCode()) : null,
        ]);
        $previous = $exception->getPrevious();
        if ($previous) {
            $error->setMeta([
                'reasons' => self::fromThrowable($previous),
            ]);
        }

        if ($exception instanceof ShopwareException) {
            $error->setCode($exception->getErrorCode());
            $meta = $error->getMeta() ?? [];
            $meta['parameters'] = $exception->getParameters();
            $error->setMeta($meta);
        }

        return new JsonApiErrors([$error]);
    }

    public function jsonSerialize(): array
    {
        return $this->errors;
    }

    public function count(): int
    {
        return count($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    public function addError(JsonApiError $error): void
    {
        $this->errors[] = $error;
    }

    public function addErrors(JsonApiError ...$errors): void
    {
        foreach ($errors as $error) {
            $this->errors[] = $error;
        }
    }

    public function getCondensedStatus(): ?string
    {
        $statusCodes = array_map(fn(JsonApiError $error) => $error->getStatus() ? ((int) $error->getStatus()) : null, $this->errors);

        $statusCodes = array_values(array_unique(array_filter($statusCodes)));

        if (count($statusCodes) === 0) {
            return null;
        }
        if (count($statusCodes) === 1) {
            return (string) $statusCodes[0];
        }

        $highestStatusCode = max($statusCodes);

        $condensedStatusCode = ((int) floor($highestStatusCode / 100)) * 100;

        return (string) $condensedStatusCode;
    }

    public function getThrowableMessage(): string
    {
        return implode(' ', array_map(
            fn(JsonApiError $error) => $error->getDetail(),
            $this->errors,
        ));
    }

    public function getErrorDetailsAsConcatenatedSentences(): string
    {
        return implode(' ', array_map(
            fn(JsonApiError $error) => makeSentence($error->getDetail()),
            $this->errors,
        ));
    }

    public function toJsonApiErrorResponse(?int $status = null): JsonApiErrorResponse
    {
        return new JsonApiErrorResponse($this, $status);
    }
}
