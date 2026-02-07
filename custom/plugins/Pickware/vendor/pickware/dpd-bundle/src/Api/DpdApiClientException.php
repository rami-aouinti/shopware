<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Api;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\ShippingBundle\Carrier\CarrierAdapterException;
use SoapFault;
use stdClass;

class DpdApiClientException extends CarrierAdapterException
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_DPD__API_CLIENT__';
    private const ERROR_CODE_DPD_API_COMMUNICATION_EXCEPTION = self::ERROR_CODE_NAMESPACE . 'DPD_API_COMMUNICATION_EXCEPTION';

    public static function dpdShipmentServiceApiCommunicationException(SoapFault $soapFault): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::ERROR_CODE_DPD_API_COMMUNICATION_EXCEPTION,
                'title' => [
                    'de' => 'DPD API Kommunikationsfehler',
                    'en' => 'DPD API communication error',
                ],
                'detail' => [
                    'de' => sprintf(
                        'Die Kommunikation mit der DPD API ist nicht möglich. Error: %s',
                        $soapFault->getMessage(),
                    ),
                    'en' => sprintf(
                        'The communication with the DPD API is not possible. Error: %s',
                        $soapFault->getMessage(),
                    ),
                ],
                'meta' => ['errorMessage' => $soapFault->getMessage()],
            ]),
            $soapFault,
        );
    }

    public static function authenticationFailed(stdClass $status): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'de' => 'DPD Authentifizierung fehlgeschlagen',
                    'en' => 'DPD authentication failed',
                ],
                'detail' => $status->message,
                'meta' => ['code' => $status->code],
            ]),
        );
    }
}
