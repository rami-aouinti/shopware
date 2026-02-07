<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\MailDraft;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Throwable;

class MailDraftException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__MAIL__';
    public const INVALID_MAIL_TEMPLATE_CONTENT_GENERATOR_OPTION = self::ERROR_CODE_NAMESPACE . 'INVALID_MAIL_TEMPLATE_CONTENT_GENERATOR_OPTION';
    public const MAIL_ATTACHMENT_DOCUMENT_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'MAIL_ATTACHMENT_DOCUMENT_NOT_FOUND';

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

    public static function invalidTemplateContentGeneratorOption($optionName): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::INVALID_MAIL_TEMPLATE_CONTENT_GENERATOR_OPTION,
            'title' => 'Invalid mail template content generator option',
            'detail' => sprintf(
                'The value for the mail template content generator option "%s" is missing or invalid.',
                $optionName,
            ),
            'meta' => ['optionName' => $optionName],
        ]);

        return new self($jsonApiError);
    }

    public static function attachmentDocumentNotFound(string $documentId): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::MAIL_ATTACHMENT_DOCUMENT_NOT_FOUND,
            'title' => 'Mail attachment document not found',
            'detail' => sprintf('No document was found with id %s', $documentId),
            'meta' => ['documentId' => $documentId],
        ]);

        return new self($jsonApiError);
    }

    public static function attachmentFileNotFound(string $fileName): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::MAIL_ATTACHMENT_DOCUMENT_NOT_FOUND,
            'title' => 'Mail attachment document file not found',
            'detail' => sprintf('No file was found with name %s', $fileName),
            'meta' => ['fileName' => $fileName],
        ]);

        return new self($jsonApiError);
    }

    public static function htmlParsingFailed(Throwable $parsingError): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'Mail draft content could not be parsed',
                    'de' => 'Der Inhalt des E-Mail-Entwurfs konnte nicht geparst werden',
                ],
                'detail' => [
                    'en' => sprintf(
                        'The HTML content of the mail draft could not be parsed: %s',
                        $parsingError->getMessage(),
                    ),
                    'de' => sprintf(
                        'Der HTML-Inhalt des E-Mail-Entwurfs konnte nicht geparst werden: %s',
                        $parsingError->getMessage(),
                    ),
                ],
                'meta' => [
                    'message' => $parsingError->getMessage(),
                ],
            ]),
        );
    }
}
