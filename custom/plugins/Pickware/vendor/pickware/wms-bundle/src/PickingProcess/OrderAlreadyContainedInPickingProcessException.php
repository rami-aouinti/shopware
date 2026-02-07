<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareWms\Delivery\Model\DeliveryCollection;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;

class OrderAlreadyContainedInPickingProcessException extends PickingProcessException
{
    public const PICKING_PROCESS_FOR_ORDER_ALREADY_EXISTS = self::ERROR_CODE_NAMESPACE . 'PICKING_PROCESS_FOR_ORDER_ALREADY_EXISTS';

    public function __construct(DeliveryCollection $deliveries)
    {
        $errors = array_values($deliveries->map(
            fn(DeliveryEntity $delivery) => new LocalizableJsonApiError([
                'code' => self::PICKING_PROCESS_FOR_ORDER_ALREADY_EXISTS,
                'title' => [
                    'en' => 'Order already part of a picking process',
                    'de' => 'Bestellung bereits Teil eines Kommissioniervorgangs',
                ],
                'detail' => [
                    'en' => sprintf(
                        'The order "%s" is already part of the picking process "%s".',
                        $delivery->getOrder()->getOrderNumber(),
                        $delivery->getPickingProcess()->getNumber(),
                    ),
                    'de' => sprintf(
                        'Die Bestellung "%s" ist bereits Teil des Kommissioniervorgangs "%s".',
                        $delivery->getOrder()->getOrderNumber(),
                        $delivery->getPickingProcess()->getNumber(),
                    ),
                ],
                'meta' => [
                    'orderId' => $delivery->getOrderId(),
                    'orderNumber' => $delivery->getOrder()->getOrderNumber(),
                    'pickingProcessId' => $delivery->getPickingProcessId(),
                    'pickingProcessNumber' => $delivery->getPickingProcess()->getNumber(),
                ],
            ]),
        ));

        parent::__construct(new JsonApiErrors($errors));
    }
}
