<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Reorder;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\A11yRenderedDocumentAware;
use Shopware\Core\Framework\Event\EventData\EntityCollectionType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Symfony\Contracts\EventDispatcher\Event;

class ReorderMailEvent extends Event implements ScalarValuesAware, MailAware, FlowEventAware, A11yRenderedDocumentAware
{
    public const EVENT_NAME = 'pickware_erp.reorder.reorder_mail';

    private Context $context;
    private ProductCollection $products;

    public function __construct(Context $context, ProductCollection $products)
    {
        $this->context = $context;
        $this->products = $products;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('products', new EntityCollectionType(ProductDefinition::class));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        // The only way we want to configure the mail recipient is through the flow configuration
        // because of this the Recipient is always empty here.
        return new MailRecipientStruct([]);
    }

    public function getSalesChannelId(): ?string
    {
        return null;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getValues(): array
    {
        return [
            'products' => $this->products,
        ];
    }

    public function getA11yDocumentIds(): array
    {
        return [];
    }
}
