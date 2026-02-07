<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Mail\Controller;

use Pickware\HttpUtils\ResponseFactory;
use Pickware\ShippingBundle\Mail\LabelMailerException;
use Pickware\ShippingBundle\Mail\LabelMailerService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class MailController
{
    private LabelMailerService $mailService;

    public function __construct(LabelMailerService $mailService)
    {
        $this->mailService = $mailService;
    }

    #[Route(
        path: '/api/_action/pickware-shipping/mail/send-return-labels-of-shipment-to-order-customer',
        name: 'api.action.pickware-shipping.mail.send-return-labels-of-shipment-to-order-customer',
        methods: ['POST'],
    )]
    public function sendReturnLabelOfShipmentToOrderCustomer(Request $request, Context $context): Response
    {
        $shipmentId = $request->request->getAlnum('shipmentId');
        if (!$shipmentId) {
            return ResponseFactory::createParameterMissingResponse('shipmentId');
        }
        $orderId = $request->request->getAlnum('orderId');
        if (!$orderId) {
            return ResponseFactory::createParameterMissingResponse('orderId');
        }
        try {
            $this->mailService->sendReturnLabelsOfShipmentToOrderCustomer($shipmentId, $orderId, $context);
        } catch (LabelMailerException $exception) {
            return $exception->serializeToJsonApiError()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
