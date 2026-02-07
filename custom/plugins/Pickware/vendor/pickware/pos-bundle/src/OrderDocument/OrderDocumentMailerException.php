<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\OrderDocument;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class OrderDocumentMailerException extends Exception implements JsonApiErrorSerializable
{
    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function unsupportedDocumentType(string $documentTypeTechnicalName): self
    {
        $detail = sprintf(
            'The sending of documents of type "%s" via mail is not supported.',
            $documentTypeTechnicalName,
        );

        return new self(new JsonApiError([
            'title' => 'Unsupported document type',
            'detail' => $detail,
            'code' => 'PICKWARE_POS__ORDER_DOCUMENT_MAILING__UNSUPPORTED_DOCUMENT_TYPE',
            'meta' => [
                'documentTypeTechnicalName' => $documentTypeTechnicalName,
            ],
        ]));
    }

    public static function noMailTemplateExistsForDocumentType(string $documentTypeTechnicalName): self
    {
        $detail = sprintf(
            'The mail template for document of type "%s" does not exist anymore. Try to reinstall the plugin.',
            $documentTypeTechnicalName,
        );

        return new self(new JsonApiError([
            'title' => 'No mail template exists for document type',
            'detail' => $detail,
            'code' => 'PICKWARE_POS__ORDER_DOCUMENT_MAILING__NO_MAIL_TEMPLATE_EXISTS_FOR_DOCUMENT_TYPE',
            'meta' => [
                'documentTypeTechnicalName' => $documentTypeTechnicalName,
            ],
        ]));
    }

    public static function failedToRenderMailTemplate(
        string $mailTemplateId,
        string $orderId,
        string $mailTemplateTypeTechnicalName,
    ): self {
        $detail = sprintf(
            'The mail template with ID "%s" (type: "%s") failed to render for order with ID "%s".',
            $mailTemplateId,
            $mailTemplateTypeTechnicalName,
            $orderId,
        );

        return new self(new JsonApiError([
            'title' => 'Failed to render mail template',
            'detail' => $detail,
            'code' => 'PICKWARE_POS__ORDER_DOCUMENT_MAILING__FAILED_TO_RENDER_MAIL_TEMPLATE',
            'meta' => [
                'mailTemplateId' => $mailTemplateId,
                'orderId' => $orderId,
                'mailTemplateTypeTechnicalName' => $mailTemplateTypeTechnicalName,
            ],
        ]));
    }
}
