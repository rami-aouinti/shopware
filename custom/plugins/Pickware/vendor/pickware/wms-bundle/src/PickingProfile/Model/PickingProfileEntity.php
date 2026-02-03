<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProfile\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PickingProfileEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;
    protected int $position;

    /**
     * @var ?array <string, mixed>
     */
    protected ?array $filter;

    protected bool $isPartialDeliveryAllowed;
    protected ?PickingProfilePrioritizedShippingMethodCollection $prioritizedShippingMethods = null;
    protected ?PickingProfilePrioritizedPaymentMethodCollection $prioritizedPaymentMethods = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function getFilter(): ?array
    {
        return $this->filter;
    }

    /**
     * @param ?array<string, mixed> $filter
     */
    public function setFilter(?array $filter): void
    {
        $this->filter = $filter;
    }

    public function getIsPartialDeliveryAllowed(): bool
    {
        return $this->isPartialDeliveryAllowed;
    }

    public function setIsPartialDeliveryAllowed(bool $isPartialDeliveryAllowed): void
    {
        $this->isPartialDeliveryAllowed = $isPartialDeliveryAllowed;
    }

    public function getPrioritizedShippingMethods(): PickingProfilePrioritizedShippingMethodCollection
    {
        if (!$this->prioritizedShippingMethods) {
            throw new AssociationNotLoadedException('prioritizedShippingMethods', $this);
        }

        return $this->prioritizedShippingMethods;
    }

    public function setPrioritizedShippingMethods(?PickingProfilePrioritizedShippingMethodCollection $prioritizedShippingMethods): void
    {
        $this->prioritizedShippingMethods = $prioritizedShippingMethods;
    }

    public function getPrioritizedPaymentMethods(): PickingProfilePrioritizedPaymentMethodCollection
    {
        if (!$this->prioritizedPaymentMethods) {
            throw new AssociationNotLoadedException('prioritizedPaymentMethods', $this);
        }

        return $this->prioritizedPaymentMethods;
    }

    public function setPrioritizedPaymentMethods(?PickingProfilePrioritizedPaymentMethodCollection $prioritizedPaymentMethods): void
    {
        $this->prioritizedPaymentMethods = $prioritizedPaymentMethods;
    }
}
