<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\OrderDocument;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class OrderDocumentServiceException extends Exception implements JsonApiErrorSerializable
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_EXTENSIONS_BUNDLE__ORDER_DOCUMENT__';
    public const NO_DOCUMENT_GENERATED = self::ERROR_CODE_NAMESPACE . 'NO_DOCUMENT_GENERATED';

    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail());
    }

    public static function noDocumentGeneratedForOrder(string $orderId, ?string $message): self
    {
        return new self(new JsonApiError([
            'code' => self::NO_DOCUMENT_GENERATED,
            'title' => sprintf(
                'Document for order with ID=%s could not be generated.',
                $orderId,
            ),
            'detail' => $message ?? 'No document was generated for the order due to an unknown error.',
        ]));
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }
}
