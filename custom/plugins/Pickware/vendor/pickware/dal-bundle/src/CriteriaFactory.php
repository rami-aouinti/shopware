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

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class CriteriaFactory
{
    private CriteriaJsonSerializer $criteriaJsonSerializer;

    public function __construct(CriteriaJsonSerializer $criteriaJsonSerializer)
    {
        $this->criteriaJsonSerializer = $criteriaJsonSerializer;
    }

    /**
     * @param class-string<EntityDefinition<Entity>> $entityDefinitionClassName
     * @param string[] $entityIds
     * @param list<string> $entityAssociations
     */
    public function makeCriteriaForEntitiesIdentifiedByIdWithAssociations(
        string $entityDefinitionClassName,
        array $entityIds,
        array $entityAssociations,
    ): Criteria {
        return $this->criteriaJsonSerializer->deserializeFromArray(
            [
                'ids' => $entityIds,
                'associations' => $entityAssociations,
            ],
            $entityDefinitionClassName,
        );
    }
}
