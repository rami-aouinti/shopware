<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\MailDraft\MailDraftException;
use Pickware\PickwareErpStarter\MailDraft\MailTemplateContentGenerator;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SupplierOrderMailTemplateContentGenerator implements MailTemplateContentGenerator
{
    private EntityManager $entityManager;
    private SystemConfigService $systemConfigService;

    public const OPTION_SUPPLIER_ORDER_ID = 'supplierOrderId';

    public function __construct(EntityManager $entityManager, SystemConfigService $systemConfigService)
    {
        $this->entityManager = $entityManager;
        $this->systemConfigService = $systemConfigService;
    }

    public function generateContent(Context $context, array $options = []): array
    {
        $content = [];

        $supplierOrderId = $options[self::OPTION_SUPPLIER_ORDER_ID] ?? null;
        if (!$supplierOrderId || !Uuid::isValid($supplierOrderId)) {
            throw MailDraftException::invalidTemplateContentGeneratorOption(self::OPTION_SUPPLIER_ORDER_ID);
        }

        /** @var SupplierOrderEntity $supplierOrder */
        $supplierOrder = $this->entityManager->findByPrimaryKey(
            SupplierOrderDefinition::class,
            $supplierOrderId,
            $context,
            ['supplier.address.salutation'],
        );
        if (!$supplierOrder) {
            throw MailDraftException::invalidTemplateContentGeneratorOption(self::OPTION_SUPPLIER_ORDER_ID);
        }

        $address = $supplierOrder->getSupplier()->getAddress();
        $salutation = [];
        if ($address) {
            $addFirstName = $address->getFirstName() && trim($address->getFirstName()) !== '';
            $addLastName = $address->getLastName() && trim($address->getLastName()) !== '';

            // Only add a letterName if either a first or last name exists
            if ($addFirstName || $addLastName) {
                if (
                    $address->getSalutation()
                    && $address->getSalutation()->getLetterName()
                    && trim($address->getSalutation()->getLetterName()) !== ''
                ) {
                    $salutation['letterName'] = trim($address->getSalutation()->getLetterName());
                }

                if ($address->getTitle() && trim($address->getTitle()) !== '') {
                    $salutation['title'] = trim($address->getTitle());
                }
            }
            if ($addFirstName) {
                $salutation['firstName'] = trim($address->getFirstName());
            }
            if ($addLastName) {
                $salutation['lastName'] = trim($address->getLastName());
            }
        }

        $content['supplierOrder'] = $supplierOrder;
        $content['salutation'] = $salutation;
        $content['shopName'] = $this->systemConfigService->getString('core.basicInformation.shopName');

        return $content;
    }

    public function getMailTemplateTypeTechnicalName(): string
    {
        return SupplierOrderMailTemplate::MAIL_TEMPLATE_TYPE_TECHNICAL_NAME;
    }
}
