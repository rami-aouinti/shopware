<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Installation\Steps;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwarePos\PickwarePosBundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;

class EnsureSalesChannelTypeInstallationStep
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function install(Context $context): void
    {
        $this->entityManager->upsert(SalesChannelTypeDefinition::class, [
            [
                'id' => PickwarePosBundle::SALES_CHANNEL_TYPE_ID,
                'name' => [
                    'en-GB' => 'Pickware POS',
                    'de-DE' => 'Pickware POS',
                ],
                'manufacturer' => [
                    'en-GB' => 'Pickware GmbH',
                    'de-DE' => 'Pickware GmbH',
                ],
                'iconName' => 'pw-pos-icon-navigation',
                'description' => [
                    'en-GB' => 'Sales channel for Pickware POS',
                    'de-DE' => 'Verkaufskanal für Pickware POS',
                ],
                'descriptionLong' => [
                    'en-GB' => 'This sales channel is used for sales at the POS, which create orders with this sales ' .
                        'channel in your shop. We recommend that you create a separate sales channel for each branch ' .
                        'store, so that you can seperate them easier in further analyses. Please note that this ' .
                        'sales channel doesn’t have a storefront and only gets used by the Pickware POS app.',
                    'de-DE' => 'Dieser Sales Channel wird für Verkäufe am POS verwendet, die als Bestellungen mit ' .
                        'diesem Sales Channel im Shop angelegt werden. Wir empfehlen dir zu Auswertungszwecken für ' .
                        'jede Filiale einen eigenen Sales Channel anzulegen.  Beachte, dass dieser Sales Channel ' .
                        'keine Storefront besitzt und nur von der Pickware POS App verwendet wird.',
                ],
                'screenshotUrls' => [
                    'pickwarepos/static/img/pickware-pos-sales-channel-screenshot-1.svg',
                ],

            ],
        ], $context);
    }
}
