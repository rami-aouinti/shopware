<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\StateTransitioning;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Defines the target state field
 * @template-covariant ReferencedEntity of Entity
 */
#[Exclude]
class EntityStateDefinition
{
    /**
     * @param class-string<EntityDefinition<ReferencedEntity>> $entityDefinitionClassName The definition class name of the entity
     *     containing the state
     * @param string $stateIdFieldName The name of the field in the entity that contains the state ID
     */
    public function __construct(
        readonly private string $entityDefinitionClassName,
        readonly private string $stateIdFieldName,
    ) {}

    /**
     * @return self<OrderEntity>
     */
    public static function order(): self
    {
        return new self(OrderDefinition::class, 'stateId');
    }

    /**
     * @return self<OrderTransactionEntity>
     */
    public static function orderTransaction(): self
    {
        return new self(OrderTransactionDefinition::class, 'stateId');
    }

    /**
     * @return self<OrderDeliveryEntity>
     */
    public static function orderDelivery(): self
    {
        return new self(OrderDeliveryDefinition::class, 'stateId');
    }

    /**
     * @return class-string<EntityDefinition<ReferencedEntity>>
     */
    public function getEntityDefinitionClassName(): string
    {
        return $this->entityDefinitionClassName;
    }

    public function getStateIdFieldName(): string
    {
        return $this->stateIdFieldName;
    }
}
