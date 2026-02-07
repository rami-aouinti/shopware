<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Mail;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class LabelMailerException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_SHIPPING__LABEL_MAILER__';
    public const FAILED_TO_RENDER_MAIL_TEMPLATE = self::ERROR_CODE_NAMESPACE . 'FAILED_TO_RENDER_MAIL_TEMPLATE';
    public const NO_MAIL_TEMPLATE_CONFIGURED_FOR_CARRIER = self::ERROR_CODE_NAMESPACE . 'NO_MAIL_TEMPLATE_CONFIGURED_FOR_CARRIER';
    public const ORDER_DELIVERY_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'ORDER_DELIVERY_NOT_FOUND';
    public const SHIPMENT_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'SHIPMENT_NOT_FOUND';
    public const SHIPMENT_HAS_NO_RETURN_LABEL_DOCUMENTS = self::ERROR_CODE_NAMESPACE . 'SHIPMENT_HAS_NO_RETURN_LABEL_DOCUMENTS';

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

    public static function noMailTemplateConfiguredForCarrier(string $carrierName): self
    {
        return new self(new JsonApiError([
            'code' => self::NO_MAIL_TEMPLATE_CONFIGURED_FOR_CARRIER,
            'title' => 'No mail templated configured for carrier',
            'detail' => sprintf(
                'The return label mail template for carrier "%s" does not exist anymore. Try to reinstall the plugin.',
                $carrierName,
            ),
            'meta' => [
                'carrierName' => $carrierName,
            ],
        ]));
    }

    public static function failedToRenderMailTemplate(string $mailTemplateTypeName): self
    {
        return new self(new JsonApiError([
            'code' => self::FAILED_TO_RENDER_MAIL_TEMPLATE,
            'title' => 'Mail template could not be rendered',
            'detail' => sprintf(
                'The mail template "%s" could not be rendered. Please check your mail template configuration',
                $mailTemplateTypeName,
            ),
            'meta' => [
                'mailTemplateTypeName' => $mailTemplateTypeName,
            ],
        ]));
    }

    public static function orderDeliveryNotFound(string $orderDeliveryId): self
    {
        return new self(new JsonApiError([
            'code' => self::ORDER_DELIVERY_NOT_FOUND,
            'title' => 'Order delivery not found',
            'detail' => sprintf(
                'The order-delivery with ID %s was not found.',
                $orderDeliveryId,
            ),
            'meta' => [
                'orderDeliveryId' => $orderDeliveryId,
            ],
        ]));
    }

    public static function shipmentNotFound(string $shipmentId): self
    {
        return new self(new JsonApiError([
            'code' => self::SHIPMENT_NOT_FOUND,
            'title' => 'Shipment not found',
            'detail' => sprintf(
                'The shipment with ID %s was not found.',
                $shipmentId,
            ),
            'meta' => [
                'shipmentId' => $shipmentId,
            ],
        ]));
    }

    public static function shipmentHasNoReturnLabelDocuments(string $shipmentId): self
    {
        return new self(new JsonApiError([
            'code' => self::SHIPMENT_HAS_NO_RETURN_LABEL_DOCUMENTS,
            'title' => 'Shipment has no return label documents',
            'detail' => sprintf(
                'The shipment with ID %s has no return label documents.',
                $shipmentId,
            ),
            'meta' => [
                'shipmentId' => $shipmentId,
            ],
        ]));
    }
}
