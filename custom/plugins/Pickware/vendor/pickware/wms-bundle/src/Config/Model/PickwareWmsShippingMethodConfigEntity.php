<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Config\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PickwareWmsShippingMethodConfigEntity extends Entity
{
    use EntityIdTrait;

    protected string $shippingMethodId;
    protected ?ShippingMethodEntity $shippingMethod = null;
    protected bool $createEnclosedReturnLabel;

    public function getShippingMethodId(): string
    {
        return $this->shippingMethodId;
    }

    public function setShippingMethodId(string $shippingMethodId): void
    {
        if ($this->shippingMethod && $this->shippingMethod->getId() !== $shippingMethodId) {
            $this->shippingMethod = null;
        }

        $this->shippingMethodId = $shippingMethodId;
    }

    public function getShippingMethod(): ShippingMethodEntity
    {
        if (!$this->shippingMethod) {
            throw new AssociationNotLoadedException('shippingMethod', $this);
        }

        return $this->shippingMethod;
    }

    public function setShippingMethod(?ShippingMethodEntity $shippingMethod): void
    {
        if ($shippingMethod) {
            $this->shippingMethodId = $shippingMethod->getId();
            $this->shippingMethod = $shippingMethod;
        }
    }

    public function getCreateEnclosedReturnLabel(): bool
    {
        return $this->createEnclosedReturnLabel;
    }

    public function setCreateEnclosedReturnLabel(bool $createEnclosedReturnLabel): void
    {
        $this->createEnclosedReturnLabel = $createEnclosedReturnLabel;
    }
}
