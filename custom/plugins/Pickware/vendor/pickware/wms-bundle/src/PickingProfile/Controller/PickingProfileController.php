<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProfile\Controller;

use Pickware\ApiVersioningBundle\Attributes\ApiLayer;
use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\OrderPickability\OrderPickabilityCriteriaFilterResolver;
use Pickware\PickwareWms\PickingProfile\ApiVersioning\ApiVersion20250703\PickingProfileOrderDeliveryFilterApiLayer as ApiVersion20250703PickingProfileOrderDeliveryFilterApiLayer;
use Pickware\PickwareWms\PickingProfile\DefaultPickingProfileFilterService;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class PickingProfileController
{
    public function __construct(
        private readonly EntityManager $entityManager,
        #[Autowire(service: 'pickware_wms.caching_order_pickability_criteria_filter_resolver')]
        private readonly OrderPickabilityCriteriaFilterResolver $orderPickabilityCriteriaFilterResolver,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
        private readonly DefaultPickingProfileFilterService $defaultPickingProfileFilterService,
    ) {}

    #[ApiLayer(ids: [
        ApiVersion20250703PickingProfileOrderDeliveryFilterApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-number-of-pickable-orders.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/picking-profile-number-of-pickable-orders', methods: ['POST'])]
    public function numberOfPickableOrders(Context $context, Request $request): JsonResponse
    {
        $response = [];
        foreach ($request->get('pickingProfiles', []) as $pickingProfile) {
            $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
                [
                    'filter' => $pickingProfile['filter'],
                ],
                OrderDefinition::class,
            );
            $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

            $this->orderPickabilityCriteriaFilterResolver->resolveOrderPickabilityFilter($criteria);

            $response[] = [
                'pickingProfileId' => $pickingProfile['pickingProfileId'],
                'numberOfPickableOrders' => (
                    $this->entityManager
                        ->getRepository(OrderDefinition::class)
                        ->searchIds($criteria, $context)
                        ->getTotal()
                ),
            ];
        }

        return new JsonResponse($response);
    }

    #[Route(path: '/api/_action/pickware-wms/picking-profile-default-filter', methods: ['GET'])]
    public function defaultFilter(Context $context): JsonResponse
    {
        $defaultFilter = $this->defaultPickingProfileFilterService->makeDefaultFilter($context);

        return new JsonResponse($defaultFilter);
    }
}
