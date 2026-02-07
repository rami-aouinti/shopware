<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\Controller;

use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityResponseService;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeCountingProcessDefinition;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeDefinition;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeEntity;
use Pickware\PickwareErpStarter\Stocktaking\StocktakingException;
use Pickware\PickwareErpStarter\Stocktaking\StocktakingService;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationCollection;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Framework\Api\Response\ResponseFactoryRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\GreaterThan;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class StocktakingController
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly StocktakingService $stocktakingService,
        private readonly EntityResponseService $entityResponseService,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
        private readonly ResponseFactoryRegistry $responseFactoryRegistry,
        private readonly RequestStack $requestStack,
    ) {}

    #[Route('/api/_action/pickware-erp/stocktaking/get-uncounted-products-in-stock-location', methods: 'PUT')]
    public function getUncountedProductsInStockLocation(
        #[JsonParameterAsUuid] string $stocktakeId,
        #[JsonParameterAsUuid] ?string $binLocationId,
        #[JsonParameter(validations: [new GreaterThan(0)])] int $limit,
        Context $context,
    ): Response {
        return new JsonResponse([
            'productIds' => $this->stocktakingService->getUncountedProductsInStockLocation(
                $stocktakeId,
                $binLocationId,
                $limit,
                $context,
            ),
        ]);
    }

    #[Route('/api/_action/pickware-erp/stocktaking/create-counting-processes', methods: 'PUT')]
    #[JsonValidation(schemaFilePath: 'create-counting-processes-payload.schema.json')]
    public function createCountingProcesses(Request $request, Context $context): Response
    {
        $payload = $request->request->all();
        foreach ($payload['countingProcesses'] as &$countingProcessPayload) {
            $countingProcessPayload['userId'] = $context->getSource()->getUserId();
        }
        unset($countingProcessPayload);

        try {
            $countingProcessIds = $this->entityManager->runInTransactionWithRetry(
                function() use ($payload, $context) {
                    // Before the counting process creation, delete any existing counting process for the same bin location
                    // if the 'overwrite' flag was set.
                    if (($payload['overwrite'] ?? false) === true) {
                        $countingProcessFilter = new OrFilter();
                        foreach ($payload['countingProcesses'] as $countingProcessPayload) {
                            if (isset($countingProcessPayload['binLocationId'])) {
                                $countingProcessFilter->addQuery(
                                    new AndFilter([
                                        new EqualsFilter('stocktakeId', $countingProcessPayload['stocktakeId']),
                                        new EqualsFilter('binLocationId', $countingProcessPayload['binLocationId']),
                                    ]),
                                );
                            }
                        }

                        if (count($countingProcessFilter->getQueries()) > 0) {
                            $this->entityManager->deleteByCriteria(
                                StocktakeCountingProcessDefinition::class,
                                (new Criteria())->addFilter($countingProcessFilter),
                                $context,
                            );
                        }

                        return $this->stocktakingService->createCountingProcesses(
                            $payload['countingProcesses'],
                            $context,
                        );
                    }

                    // If the overwrite flag is false, upsert every counting process with an unknown stock location and
                    // create every counting process with a known stock location.
                    // This is necessary because of the feature #7568, which merges counting processes with unknown
                    // stock locations for the same product. We have to handle this here, in the
                    // _createCountingProcesses_ endpoint, to guarantee backwards compatibility with WMS.
                    $countingProcessesWithUnknownStockLocation = array_values(array_filter(
                        $payload['countingProcesses'],
                        fn(array $countingProcessPayload) => $countingProcessPayload['binLocationId'] === null,
                    ));

                    $countingProcessesWithBinLocation = array_values(array_filter(
                        $payload['countingProcesses'],
                        fn(array $countingProcessPayload) => $countingProcessPayload['binLocationId'] !== null,
                    ));

                    $upsertedIds = $this->stocktakingService->upsertCountingProcesses($countingProcessesWithUnknownStockLocation, $context);
                    $createdIds = $this->stocktakingService->createCountingProcesses($countingProcessesWithBinLocation, $context);

                    return array_merge($upsertedIds, $createdIds);
                },
            );
        } catch (StocktakingException $e) {
            return $e->serializeToJsonApiError()->setStatus(Response::HTTP_BAD_REQUEST)->toJsonApiErrorResponse();
        }

        return $this->entityResponseService->makeEntityListingResponse(
            StocktakeCountingProcessDefinition::class,
            $countingProcessIds,
            $context,
        );
    }

    #[Route('/api/_action/pickware-erp/stocktaking/upsert-counting-processes')]
    #[JsonValidation(schemaFilePath: 'upsert-counting-processes-payload.schema.json')]
    public function upsertCountingProcesses(
        Request $request,
        Context $context,
    ): Response {
        $payload = $request->request->all();
        foreach ($payload['countingProcesses'] as &$countingProcessPayload) {
            $countingProcessPayload['userId'] = $context->getSource()->getUserId();
        }
        unset($countingProcessPayload);

        try {
            $countingProcessIds = $this->stocktakingService->upsertCountingProcesses($payload['countingProcesses'], $context);
        } catch (StocktakingException $e) {
            return $e->serializeToJsonApiError()->setStatus(Response::HTTP_BAD_REQUEST)->toJsonApiErrorResponse();
        }

        return $this->entityResponseService->makeEntityListingResponse(
            StocktakeCountingProcessDefinition::class,
            $countingProcessIds,
            $context,
        );
    }

    #[Route('/api/_action/pickware-erp/stocktaking/complete-stocktake', methods: 'PUT')]
    #[JsonValidation(schemaFilePath: 'complete-stocktake-payload.schema.json')]
    public function completeStocktake(Request $request, Context $context): Response
    {
        $payload = $request->request->all();
        try {
            $this->stocktakingService->completeStocktake($payload['stocktakeId'], $context->getSource()->getUserId(), $context);
        } catch (StocktakingException $e) {
            return $e->serializeToJsonApiError()->setStatus(Response::HTTP_BAD_REQUEST)->toJsonApiErrorResponse();
        }

        return new Response('', Response::HTTP_OK);
    }

    #[Route('/api/_action/pickware-erp/stocktaking/get-uncounted-stocks', methods: 'PUT')]
    public function getUncountedStocks(
        #[JsonParameterAsUuid] string $stocktakeId,
        #[JsonParameter] array $criteria,
        Context $context,
    ): Response {
        $deserializedCriteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $criteria,
            StockDefinition::class,
        );

        /** @var ?StocktakeEntity $stocktake */
        $stocktake = $this->entityManager->findByPrimaryKey(
            StocktakeDefinition::class,
            $stocktakeId,
            $context,
        );
        if (!$stocktake) {
            return StocktakingException::stocktakesNotFound([$stocktakeId])
                ->serializeToJsonApiError()
                ->setStatus(Response::HTTP_BAD_REQUEST)
                ->toJsonApiErrorResponse();
        }

        $uncountedProductIdsInUnknownStockLocation = $this->stocktakingService->getUncountedProductsInUnknownStockLocation(
            $stocktakeId,
            $context,
        );

        // This method return uncounted stocks in the warehouse of the given stocktake. We need to filter out the stocks
        // that are already counted in the given stocktake. Therefore, we add to the criteria sent by the client
        // additional filters.
        $this->addUncountedStocksCriteriaFilter(
            $deserializedCriteria,
            $stocktake->getWarehouseId(),
            $stocktakeId,
            $uncountedProductIdsInUnknownStockLocation,
        );

        $request = $this->requestStack->getCurrentRequest();

        return $context->enableInheritance(fn(Context $context) => $this->responseFactoryRegistry
            ->getType($request)
            ->createListingResponse(
                $deserializedCriteria,
                $this->entityManager
                    ->getRepository(StockDefinition::class)
                    ->search($deserializedCriteria, $context),
                $this->entityManager->getEntityDefinition(StockDefinition::class),
                $request,
                $context,
            ));
    }

    #[Route('/api/_action/pickware-erp/stocktaking/get-uncounted-stocks-summary', methods: 'POST')]
    public function getUncountedStocksSummary(
        #[JsonParameterAsUuid] string $stocktakeId,
        #[JsonParameter] int $uncountedBinLocationsLimit,
        Context $context,
    ): Response {
        /** @var ?StocktakeEntity $stocktake */
        $stocktake = $this->entityManager->findByPrimaryKey(
            StocktakeDefinition::class,
            $stocktakeId,
            $context,
        );
        if (!$stocktake) {
            return StocktakingException::stocktakesNotFound([$stocktakeId])
                ->serializeToJsonApiError()
                ->setStatus(Response::HTTP_BAD_REQUEST)
                ->toJsonApiErrorResponse();
        }

        $uncountedProductIdsInUnknownStockLocation = $this->stocktakingService->getUncountedProductsInUnknownStockLocation(
            $stocktakeId,
            $context,
        );

        $uncountedStockCriteria = new Criteria();
        $this->addUncountedStocksCriteriaFilter(
            $uncountedStockCriteria,
            $stocktake->getWarehouseId(),
            $stocktakeId,
            $uncountedProductIdsInUnknownStockLocation,
        );

        $uncountedProductsCount = $this->entityManager->count(
            StockDefinition::class,
            'productId',
            $uncountedStockCriteria,
            $context,
        );

        $uncountedBinLocationCriteria = new Criteria();
        $uncountedBinLocationCriteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('warehouseId', $stocktake->getWarehouseId()),
            new NotFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('pickwareStocktakeCountingProcesses.stocktakeId', $stocktakeId),
            ]),
            new NotFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('stocks.id', null),
            ]),
        ]));
        $uncountedBinLocationCriteria
            ->addSorting(new FieldSorting('code', FieldSorting::ASCENDING))
            ->setLimit($uncountedBinLocationsLimit);

        /** @var BinLocationCollection $uncountedBinLocations */
        $uncountedBinLocations = $this->entityManager->findBy(
            BinLocationDefinition::class,
            $uncountedBinLocationCriteria,
            $context,
        );

        $uncountedBinLocationCriteria->setLimit(null);
        $uncountedBinLocationsCount = $this->entityManager->count(
            BinLocationDefinition::class,
            'id',
            $uncountedBinLocationCriteria,
            $context,
        );

        $uncountedBinLocationCodes = array_values($uncountedBinLocations->map(fn($binLocation) => $binLocation->getCode()));

        return new JsonResponse([
            'uncountedProductsCount' => $uncountedProductsCount,
            'someUncountedBinLocationCodes' => $uncountedBinLocationCodes,
            'uncountedBinLocationsCount' => $uncountedBinLocationsCount,
            'hasUncountedProductsInUnknownStockLocation' => !empty($uncountedProductIdsInUnknownStockLocation),
        ]);
    }

    private function addUncountedStocksCriteriaFilter(
        Criteria $criteria,
        string $warehouseId,
        string $stocktakeId,
        array $uncountedProductIdsInUnknownStockLocation,
    ): void {
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            // Stock in unknown and product uncounted in unknown
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('warehouseId', $warehouseId),
                new EqualsAnyFilter('productId', $uncountedProductIdsInUnknownStockLocation),
                new EqualsFilter('binLocationId', null),
            ]),
            // Stock in bin location and the bin location is uncounted
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('binLocation.warehouseId', $warehouseId),
                new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('binLocation.id', null)]),
                new NotFilter(MultiFilter::CONNECTION_AND, [
                    new EqualsFilter('binLocation.pickwareStocktakeCountingProcesses.stocktakeId', $stocktakeId),
                ]),
            ]),
        ]));

        $criteria->addFilter(
            new EqualsFilter('product.pickwareErpPickwareProduct.isStockManagementDisabled', false),
        );
    }
}
