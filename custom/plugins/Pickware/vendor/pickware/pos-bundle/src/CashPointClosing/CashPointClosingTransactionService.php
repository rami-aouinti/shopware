<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing;

use Pickware\DalBundle\DalPayloadSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;

class CashPointClosingTransactionService
{
    private EntityManager $entityManager;
    private DalPayloadSerializer $dalPayloadSerializer;

    public function __construct(
        EntityManager $entityManager,
        DalPayloadSerializer $dalPayloadSerializer,
    ) {
        $this->entityManager = $entityManager;
        $this->dalPayloadSerializer = $dalPayloadSerializer;
    }

    public function saveCashPointClosingTransaction(array $payload, string $userId, Context $context): void
    {
        /** @var UserEntity $user */
        $user = $this->entityManager->getByPrimaryKey(UserDefinition::class, $userId, $context);
        $userPayload = [
            'userId' => $user->getId(),
            'userSnapshot' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'timeZone' => $user->getTimeZone(),
            ],
        ];

        $transaction = CashPointClosingTransaction::fromArray(array_merge(
            $payload,
            $userPayload,
        ));

        $this->entityManager->createIfNotExists(
            CashPointClosingTransactionDefinition::class,
            [$this->dalPayloadSerializer->getDalPayload($transaction)],
            $context,
        );
    }
}
