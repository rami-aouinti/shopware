<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\Product;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorsSerializable;
use Throwable;

class ProductException extends Exception implements JsonApiErrorsSerializable
{
    public function __construct(public readonly JsonApiErrors $jsonApiErrors, ?Throwable $previous = null)
    {
        parent::__construct(
            message: $this->jsonApiErrors->getThrowableMessage(),
            previous: $previous,
        );
    }

    public function serializeToJsonApiErrors(): JsonApiErrors
    {
        return $this->jsonApiErrors;
    }

    /**
     * @param list<string> $productIds
     */
    public static function productsDoNotExist(array $productIds): self
    {
        return new self(
            jsonApiErrors: new JsonApiErrors(
                array_map(fn(string $id) => new LocalizableJsonApiError([
                    'detail' => [
                        'de' => "Das Produkt mit der ID {$id} existiert nicht.",
                        'en' => "The product with the ID {$id} does not exist.",
                    ],
                    'meta' => ['productId' => $id],
                ]), $productIds),
            ),
        );
    }
}
