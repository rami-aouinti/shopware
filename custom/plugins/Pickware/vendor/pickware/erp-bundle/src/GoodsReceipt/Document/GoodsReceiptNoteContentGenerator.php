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

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptEntity;
use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;

class GoodsReceiptNoteContentGenerator
{
    private const BARCODE_ACTION_PREFIX = '^A';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly BarcodeGeneratorSVG $barcodeGenerator = new BarcodeGeneratorSVG(),
    ) {}

    public function generateForGoodsReceipt(
        string $goodsReceiptId,
        string $languageId,
        Context $context,
    ): array {
        /** @var GoodsReceiptEntity $goodsReceipt */
        $goodsReceipt = $this->entityManager->getByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptId,
            $context,
            [
                'supplierOrders',
                'returnOrders.order',
                'returnOrders.order.lineItems.product',
                'returnOrders.order.billingAddress.country',
                'returnOrders.order.billingAddress.countryState',
                'returnOrders.order.deliveries.shippingMethod.translated',
                'returnOrders.order.deliveries.shippingOrderAddress.country',
                'returnOrders.order.deliveries.shippingOrderAddress.countryState',
                'returnOrders.order.transactions.paymentMethod.translated',
                'warehouse',
            ],
        );

        /** @var LanguageEntity $language */
        $language = $this->entityManager->getByPrimaryKey(LanguageDefinition::class, $languageId, $context, ['locale']);

        return [
            'goodsReceipt' => $goodsReceipt,
            'barcode' => $this->generateBarcode($goodsReceipt->getNumber()),
            'localeCode' => $language->getLocale()->getCode(),
        ];
    }

    private function generateBarcode(string $goodsReceiptNumber): string
    {
        $code = self::BARCODE_ACTION_PREFIX . $goodsReceiptNumber;
        $barcode = $this->barcodeGenerator->getBarcode(
            barcode: $code,
            type: BarcodeGenerator::TYPE_CODE_128,
            widthFactor: 1,
        );

        return 'data:image/svg+xml;base64,' . base64_encode($barcode);
    }
}
