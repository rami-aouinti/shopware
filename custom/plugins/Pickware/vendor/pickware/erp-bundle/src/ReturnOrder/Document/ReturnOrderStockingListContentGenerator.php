<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder\Document;

use InvalidArgumentException;
use Pickware\DalBundle\ContextFactory;
use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\GoodsReceipt\FeatureFlags\GoodsReceiptForReturnOrderDevFeatureFlag;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;

class ReturnOrderStockingListContentGenerator
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ContextFactory $contextFactory,
        private readonly ReturnOrderStockingListElementFactory $returnOrderStockingListElementFactory,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    public function generateForReturnOrder(
        string $returnOrderId,
        string $languageId,
        Context $context,
    ): array {
        if ($this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)) {
            // Note: When the feature flag is removed, the whole code for generating a return order stocking list has
            // to be removed as well.
            throw new InvalidArgumentException(sprintf(
                'Creating a return order stocking list is not allowed when the feature flag "%s" is enabled.',
                GoodsReceiptForReturnOrderDevFeatureFlag::NAME,
            ));
        }

        /** @var ReturnOrderEntity $returnOrder */
        $returnOrder = $context->enableInheritance(
            function(Context $inheritanceContext) use ($languageId, $returnOrderId) {
                $localizedContext = $this->contextFactory->createLocalizedContext($languageId, $inheritanceContext);

                return $this->entityManager->getByPrimaryKey(
                    ReturnOrderDefinition::class,
                    $returnOrderId,
                    $localizedContext,
                    ['warehouse'],
                );
            },
        );

        $returnOrderStockingListElements = $this->returnOrderStockingListElementFactory
            ->createReturnOrderStockingListElements(
                $returnOrderId,
                $context,
            );

        /** @var LanguageEntity $language */
        $language = $this->entityManager->getByPrimaryKey(LanguageDefinition::class, $languageId, $context, ['locale']);

        return [
            'returnOrder' => $returnOrder,
            'returnOrderStockingListElements' => $returnOrderStockingListElements,
            'localeCode' => $language->getLocale()->getCode(),
        ];
    }
}
