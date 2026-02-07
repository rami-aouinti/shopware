<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\InvoiceCorrection\Model\Subscriber;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\InvoiceCorrection\Model\PickwareDocumentVersionDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InsertDocumentSubscriber implements EventSubscriberInterface
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'document.written' => 'documentWritten',
        ];
    }

    /**
     * This is a workaround to fix a problem where shopware updates the orderVersionId of a document if the document
     * is updated without passing the orderVersionId. This causes the orderVersionId of the document to be the live
     * version. Since our InvoiceCorrection depends on order versions these resulted in a wrong calculation of the
     * order difference. To fix this we save the orderVersionId in a new entity when a new document is created.
     * This entity will then be used in the InvoiceCorrectionCalculator to get its orderVersionId instead.
     *
     * See more here:
     * - https://github.com/pickware/shopware-plugins/issues/4709
     * - https://issues.shopware.com/issues/NEXT-29601
     */
    public function documentWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        $orderVersionPayload = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_INSERT) {
                return;
            }

            $payload = $writeResult->getPayload();
            $orderVersionPayload[] = [
                'documentId' => $payload['id'],
                'orderId' => $payload['orderId'],
                'orderVersionId' => $payload['orderVersionId'],
            ];
        }

        // We need to create this entity in system scope to guarantee compatibility with the acl restricted wms and pos
        // roles and not introduce breaking changes against both of these plugins.
        $entityWrittenEvent->getContext()->scope(
            Context::SYSTEM_SCOPE,
            function() use ($orderVersionPayload, $entityWrittenEvent): void {
                $this->entityManager->create(
                    PickwareDocumentVersionDefinition::class,
                    $orderVersionPayload,
                    $entityWrittenEvent->getContext(),
                );
            },
        );
    }
}
