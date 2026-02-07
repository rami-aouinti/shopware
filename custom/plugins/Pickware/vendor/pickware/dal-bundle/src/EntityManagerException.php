<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Throwable;

class EntityManagerException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_DAL_BUNDLE__ENTITY_MANAGER__';
    public const ERROR_CODE_ENTITY_WITH_PRIMARY_KEY_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'ENTITY_WITH_PRIMARY_KEY_NOT_FOUND';
    public const ERROR_CODE_ENTITY_WITH_CRITERIA_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'ENTITY_WITH_CRITERIA_NOT_FOUND';

    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError, ?Throwable $previous = null)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail());
    }

    /**
     * @param array|Criteria $criteria
     */
    public static function entityWithCriteriaNotFound(string $entityDefinitionClassName, $criteria): self
    {
        $entityName = (new $entityDefinitionClassName())->getEntityName();

        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_ENTITY_WITH_CRITERIA_NOT_FOUND,
            'title' => 'Entity with criteria not found',
            'detail' => sprintf(
                'The Entity of type "%s" with passed criteria was not found.',
                $entityName,
            ),
            'meta' => [
                'criteria' => $criteria,
                'entityDefinitionClassName' => $entityDefinitionClassName,
                'entityName' => $entityName,
            ],
        ]));
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    /**
     * @param string[]|string $primaryKey
     */
    public static function entityWithPrimaryKeyNotFound(string $entityDefinitionClassName, $primaryKey): self
    {
        $entityName = (new $entityDefinitionClassName())->getEntityName();

        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_ENTITY_WITH_PRIMARY_KEY_NOT_FOUND,
            'title' => 'Entity with primary key not found',
            'detail' => sprintf(
                'The Entity of type %s with primary key %s was not found.',
                $entityName,
                is_array($primaryKey) ? ('(' . implode(', ', $primaryKey) . ')') : $primaryKey,
            ),
            'meta' => [
                'primaryKey' => $primaryKey,
                'entityDefinitionClassName' => $entityDefinitionClassName,
                'entityName' => $entityName,
            ],
        ]));
    }
}
