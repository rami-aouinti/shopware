<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class BatchManagedProductDeletionException extends Exception implements JsonApiErrorSerializable
{
    public function __construct(private readonly LocalizableJsonApiError $jsonApiError)
    {
        parent::__construct($jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): LocalizableJsonApiError
    {
        return $this->jsonApiError;
    }

    /**
     * @param list<string> $productNumbers
     */
    public static function batchManagedProductsCannotBeDeleted(array $productNumbers): self
    {
        natsort($productNumbers);
        $isSingular = count($productNumbers) === 1;
        $productNumberList = implode(', ', $productNumbers);

        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'Cannot delete batch-managed products',
                    'de' => 'Chargengeführte Produkte können nicht gelöscht werden',
                ],
                'detail' => [
                    'en' => sprintf(
                        ($isSingular ? 'The product %s is' : 'The products %s are') . ' batch-managed and cannot be deleted.',
                        $productNumberList,
                    ),
                    'de' => sprintf(
                        ($isSingular ? 'Das Produkt %s ist' : 'Die Produkte %s sind') . ' chargengeführt und können nicht gelöscht werden.',
                        $productNumberList,
                    ),
                ],
                'meta' => [
                    'productNumbers' => $productNumbers,
                ],
            ]),
        );
    }
}
