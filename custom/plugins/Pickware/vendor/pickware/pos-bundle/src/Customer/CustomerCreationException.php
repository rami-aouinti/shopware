<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Customer;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class CustomerCreationException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_POS__CUSTOMER_CREATION__';
    public const ERROR_CODE_SALES_CHANNEL_DOMAIN_MISSING = self::ERROR_CODE_NAMESPACE . 'SALES_CHANNEL_DOMAIN_MISSING';
    public const ERROR_CODE_NOTIFY_CUSTOMER_REGISTRATION_FAILED = self::ERROR_CODE_NAMESPACE . 'NOTIFY_CUSTOMER_REGISTRATION_FAILED';
    public const ERROR_CODE_NOTIFY_CUSTOMER_RECOVERY_FAILED = self::ERROR_CODE_NAMESPACE . 'NOTIFY_CUSTOMER_RECOVERY_FAILED';
    public const ERROR_CODE_NOTIFY_NEWSLETTER_SUBSCRIPTION_FAILED = self::ERROR_CODE_NAMESPACE . 'NOTIFY_NEWSLETTER_SUBSCRIPTION_FAILED';
    public const ERROR_CUSTOMER_ALREADY_EXISTS = self::ERROR_CODE_NAMESPACE . 'CUSTOMER_ALREADY_EXISTS';

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

    public static function salesChannelDomainMissing(string $configSalesChannelId, string $configSalesChannelName): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::ERROR_CODE_SALES_CHANNEL_DOMAIN_MISSING,
            'title' => 'Sales channel domain missing from config',
            'detail' => sprintf(
                'The config for plugin Pickware-POS does not have a sales channel domain set for sales channel "%s". '
                . 'In the administration, please go to the plugins configuration and select a domain.',
                $configSalesChannelName,
            ),
            'meta' => [
                'salesChannelId' => $configSalesChannelId,
                'salesChannelName' => $configSalesChannelName,
            ],
        ]);

        return new self($jsonApiError);
    }

    public static function notifyCustomerRegistrationFailed(Exception $exception): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::ERROR_CODE_NOTIFY_CUSTOMER_REGISTRATION_FAILED,
            'title' => 'The customer registration email could not be sent',
            'detail' => sprintf('An unhandled server error occurred: %s', $exception->getMessage()),
            'meta' => ['originalExceptionMessage' => $exception->getMessage()],
        ]);

        return new self($jsonApiError);
    }

    public static function notifyCustomerRecoveryFailed(Exception $exception): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::ERROR_CODE_NOTIFY_CUSTOMER_RECOVERY_FAILED,
            'title' => 'The customer password recovery email could not be sent',
            'detail' => sprintf('An unhandled server error occurred: %s', $exception->getMessage()),
            'meta' => ['originalExceptionMessage' => $exception->getMessage()],
        ]);

        return new self($jsonApiError);
    }

    public static function notifyCustomerNewsletterSubscriptionFailed(Exception $exception): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::ERROR_CODE_NOTIFY_NEWSLETTER_SUBSCRIPTION_FAILED,
            'title' => 'The newsletter subscription email could not be sent',
            'detail' => sprintf('An unhandled server error occurred: %s', $exception->getMessage()),
            'meta' => ['originalExceptionMessage' => $exception->getMessage()],
        ]);

        return new self($jsonApiError);
    }

    public static function customerAlreadyExists(string $email): self
    {
        return new self(
            (new LocalizableJsonApiError([
                'code' => self::ERROR_CUSTOMER_ALREADY_EXISTS,
                'title' => [
                    'en' => 'Customer already exists',
                    'de' => 'Kunde existiert bereits',
                ],
                'detail' => [
                    'en' => sprintf('A customer already exists with the following email address: %s', $email),
                    'de' => sprintf('Es existiert bereits ein Kunde mit folgender E-Mail-Adresse: %s', $email),
                ],
                'meta' => [
                    'email' => $email,
                ],
            ])),
        );
    }
}
