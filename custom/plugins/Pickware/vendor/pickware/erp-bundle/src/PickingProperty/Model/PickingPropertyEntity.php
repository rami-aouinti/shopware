<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PickingProperty\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PickingPropertyEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;
    protected ?ProductCollection $products = null;
    protected bool $showOnOrderDocuments;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getProducts(): ProductCollection
    {
        if (!$this->products) {
            throw new AssociationNotLoadedException('products', $this);
        }

        return $this->products;
    }

    public function setProducts(?ProductCollection $products): void
    {
        $this->products = $products;
    }

    public function getShowOnOrderDocuments(): bool
    {
        return $this->showOnOrderDocuments;
    }

    public function setShowOnOrderDocuments(bool $showOnOrderDocuments): void
    {
        $this->showOnOrderDocuments = $showOnOrderDocuments;
    }
}
