<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery;

use Pickware\DalBundle\EntityDeletionProtection\DeletionProtector;
use Pickware\DalBundle\EntityDeletionProtection\WriteConstraintViolationExceptionFactory;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareWms\Delivery\Model\DeliveryCollection;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\PickingProcess\DeliveryStateMachine;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

class OrderDeletionProtector implements DeletionProtector
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function getEntityDefinitionEntityName(): string
    {
        return OrderDefinition::ENTITY_NAME;
    }

    /**
     * @return bool Returns "true" (delete protected) if there are any deliveries for the order in a pending state.
     */
    public function isDeletionProtected(DeleteCommand $command, Context $context): bool
    {
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return false;
        }

        $orderId = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);
        /** @var DeliveryCollection $deliveries */
        $deliveries = $this->entityManager->findBy(
            DeliveryDefinition::class,
            ['orderId' => $orderId],
            $context,
            ['state'],
        );
        foreach ($deliveries as $delivery) {
            if (in_array($delivery->getState()->getTechnicalName(), DeliveryStateMachine::PENDING_STATES)) {
                return true;
            }
        }

        return false;
    }

    public function getException(DeleteCommand $command, Context $context): WriteConstraintViolationException
    {
        return WriteConstraintViolationExceptionFactory::create(
            'Orders with active WMS deliveries cannot be deleted.',
        );
    }
}
