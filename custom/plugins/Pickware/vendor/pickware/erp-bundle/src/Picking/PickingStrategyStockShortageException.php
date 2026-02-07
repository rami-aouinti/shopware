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

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;

class PickingStrategyStockShortageException extends Exception implements JsonApiErrorSerializable
{
    private JsonApiError $jsonApiError;

    /**
     * @param string[] $productNumbers
     */
    public function __construct(
        private readonly ProductQuantityImmutableCollection $stockShortages,
        private readonly ProductQuantityLocationImmutableCollection $partialPickingRequestSolution,
        private readonly array $productNumbers,
    ) {
        $this->jsonApiError = new LocalizableJsonApiError([
            'code' => 'PICKWARE_ERP__PICKING__PICKING_REQUEST_STOCK_SHORTAGE',
            'title' => [
                'en' => 'Stock shortage',
                'de' => 'Unzureichend Bestand',
            ],
            'detail' => [
                'en' => sprintf(
                    'There is not enough stock available to pick the requested quantity of the products with number: "%s".',
                    implode('", "', $productNumbers),
                ),
                'de' => sprintf(
                    'Es ist nicht ausreichend Bestand für die Produkte mit Nummer "%s" verfügbar um die angeforderte Menge zu kommissionieren.',
                    implode('", "', $productNumbers),
                ),
            ],
            'meta' => [
                'stockShortages' => $stockShortages,
                'productNumbers' => $productNumbers,
            ],
        ]);
        parent::__construct($this->jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public function getJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public function getStockShortages(): ProductQuantityImmutableCollection
    {
        return $this->stockShortages;
    }

    public function getPartialPickingRequestSolution(): ProductQuantityLocationImmutableCollection
    {
        return $this->partialPickingRequestSolution;
    }

    public function getProductNumbers(): array
    {
        return $this->productNumbers;
    }
}
