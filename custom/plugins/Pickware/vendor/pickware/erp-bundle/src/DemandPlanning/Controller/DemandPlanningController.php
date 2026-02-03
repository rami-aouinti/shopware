<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemandPlanning\Controller;

use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\PickwareErpStarter\DemandPlanning\AnalyticsProfile\Model\DemandPlanningListItemDefinition;
use Pickware\PickwareErpStarter\DemandPlanning\DemandPlanningListService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class DemandPlanningController
{
    private DemandPlanningListService $demandPlanningListService;
    private CriteriaJsonSerializer $criteriaJsonSerializer;

    public function __construct(
        DemandPlanningListService $demandPlanningListService,
        CriteriaJsonSerializer $criteriaJsonSerializer,
    ) {
        $this->demandPlanningListService = $demandPlanningListService;
        $this->criteriaJsonSerializer = $criteriaJsonSerializer;
    }

    #[Route(path: '/api/_action/pickware-erp/demand-planning/add-items-to-purchase-list', methods: ['POST'])]
    public function addItemsToPurchaseList(Request $request): Response
    {
        $demandPlanningListItemIds = $request->get('demandPlanningListItemIds', []);

        $this->demandPlanningListService->addDemandPlanningItemsToPurchaseList($demandPlanningListItemIds);

        return new JsonResponse();
    }

    #[Route(path: '/api/_action/pickware-erp/demand-planning/add-all-items-to-purchase-list', methods: ['POST'])]
    public function addAllItemsToPurchaseList(Request $request, Context $context): Response
    {
        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $request->get('criteria', []),
            DemandPlanningListItemDefinition::class,
        );

        $this->demandPlanningListService->addAllItemsToPurchaseList($criteria, $context);

        return new JsonResponse();
    }
}
