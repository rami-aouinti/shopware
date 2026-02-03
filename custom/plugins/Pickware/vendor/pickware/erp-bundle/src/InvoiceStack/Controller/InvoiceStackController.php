<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\InvoiceStack\Controller;

use Pickware\PickwareErpStarter\InvoiceStack\InvoiceStackService;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
readonly class InvoiceStackController
{
    public function __construct(
        private InvoiceStackService $invoiceStackService,
    ) {}

    #[Route(path: '/api/_action/pickware-erp/check-order-has-non-cancelled-invoice', methods: ['POST'])]
    public function checkOrderHasNonCancelledInvoiceAction(
        #[JsonParameterAsUuid] string $orderId,
        Context $context,
    ): JsonResponse {
        return new JsonResponse([
            'hasNonCancelledInvoice' => $this->invoiceStackService
                ->getInvoiceStacksOfOrder($orderId, $context)
                ->containsOpenInvoiceStack(),
        ]);
    }
}
