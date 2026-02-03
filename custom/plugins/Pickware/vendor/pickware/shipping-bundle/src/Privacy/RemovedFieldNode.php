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
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A removed field with reasons for the removal, or with removed subfields.
 */
#[Exclude]
class RemovedFieldNode implements JsonSerializable
{
    private string $name;

    /** @var null|String[] */
    private ?array $reasons = null;

    private ?RemovedFieldTree $nestedRemovedFields = null;

    private function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function fromReason(string $name, string $reason): RemovedFieldNode
    {
        $node = new RemovedFieldNode($name);
        // Currently we only ever have one reason for removal.
        // In the future a method might be added to add multiple reasons for the same field.
        $node->reasons = [$reason];

        return $node;
    }

    public static function fromRemovedFields(string $name, RemovedFieldTree $removedFields): RemovedFieldNode
    {
        $node = new RemovedFieldNode($name);
        $node->nestedRemovedFields = $removedFields;

        return $node;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function jsonSerialize(): ?array
    {
        if ($this->reasons !== null) {
            return [$this->name => $this->reasons];
        }
        if ($this->nestedRemovedFields !== null) {
            return [$this->name => $this->nestedRemovedFields->jsonSerialize()];
        }

        throw new LogicException('Cannot serialize a removed field node without reasons or nested removed fields.');
    }
}
