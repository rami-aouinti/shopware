<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\Document;

use Pickware\DalBundle\ContextFactory;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Document\DocumentConfigLoader;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderDocumentType;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;

class SupplierOrderDocumentContentGenerator
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ContextFactory $contextFactory,
        private readonly DocumentConfigLoader $documentConfigLoader,
    ) {}

    public function generateFromSupplierOrder(
        string $supplierOrderId,
        string $languageId,
        Context $context,
    ): array {
        $supplierOrder = $context->enableInheritance(
            function(Context $inheritanceContext) use ($languageId, $supplierOrderId) {
                $localizedContext = $this->contextFactory->createLocalizedContext($languageId, $inheritanceContext);

                return $this->entityManager->getByPrimaryKey(
                    SupplierOrderDefinition::class,
                    $supplierOrderId,
                    $localizedContext,
                    [
                        'currency',
                        'lineItems.product.manufacturer',
                        'lineItems.product.extensions.pickwareErpProductSupplierConfigurations',
                        'supplier.address',
                        'supplier.language.locale',
                        'warehouse.address',
                    ],
                );
            },
        );

        /** @var LanguageEntity $language */
        $language = $this->entityManager->getByPrimaryKey(LanguageDefinition::class, $languageId, $context, ['locale']);

        $documentConfiguration = $this->documentConfigLoader->loadGlobalConfig(
            SupplierOrderDocumentType::TECHNICAL_NAME,
            $languageId,
            $context,
        );

        return [
            'supplierOrder' => $supplierOrder,
            'localeCode' => $language->getLocale()->getCode(),
            'config' => $documentConfiguration,
        ];
    }
}
