<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picking;

use Closure;
use LogicException;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PickingRequest
{
    /**
     * @deprecated Only exists for backwards compatibility with pickware-wms. Will be removed in v5.0.0.
     */
    private readonly array $legacyPickingRequestSolution;

    private readonly ?StockArea $sourceStockArea;
    private readonly ProductQuantityImmutableCollection $productsToPick;

    /**
     * @param ProductQuantityImmutableCollection|array<ProductPickingRequest> $productQuantities
     */
    public function __construct(
        mixed $productQuantities,
        ?StockArea $sourceStockArea = null, // Is only optional for backwards compatibility with pickware-wms
        ?array $legacyPickingRequestSolution = null,
    ) {
        if (is_array($productQuantities)) {
            trigger_error(
                'Passing an array as `productQuantities` is deprecated. Pass a `ProductQuantityImmutableCollection`' .
                ' instead. Support for arrays will be dropped in v5.0.0.',
                E_USER_DEPRECATED,
            );
            $this->productsToPick = ImmutableCollection::create($productQuantities)
                ->map(
                    fn(ProductPickingRequest $productPickingRequest) => new ProductQuantity(
                        productId: $productPickingRequest->getProductId(),
                        quantity: $productPickingRequest->getQuantity(),
                    ),
                    returnType: ProductQuantityImmutableCollection::class,
                )
                ->groupByProductId();
        } else {
            // A picking request must always have deduplicated product quantities
            $this->productsToPick = $productQuantities->groupByProductId();
        }
        if ($legacyPickingRequestSolution !== null) {
            trigger_error(
                'Passing a `legacyPickingRequestSolution` is deprecated. Support for this property will be dropped' .
                ' in v5.0.0.',
                E_USER_DEPRECATED,
            );
        }
        $this->sourceStockArea = $sourceStockArea;
        $this->legacyPickingRequestSolution = $legacyPickingRequestSolution ?? [];
    }

    public function getProductsToPick(): ProductQuantityImmutableCollection
    {
        return $this->productsToPick;
    }

    public function getSourceStockArea(): StockArea
    {
        if ($this->sourceStockArea === null) {
            throw new LogicException('It is required to pass a source stock area to the picking request.');
        }

        return $this->sourceStockArea;
    }

    // The following properties are for backwards compatibility with pickware-wms and can be removed in v5.0.0

    /**
     * @deprecated Will be removed in v5.0.0. Use {@link getProductsToPick}` instead.
     */
    private function getProductPickingRequests(): array
    {
        $productPickingRequests = [];
        foreach ($this->productsToPick as $productToPick) {
            $pickLocations = array_map(
                fn(ProductQuantityLocation $itemToPick) => new PickLocation(
                    stockLocationReference: $itemToPick->getStockLocationReference(),
                    quantityToPick: $itemToPick->getQuantity(),
                ),
                array_values(array_filter(
                    $this->legacyPickingRequestSolution,
                    fn(ProductQuantityLocation $solution) => $solution->getProductId() === $productToPick->getProductId(),
                )),
            );

            // If there are multiple pick locations, we create independent product picking requests, so they can be
            // sorted independently. If there is no pick location at all, we still want to create one product picking
            // request.
            do {
                $productPickingRequests[] = new ProductPickingRequest(
                    productId: $productToPick->getProductId(),
                    quantity: $productToPick->getQuantity(),
                    pickLocations: array_values(array_filter([array_shift($pickLocations)])),
                );
            } while (count($pickLocations) > 0);
        }

        // We need to apply the sorting of the legacy picking solution to the product picking requests because the
        // sorting might be broken if we needed to split multiple pick locations into multiple product picking requests.
        $sortedStockLocationReferences = new ImmutableCollection(array_map(
            fn(ProductQuantityLocation $solution) => $solution->getStockLocationReference(),
            $this->legacyPickingRequestSolution,
        ));
        usort(
            $productPickingRequests,
            function(ProductPickingRequest $lhs, ProductPickingRequest $rhs) use ($sortedStockLocationReferences) {
                $lhsStockLocationReference = ($lhs->getPickLocations()[0] ?? null)?->getStockLocationReference();
                $rhsStockLocationReference = ($rhs->getPickLocations()[0] ?? null)?->getStockLocationReference();

                $lhsIndex = $sortedStockLocationReferences->indexOfElementEqualTo($lhsStockLocationReference);
                $rhsIndex = $sortedStockLocationReferences->indexOfElementEqualTo($rhsStockLocationReference);

                return $lhsIndex <=> $rhsIndex;
            },
        );

        return $productPickingRequests;
    }

    /**
     * @deprecated Will be removed in v5.0.0. Use {@link getProductsToPick}` instead.
     */
    public function map(Closure $closure): array
    {
        return array_map($closure, $this->getProductPickingRequests());
    }

    /**
     * @deprecated Will be removed in v5.0.0. Use {@link getProductsToPick}` instead.
     */
    public function getElements(): array
    {
        return $this->getProductPickingRequests();
    }

    /**
     * @deprecated Will be removed in v5.0.0. Use {@link getProductsToPick}` instead.
     */
    public function getProductIds(): array
    {
        return $this->getProductsToPick()->getProductIds()->asArray();
    }
}
