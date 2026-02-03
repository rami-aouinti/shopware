<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PaymentCapture\Subscriber;

use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\PaymentCapture\ExternalOrderTransactionCapture;
use Pickware\DatevBundle\PaymentCapture\ExternalOrderTransactionCapturedEvent;
use Pickware\DatevBundle\PaymentCapture\Model\PaymentCaptureDefinition;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaymentCaptureOnExternalOrderTransactionCaptureSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ExternalOrderTransactionCapturedEvent::class => 'externalOrderTransactionCaptured',
        ];
    }

    public function externalOrderTransactionCaptured(ExternalOrderTransactionCapturedEvent $event): void
    {
        /** @var OrderCollection $orders */
        $orders = $this->entityManager->findBy(
            OrderDefinition::class,
            [
                'id' => $event->getCaptures()
                    ->map(fn(ExternalOrderTransactionCapture $capture) => $capture->getOrderId())
                    ->asArray(),
            ],
            $event->getContext(),
        );

        $this->entityManager->create(
            PaymentCaptureDefinition::class,
            $event->getCaptures()
                ->map(fn(ExternalOrderTransactionCapture $capture) => [
                    'id' => Uuid::randomHex(),
                    'type' => PaymentCaptureDefinition::TYPE_AUTOMATIC,
                    'amount' => $capture->getAmount(),
                    'originalAmount' => $capture->getAmount(),
                    'transactionDate' => $capture->getProcessedAt(),
                    'currencyId' => $orders->get($capture->getOrderId())->getCurrencyId(),
                    'orderId' => $capture->getOrderId(),
                    'orderTransactionId' => null,
                    'transactionReference' => $capture->getTransactionReference(),
                ])
                ->asArray(),
            $event->getContext(),
        );
    }
}
