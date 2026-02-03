<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\OrderTaxValidation\Controller;

use Pickware\DatevBundle\OrderTaxValidation\TaxInformationValidator;
use Pickware\ValidationBundle\Annotation\JsonParameterAsArrayOfUuids;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class OrderTaxValidationController
{
    public function __construct(
        private readonly TaxInformationValidator $orderTaxInformationValidator,
    ) {}

    #[Route(
        path: '/api/_action/pickware-datev/get-orders-have-valid-tax-information',
        methods: ['POST'],
    )]
    public function getExportHasIndividualDebtorAccountInformation(
        #[JsonParameterAsArrayOfUuids] array $orderIds,
        Context $context,
    ): Response {
        return new JsonResponse([
            'ordersHaveValidTaxInformation' => $this->orderTaxInformationValidator->areOrdersTaxInformationValid(
                $orderIds,
                $context,
            ),
        ]);
    }
}
