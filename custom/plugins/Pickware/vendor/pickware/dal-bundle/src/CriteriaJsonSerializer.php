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

use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CriteriaJsonSerializer
{
    public function __construct(
        #[Autowire(service: 'api.request_criteria_builder')]
        private readonly RequestCriteriaBuilder $requestCriteriaBuilder,
        private readonly EntityManager $entityManager,
    ) {}

    public function serializeToArray(Criteria $criteria): array
    {
        return $this->requestCriteriaBuilder->toArray($criteria);
    }

    /**
     * This is mostly a copy of Shopware\Core\Framework\DataAbstractionLayer\Search::RequestCriteriaBuilder with
     * different arguments but the same result. Also, the validation call
     * Shopware\Core\Framework\DataAbstractionLayer\Search\ApiCriteriaValidator::validate is missing.
     *
     * @param array<string, mixed> $payload
     * @param class-string<EntityDefinition<Entity>> $entityDefinitionClassName
     */
    public function deserializeFromArray(array $payload, string $entityDefinitionClassName): Criteria
    {
        $entityDefinition = $this->entityManager->getEntityDefinition($entityDefinitionClassName);

        return $this->requestCriteriaBuilder->fromArray(
            $payload,
            new Criteria(),
            $entityDefinition,
            new Context(new SystemSource()),
        );
    }
}
