<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Api;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use SoapFault;

class AustrianPostShipmentServiceApiTransportException extends AustrianPostApiClientException
{
    public function __construct(SoapFault $soapFault)
    {
        parent::__construct(
            new LocalizableJsonApiError([
                'title' => [
                    'de' => 'Austrian Post API Kommunikationsfehler',
                    'en' => 'Austrian Post API communication error',
                ],
                'detail' => [
                    'de' => sprintf(
                        'Die Kommunikation mit der Austrian Post API ist nicht möglich. Error: %s',
                        $soapFault->getMessage(),
                    ),
                    'en' => sprintf(
                        'The communication with the Austrian Post API is not possible. Error: %s',
                        $soapFault->getMessage(),
                    ),
                ],
                'meta' => ['errorMessage' => $soapFault->getMessage()],
            ]),
            $soapFault,
        );
    }
}
