<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Privacy;

use JsonSerializable;
use LogicException;

/**
 * A collection of removed field nodes. This is a tree with arbitrary width and depth, representing the same structure
 * as the object that has been sanitized, but only containing the elements that have been removed.
 */
class RemovedFieldTree implements JsonSerializable
{
    /**
     * @var RemovedFieldNode[] $removedFields
     */
    private array $removedFields;

    public function __construct(RemovedFieldNode ...$removedFields)
    {
        $this->removedFields = $removedFields;
    }

    public function addRemovedField(RemovedFieldNode $removedField): void
    {
        $this->removedFields[] = $removedField;
    }

    public function isEmpty(): bool
    {
        return empty($this->removedFields);
    }

    public function jsonSerialize(): array
    {
        // Assert this now, because fields can be renamed after being added to the tree.
        if (
            count(array_unique(array_map(
                fn(RemovedFieldNode $removedField) => $removedField->getName(),
                $this->removedFields,
            ))) !== count($this->removedFields)
        ) {
            throw new LogicException('Removed fields must have unique names.');
        }

        return array_merge(
            ...array_map(
                fn(RemovedFieldNode $removedField) => $removedField->jsonSerialize(),
                $this->removedFields,
            ),
        );
    }
}
