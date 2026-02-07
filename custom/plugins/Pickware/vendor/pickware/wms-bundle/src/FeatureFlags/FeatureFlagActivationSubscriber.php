<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\FeatureFlags;

use Pickware\FeatureFlagBundle\PickwareFeatureFlagsFilterEvent;
use Pickware\PickwareErpStarter\GoodsReceipt\FeatureFlags\GoodsReceiptAdditionalInformationProdFeatureFlag;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionFeatureFlag;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyProductionFeatureFlag;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderManagementFeatureFlag;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderViewFeatureFlag;
use Pickware\PickwareErpStarter\Warehouse\Model\Subscriber\BinLocationPositionFeatureFlag;
use Pickware\PickwareErpStarter\Warehouse\WarehouseStockNotAvailableForSaleFeatureFlag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FeatureFlagActivationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PickwareFeatureFlagsFilterEvent::class => [
                'activateFeatureFlags',
                PickwareFeatureFlagsFilterEvent::PRIORITY_ON_PREMISES,
            ],
        ];
    }

    public function activateFeatureFlags(PickwareFeatureFlagsFilterEvent $event): void
    {
        $event->getFeatureFlags()->getByName(BinLocationPositionFeatureFlag::NAME)?->enable();
        if (class_exists(GoodsReceiptAdditionalInformationProdFeatureFlag::class)) {
            $event->getFeatureFlags()->getByName(GoodsReceiptAdditionalInformationProdFeatureFlag::NAME)?->enable();
        }
        if (class_exists(InvoiceCorrectionFeatureFlag::class)) {
            $event->getFeatureFlags()->getByName(InvoiceCorrectionFeatureFlag::NAME)?->enable();
        }
        if (class_exists(PickingPropertyProductionFeatureFlag::class)) {
            $event->getFeatureFlags()->getByName(PickingPropertyProductionFeatureFlag::NAME)?->enable();
        }
        if (class_exists(ReturnOrderManagementFeatureFlag::class)) {
            $event->getFeatureFlags()->getByName(ReturnOrderManagementFeatureFlag::NAME)?->enable();
        }
        if (class_exists(ReturnOrderViewFeatureFlag::class)) {
            $event->getFeatureFlags()->getByName(ReturnOrderViewFeatureFlag::NAME)?->enable();
        }
        if (class_exists(WarehouseStockNotAvailableForSaleFeatureFlag::class)) {
            $event->getFeatureFlags()->getByName(WarehouseStockNotAvailableForSaleFeatureFlag::NAME)?->enable();
        }

        // GoodsReceiptManagementFeatureFlag and ProductBarcodeLabelCreationFeatureFlag were introduced after their
        // respective feature flags have been added. Hence we cannot use those classes without increasing the version
        // constraint on PickwareErpStarter. Instead, we enable the feature flags by their name.
        $event->getFeatureFlags()->getByName('pickware-erp.feature.goods-receipt-management')?->enable();
        $event->getFeatureFlags()->getByName('pickware-erp.feature.product-barcode-label-creation')?->enable();
    }
}
