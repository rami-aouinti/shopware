<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\InvoiceCorrection;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorsSerializable;

class InvoiceCorrectionException extends Exception implements JsonApiErrorsSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__INVOICE_CORRECTION__';
    public const ERROR_CODE_INVALID_DOCUMENT_CONFIGURATION = self::ERROR_CODE_NAMESPACE . 'INVALID_DOCUMENT_CONFIGURATION';
    public const ERROR_CODE_INVOICE_CORRECTION_WOULD_BE_EMPTY = self::ERROR_CODE_NAMESPACE . 'INVOICE_CORRECTION_WOULD_BE_EMPTY';
    public const ERROR_CODE_NO_REFERENCE_DOCUMENT_FOUND = self::ERROR_CODE_NAMESPACE . 'NO_REFERENCE_DOCUMENT_FOUND';
    public const ERROR_CODE_INVOICE_CORRECTION_FOR_INVOICE_EXISTS = self::ERROR_CODE_NAMESPACE . 'INVOICE_CORRECTION_FOR_INVOICE_EXISTS';

    public function __construct(private readonly JsonApiErrors $jsonApiErrors)
    {
        parent::__construct($this->jsonApiErrors->getThrowableMessage());
    }

    public function serializeToJsonApiErrors(): JsonApiErrors
    {
        return $this->jsonApiErrors;
    }

    public static function invalidDocumentConfiguration(string $message): self
    {
        return new self(new JsonApiErrors([
            new LocalizableJsonApiError([
                'code' => self::ERROR_CODE_INVALID_DOCUMENT_CONFIGURATION,
                'title' => [
                    'en' => 'Invalid document configuration',
                    'de' => 'Ungültige Dokumentenkonfiguration',
                ],
                'detail' => $message,
            ]),
        ]));
    }

    public static function invoiceCorrectionWouldBeEmpty(): self
    {
        return new self(new JsonApiErrors([
            new LocalizableJsonApiError([
                'code' => self::ERROR_CODE_INVOICE_CORRECTION_WOULD_BE_EMPTY,
                'title' => [
                    'en' => 'Invoice correction would be empty',
                    'de' => 'Rechnungskorrektur wäre leer',
                ],
                'detail' => [
                    'en' => 'The invoice correction would be empty.',
                    'de' => 'Die Rechnungskorrektur wäre leer.',
                ],
            ]),
        ]));
    }

    public static function noReferenceDocumentFound(): self
    {
        return new self(new JsonApiErrors([
            new LocalizableJsonApiError([
                'code' => self::ERROR_CODE_NO_REFERENCE_DOCUMENT_FOUND,
                'title' => [
                    'en' => 'No referenceable invoice document found',
                    'de' => 'Kein referenzierbares Rechnungsdokument gefunden',
                ],
                'detail' => [
                    'en' => 'No latest invoice correction or referenceable invoice, that has not been cancelled yet, could be found.',
                    'de' => 'Es konnte keine aktuelle Rechnungskorrektur oder referenzierbare Rechnung gefunden werden, die noch nicht storniert wurde.',
                ],
            ]),
        ]));
    }

    public static function invoiceCorrectionForInvoiceExists(string $invoiceNumber): self
    {
        return new self(new JsonApiErrors([
            new LocalizableJsonApiError([
                'code' => self::ERROR_CODE_INVOICE_CORRECTION_FOR_INVOICE_EXISTS,
                'title' => [
                    'en' => 'An invoice correction exists for the invoice',
                    'de' => 'Für diese Rechnung existiert bereits eine Rechnungskorrektur',
                ],
                'detail' => [
                    'en' => sprintf(
                        'At least one invoice correction exists for the invoice with number "%s".',
                        $invoiceNumber,
                    ),
                    'de' => sprintf(
                        'Für die Rechnung mit der Nummer "%s" existiert mindestens eine Rechnungskorrektur.',
                        $invoiceNumber,
                    ),
                ],
            ]),
        ]));
    }

    public static function noInvoiceCorrectionReferenceInformationFound(string $documentId): self
    {
        return new self(new JsonApiErrors([
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'No invoice correction reference information found',
                    'de' => 'Keine Referenzinformationen für die Rechnungskorrektur gefunden',
                ],
                'detail' => [
                    'en' => sprintf(
                        'No invoice correction reference information could be found for existing document "%s".',
                        $documentId,
                    ),
                    'de' => sprintf(
                        'Für das existierende Dokument "%s" konnten keine Referenzinformationen für die Rechnungskorrektur gefunden werden.',
                        $documentId,
                    ),
                ],
            ]),
        ]));
    }

    public static function orderCalculationFailed(string $orderId, string $reason): self
    {
        return new self(new JsonApiErrors([
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'Order calculation for invoice correction failed',
                    'de' => 'Bestellberechnung für Rechnungskorrektur fehlgeschlagen',
                ],
                'detail' => [
                    'en' => sprintf(
                        'The order calculation for invoice correction failed. Reason: %s',
                        $reason,
                    ),
                    'de' => sprintf(
                        'Die Bestellberechnung für Rechnungskorrektur fehlgeschlagen. Grund: %s',
                        $reason,
                    ),
                ],
                'meta' => [
                    'orderId' => $orderId,
                ],
            ]),
        ]));
    }
}
