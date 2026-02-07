<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Installation\Steps;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareWms\DocumentPrintingConfig\Model\DocumentPrintingConfigDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\Context;

class WriteLegacyDocumentPrintingSettingsForExistingUsersInstallationStep
{
    private EntityManager $entityManager;
    private Context $context;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->context = Context::createDefaultContext();
    }

    public function writeLegacyDocumentPrintingSettings(): void
    {
        /** @var ShippingMethodCollection $allShippingMethods */
        $allShippingMethods = $this->entityManager->findAll(
            ShippingMethodDefinition::class,
            $this->context,
        );

        $documentPrintingConfigPayload = [];
        foreach ($allShippingMethods->getIds() as $shippingMethodId) {
            $documentPrintingConfigPayload[] = [
                'shippingMethodId' => $shippingMethodId,
                'copiesOfInvoices' => 1,
                'copiesOfDeliveryNotes' => 1,
            ];
        }

        $this->entityManager->create(
            DocumentPrintingConfigDefinition::class,
            $documentPrintingConfigPayload,
            $this->context,
        );
    }
}
