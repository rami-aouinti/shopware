<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\BranchStore;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Pickware\PickwarePos\BranchStore\Model\BranchStoreEntity;

class BranchStoreException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_POS__BRANCH_STORE__';
    private const ERROR_CODE_BRANCH_STORE_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'BRANCH_STORE_NOT_FOUND';
    private const ERROR_CODE_BRANCH_STORE_HAS_NO_SALES_CHANNEL = self::ERROR_CODE_NAMESPACE . 'BRANCH_STORE_HAS_NO_SALES_CHANNEL';

    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function createBranchStoreNotFoundError(string $id): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::ERROR_CODE_BRANCH_STORE_NOT_FOUND,
            'title' => 'Branch store not found',
            'detail' => sprintf('The branch store with id "%s" could not be found.', $id),
            'meta' => ['id' => $id],
        ]);

        return new self($jsonApiError);
    }

    public static function branchStoreHasNoSalesChannel(BranchStoreEntity $branchStore): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::ERROR_CODE_BRANCH_STORE_HAS_NO_SALES_CHANNEL,
            'title' => 'Branch store has no sales channel',
            'detail' => sprintf(
                'The branch store "%s" with id "%s" has no sales channel assigned.',
                $branchStore->getName(),
                $branchStore->getId(),
            ),
            'meta' => [
                'id' => $branchStore->getId(),
                'name' => $branchStore->getName(),
            ],
        ]);

        return new self($jsonApiError);
    }
}
