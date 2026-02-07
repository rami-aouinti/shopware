<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle\Association\Exception;

use Pickware\DalBundle\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class AssociationNotLoadedException extends DataAbstractionLayerException
{
    /**
     * @param Entity $entity Put $this here
     */
    public function __construct(string $associationFieldName, Entity $entity)
    {
        $message = sprintf(
            'The association "%s" of the entity (id=%s, instance of %s) was not loaded. Please provide the named ' .
            'association in the passed %s when fetching the entity.',
            $associationFieldName,
            $entity->getUniqueIdentifier(),
            get_class($entity),
            Criteria::class,
        );

        parent::__construct($message);
    }
}
