<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Pickware\HttpUtils\JsonApiErrorFactory;

class CashPointClosingException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_POS__CASH_POINT_CLOSING__';
    public const TRANSACTIONS_MISSING = self::ERROR_CODE_NAMESPACE . 'TRANSACTIONS_MISSING';
    public const USER_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'USER_NOT_FOUND';
    public const CURRENCY_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'CURRENCY_NOT_FOUND';
    public const MULTIPLE_CURRENCIES_FOUND = self::ERROR_CODE_NAMESPACE . 'MULTIPLE_CURRENCIES_FOUND';
    public const INVALID_CASH_POINT_CLOSING_TRANSACTION_PAYLOAD = self::ERROR_CODE_NAMESPACE . 'INVALID_CASH_POINT_CLOSING_TRANSACTION_PAYLOAD';
    public const CASH_REGISTER_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'CASH_REGISTER_NOT_FOUND';
    public const CASH_POINT_CLOSING_DOES_NOT_MATCH_PREVIEW = self::ERROR_CODE_NAMESPACE . 'CASH_POINT_CLOSING_DOES_NOT_MATCH_PREVIEW';
    public const CASH_POINT_CLOSING_UNKNOWN_TAXRATE = self::ERROR_CODE_NAMESPACE . 'CASH_POINT_CLOSING_UNKNOWN_TAXRATE';

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

    public static function transactionsMissing(string $cashRegisterId): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::TRANSACTIONS_MISSING,
            'title' => 'No transactions found',
            'detail' => sprintf('No transactions have been found for cash register with id %s', $cashRegisterId),
            'meta' => ['cashRegisterId' => $cashRegisterId],
        ]);

        return new self($jsonApiError);
    }

    public static function userNotFound(string $userId): self
    {
        return new self(
            JsonApiErrorFactory::entityByIdNotFoundException(self::USER_NOT_FOUND, 'user', $userId),
        );
    }

    public static function currencyNotFound(string $currencyId): self
    {
        return new self(
            JsonApiErrorFactory::entityByIdNotFoundException(self::CURRENCY_NOT_FOUND, 'currency', $currencyId),
        );
    }

    public static function multipleCurrenciesFound(array $currencyIds): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::MULTIPLE_CURRENCIES_FOUND,
            'title' => 'Multiple currencies found in transactions for a single cash register',
            'detail' => sprintf(
                'Multiple currencies were found in transactions for a single cash register: %s',
                implode(', ', $currencyIds),
            ),
            'meta' => ['currencyIds' => $currencyIds],
        ]);

        return new self($jsonApiError);
    }

    public static function cashRegisterNotFound(string $cashRegisterId): self
    {
        return new self(JsonApiErrorFactory::entityByIdNotFoundException(
            self::CASH_REGISTER_NOT_FOUND,
            'cash register',
            $cashRegisterId,
        ));
    }

    public static function cashPointClosingChanged(): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::CASH_POINT_CLOSING_DOES_NOT_MATCH_PREVIEW,
            'title' => 'Cash point closing cannot be saved.',
            'detail' => 'The cash point closing has changed since the last preview (i.e. transactions were added or removed)',
        ]);

        return new self($jsonApiError);
    }

    public static function cashPointClosingUnknownTaxRate(float $taxRate): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::CASH_POINT_CLOSING_UNKNOWN_TAXRATE,
            'title' => 'Tax rate is unknown and could not be mapped to a Fiskaly VAT ID.',
            'detail' => sprintf(
                'The tax rate %s%% is unknown and could not be mapped to a Fiskaly VAT ID.',
                $taxRate,
            ),
            'meta' => ['taxRate' => $taxRate],
        ]);

        return new self($jsonApiError);
    }
}
