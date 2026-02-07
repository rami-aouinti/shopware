<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Coupon;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;

class CouponException extends Exception implements JsonApiErrorSerializable
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_POS__COUPON__';
    public const UNSUPPORTED_COUPON_TYPE = self::ERROR_CODE_NAMESPACE . 'UNSUPPORTED_COUPON_TYPE';
    public const UNSUPPORTED_TAXES = self::ERROR_CODE_NAMESPACE . 'UNSUPPORTED_TAXES';
    public const NOT_PAID = self::ERROR_CODE_NAMESPACE . 'NOT_PAID';
    public const AMBIGUOUS_COUPON_CODE = self::ERROR_CODE_NAMESPACE . 'AMBIGUOUS_COUPON_CODE';
    public const DISCOUNT_MISSING = self::ERROR_CODE_NAMESPACE . 'DISCOUNT_MISSING';
    public const ONLY_ONE_DISCOUNT_ALLOWED = self::ERROR_CODE_NAMESPACE . 'ONLY_ONE_DISCOUNT_ALLOWED';
    public const INVALID_DISCOUNT_SCOPE = self::ERROR_CODE_NAMESPACE . 'INVALID_DISCOUNT_SCOPE';

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

    public static function ambiguousCouponCode(string $code, array $coupons): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::AMBIGUOUS_COUPON_CODE,
                'title' => [
                    'en' => 'Ambiguous coupon code',
                    'de' => 'Mehrdeutiger Gutscheincode',
                ],
                'detail' => [
                    'en' => 'The coupon code cannot be uniquely assigned to any coupon because multiple coupons were found for this code.',
                    'de' => 'Der Gutscheincode kann keinem Gutschein eindeutig zugewiesen werden, da für den Code mehrere Gutscheine gefunden wurden.',
                ],
                'meta' => [
                    'couponCode' => $code,
                    'coupons' => $coupons,
                ],
            ]),
        );
    }

    public static function unsupportedCouponType(string $code, string|int $couponType): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::UNSUPPORTED_COUPON_TYPE,
                'title' => [
                    'en' => 'Coupon type not supported.',
                    'de' => 'Gutscheinart unbekannt.',
                ],
                'detail' => [
                    'en' => 'The coupon type is not supported.',
                    'de' => 'Die Art des Gutscheins wird nicht unterstützt.',
                ],
                'meta' => [
                    'couponCode' => $code,
                    'couponType' => $couponType,
                ],
            ]),
        );
    }

    public static function unsupportedTaxes(string $code, CalculatedTaxCollection $taxRules): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::UNSUPPORTED_TAXES,
                'title' => [
                    'en' => 'Taxes not supported',
                    'de' => 'Steuern nicht unterstützt',
                ],
                'detail' => [
                    'en' => 'The coupon was sold with unsupported tax rates. The coupon must be sold with exactly one tax rate.',
                    'de' => 'Der Gutschein wurde mit einer nicht unterstützten Steuerkonfiguration verkauft. Der Gutschein muss mit exakt einem Steuersatz verkauft werden.',
                ],
                'meta' => [
                    'couponCode' => $code,
                    'couponTaxesOnPurchase' => $taxRules,
                ],
            ]),
        );
    }

    public static function notPaid(string $code, string $orderId): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::NOT_PAID,
                'title' => [
                    'en' => 'Coupon not paid',
                    'de' => 'Gutschein nicht bezahlt',
                ],
                'detail' => [
                    'en' => 'The coupon was not not paid yet.',
                    'de' => 'Der Gutschein wurde noch nicht bezahlt.',
                ],
                'meta' => [
                    'couponCode' => $code,
                    'orderId' => $orderId,
                ],
            ]),
        );
    }

    public static function discountMissing(string $code): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::DISCOUNT_MISSING,
                'title' => [
                    'en' => 'Discount missing',
                    'de' => 'Rabatt fehlt',
                ],
                'detail' => [
                    'en' => 'No discount is assigned to this coupon.',
                    'de' => 'Diesem Gutschein ist kein Rabatt zugewiesen.',
                ],
                'meta' => [
                    'couponCode' => $code,
                ],
            ]),
        );
    }

    public static function onlyOneDiscountAllowed(string $code): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::ONLY_ONE_DISCOUNT_ALLOWED,
                'title' => [
                    'en' => 'Only one discount is allowed',
                    'de' => 'Nur ein Rabatt gültig',
                ],
                'detail' => [
                    'en' => 'For this promotion exists multiple discounts but only one is supported.',
                    'de' => 'Diesem Gutschein sind mehrere Rabatte zugewiesen, es wird aber nur ein Rabatt unterstützt.',
                ],
                'meta' => [
                    'couponCode' => $code,
                ],
            ]),
        );
    }

    public static function invalidDiscountScope(string $code, string $scope): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::INVALID_DISCOUNT_SCOPE,
                'title' => [
                    'en' => 'Invalid discount scope',
                    'de' => 'Falscher Rabatt-Anwendungsbereich',
                ],
                'detail' => [
                    'en' => 'The discount has an invalid scope. It needs to be scoped to "cart" to work at the POS.',
                    'de' => 'Der Rabatt hat den falschen Anwendungsbereich. Der Anwendungsbereich muss "Warenkorb" sein.',
                ],
                'meta' => [
                    'couponCode' => $code,
                    'discountScope' => $scope,
                ],
            ]),
        );
    }
}
