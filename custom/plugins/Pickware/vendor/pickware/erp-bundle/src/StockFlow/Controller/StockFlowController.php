<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockFlow\Controller;

use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockFlow\StockFlowService;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Pickware\ValidationBundle\JsonValidator;
use Pickware\ValidationBundle\JsonValidatorException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class StockFlowController
{
    private JsonValidator $jsonValidator;
    private StockFlowService $stockFlowService;

    public function __construct(StockFlowService $stockFlowService, JsonValidator $jsonValidator)
    {
        $this->stockFlowService = $stockFlowService;
        $this->jsonValidator = $jsonValidator;
    }

    #[Route(
        path: '/api/_action/pickware-erp/calculate-stock-flow-for-stock-location',
        name: 'api.action.pickware-erp.calculate-stock-flow-for-stock-location',
        methods: ['POST'],
    )]
    public function calculateStockFlowForStockLocation(Request $request): JsonResponse
    {
        $stockLocationJson = $request->getContent();
        try {
            $this->jsonValidator->validateJsonAgainstSchema($stockLocationJson, StockLocationReference::JSON_SCHEMA_FILE);
        } catch (JsonValidatorException $exception) {
            return $exception->serializeToJsonApiError()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }
        $stockLocation = Json::decodeToArray($stockLocationJson);

        return new JsonResponse($this->stockFlowService->getStockFlow(
            StockLocationReference::create($stockLocation),
        ));
    }

    #[JsonValidation(schemaFilePath: 'payload-calculate-combined-stock-flow-for-stock-locations.schema.json')]
    #[Route(
        path: '/api/_action/pickware-erp/calculate-combined-stock-flow-for-stock-locations',
        name: 'api.action.pickware-erp.calculate-combined-stock-flow-for-stock-locations',
        methods: ['POST'],
    )]
    public function calculateCombinedStockFlowForStockLocations(Request $request): JsonResponse
    {
        $stockLocations = array_map(
            fn(array $stockLocation) => StockLocationReference::create($stockLocation),
            $request->get('stockLocations'),
        );

        return new JsonResponse($this->stockFlowService->getCombinedStockFlow($stockLocations));
    }

    #[JsonValidation(schemaFilePath: 'payload-calculate-combined-stock-flow-for-stock-locations-per-location-type.schema.json')]
    #[Route(
        path: '/api/_action/pickware-erp/calculate-combined-stock-flow-for-stock-locations-per-location-type',
        name: 'api.action.pickware-erp.calculate-combined-stock-flow-for-stock-locations-per-location-type',
        methods: ['POST'],
    )]
    public function calculateCombinedStockFlowForStockLocationsPerLocationType(Request $request): JsonResponse
    {
        $stockLocations = array_map(
            fn(array $stockLocation) => StockLocationReference::create($stockLocation),
            $request->get('stockLocations'),
        );

        return new JsonResponse($this->stockFlowService->getCombinedStockFlowPerStockLocationType($stockLocations));
    }
}
