<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Document;

use Pickware\DalBundle\ContextFactory;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;

class GoodsReceiptStockingListContentGenerator
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ContextFactory $contextFactory,
        private readonly GoodsReceiptStockingListElementFactory $goodsReceiptStockingListElementFactory,
    ) {}

    public function generateForGoodsReceipt(
        string $goodsReceiptId,
        string $languageId,
        Context $context,
    ): array {
        /** @var GoodsReceiptEntity $goodsReceipt */
        $goodsReceipt = $context->enableInheritance(
            function(Context $inheritanceContext) use ($languageId, $goodsReceiptId) {
                $localizedContext = $this->contextFactory->createLocalizedContext($languageId, $inheritanceContext);

                return $this->entityManager->getByPrimaryKey(
                    GoodsReceiptDefinition::class,
                    $goodsReceiptId,
                    $localizedContext,
                    ['warehouse'],
                );
            },
        );

        $goodsReceiptStockingListElements = $this->goodsReceiptStockingListElementFactory
            ->createGoodsReceiptStockingListElements(
                $goodsReceiptId,
                $context,
            );

        /** @var LanguageEntity $language */
        $language = $this->entityManager->getByPrimaryKey(LanguageDefinition::class, $languageId, $context, ['locale']);

        return [
            'goodsReceipt' => $goodsReceipt,
            'goodsReceiptStockingListElements' => $goodsReceiptStockingListElements,
            'localeCode' => $language->getLocale()->getCode(),
        ];
    }
}
