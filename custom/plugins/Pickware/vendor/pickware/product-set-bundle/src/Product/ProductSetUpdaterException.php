<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Product;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class ProductSetUpdaterException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_PRODUCT_SET__PRODUCT_SET__';
    public const ERROR_CODE_PARENT_IS_NO_PRODUCT_SET = self::ERROR_CODE_NAMESPACE . 'PARENT_IS_NO_PRODUCT_SET';
    public const ERROR_CODE_PARENT_IS_PRODUCT_SET = self::ERROR_CODE_NAMESPACE . 'PARENT_IS_PRODUCT_SET';
    public const ERROR_CODE_CONFIGURATION_CANNOT_BE_PRODUCT_SET = self::ERROR_CODE_NAMESPACE . 'CONFIGURATION_CANNOT_BE_PRODUCT_SET';
    public const ERROR_CODE_PRODUCT_SET_CANNOT_BE_CONFIGURATION = self::ERROR_CODE_NAMESPACE . 'PRODUCT_SET_CANNOT_BE_CONFIGURATION';

    public function __construct(private readonly JsonApiError $jsonApiError)
    {
        parent::__construct($this->jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function invalidProductSetCreationBecauseParentIsNotProductSet(array $productIdsByParentProductIds): self
    {
        $jsonApiError = new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_PARENT_IS_NO_PRODUCT_SET,
            'title' => [
                'en' => 'Cannot create variant product sets',
                'de' => 'Stücklisten von Varianten-Produkten können nicht erstellt werden',
            ],
            'detail' => [
                'en' => sprintf(
                    'Cannot create product sets for variants [ID=%s] because their parent products are not product sets.',
                    implode(', ', array_values($productIdsByParentProductIds)),
                ),
                'de' => sprintf(
                    'Stücklisten von Varianten-Produkten [ID=%s] können nicht erstellt werden, da deren Haupt-Produkte keine Stücklisten sind.',
                    implode(', ', array_values($productIdsByParentProductIds)),
                ),
            ],
            'meta' => [
                'productIdsByParentProductIds' => $productIdsByParentProductIds,
            ],
        ]);

        return new self($jsonApiError);
    }

    public static function invalidProductSetDeletionBecauseParentIsAProductSet(array $productIdsByParentProductIds): self
    {
        $jsonApiError = new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_PARENT_IS_PRODUCT_SET,
            'title' => [
                'en' => 'Cannot delete variant product sets',
                'de' => 'Stücklisten von Varianten-Produkten können nicht gelöscht werden',
            ],
            'detail' => [
                'en' => sprintf(
                    'Cannot delete product sets for variants [ID=%s] because their parent products are product sets.',
                    implode(', ', array_values($productIdsByParentProductIds)),
                ),
                'de' => sprintf(
                    'Stücklisten von Varianten-Produkten [ID=%s] können nicht gelöscht werden, da deren Haupt-Produkte Stücklisten sind.',
                    implode(', ', array_values($productIdsByParentProductIds)),
                ),
            ],
            'meta' => [
                'productIdsByParentProductIds' => $productIdsByParentProductIds,
            ],
        ]);

        return new self($jsonApiError);
    }

    public static function invalidProductSetCreationBecauseProductIsAlreadyAConfiguration(
        string $productName,
        string $productId,
        string $productNumber,
    ): self {
        $jsonApiError = new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_CONFIGURATION_CANNOT_BE_PRODUCT_SET,
            'title' => [
                'de' => 'Ein bereits zugeordnetes Produkt einer Stückliste kann keine Stückliste sein',
                'en' => 'A product that is already assigned to a product set cannot be a product set',
            ],
            'detail' => [
                'de' => sprintf(
                    'Das Produkt "%s" ("%s"; ID "%s") ist bereits einer Stückliste zugeordnet. Das Produkt kann daher nicht selbst als Stückliste markiert werden.',
                    $productName,
                    $productNumber,
                    $productId,
                ),
                'en' => sprintf(
                    'The product "%s" ("%s"; ID "%s") is already assigned to a product set. The product can therefore not be marked as a product set itself.',
                    $productName,
                    $productNumber,
                    $productId,
                ),
            ],
            'meta' => [
                'productId' => $productId,
            ],
        ]);

        return new self($jsonApiError);
    }

    public static function invalidProductSetConfigurationCreationBecauseProductIsAlreadyAProductSet(
        string $productName,
        string $productId,
        string $productNumber,
    ): self {
        $jsonApiError = new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_PRODUCT_SET_CANNOT_BE_CONFIGURATION,
            'title' => [
                'de' => 'Ein Produkt, das bereits eine Stückliste ist, kann nicht Teil einer Stücklisten-Konfiguration sein',
                'en' => 'A product that is already a product set cannot be part of a product set configuration',
            ],
            'detail' => [
                'de' => sprintf(
                    'Das Produkt "%s" ("%s"; ID "%s") ist eine Stückliste. Das Produkt kann daher nicht einer anderen Stückliste zugeordnet werden.',
                    $productName,
                    $productNumber,
                    $productId,
                ),
                'en' => sprintf(
                    'The product "%s" ("%s"; ID "%s") is a product set. The product can therefore not be assigned to another product set.',
                    $productName,
                    $productNumber,
                    $productId,
                ),
            ],
            'meta' => [
                'productId' => $productId,
            ],
        ]);

        return new self($jsonApiError);
    }
}
