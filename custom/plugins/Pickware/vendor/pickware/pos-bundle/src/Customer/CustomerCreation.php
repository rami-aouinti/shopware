<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Customer;

use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityWrittenContainerEventExtension;
use Pickware\PickwarePos\Config\ConfigService;
use Pickware\PickwarePos\PickwarePosBundle;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CustomerCreation
{
    private EntityManager $entityManager;
    private NumberRangeValueGeneratorInterface $numberRangeValueGenerator;
    private SystemConfigService $systemConfigService;
    private ConfigService $configService;

    public function __construct(
        EntityManager $entityManager,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        SystemConfigService $systemConfigService,
        ConfigService $configService,
    ) {
        $this->entityManager = $entityManager;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->systemConfigService = $systemConfigService;
        $this->configService = $configService;
    }

    public function createPosCustomerIfNotExists(array $customerPayload, Context $context): EntityWrittenContainerEvent
    {
        $customerId = $customerPayload['id'];
        /** @var CustomerEntity $existingCustomer */
        $existingCustomer = $this->entityManager->findByPrimaryKey(
            CustomerDefinition::class,
            $customerId,
            $context,
        );
        if ($existingCustomer) {
            return EntityWrittenContainerEventExtension::makeEmptyEntityWrittenContainerEvent($context);
        }

        $existingCustomers = $this->entityManager->findBy(
            CustomerDefinition::class,
            ['email' => $customerPayload['email']],
            $context,
        );
        if ($existingCustomers->count() > 0) {
            throw CustomerCreationException::customerAlreadyExists($existingCustomers->first()->getEmail());
        }

        $this->ensureCustomerPayloadHasDefaultPaymentMethodId($customerPayload);

        $customerWrittenContainerEvent = null;
        $this->entityManager->runInTransactionWithRetry(function() use (
            $customerId,
            $customerPayload,
            $context,
            &$customerWrittenContainerEvent
        ): void {
            $this->ensureCustomerPayloadHasCustomerNumber($customerPayload, $context);
            $customerWrittenContainerEvent = $this->entityManager->create(
                CustomerDefinition::class,
                [$customerPayload],
                $context,
            );
            $this->createCustomerRecovery($customerId, $context);
        });

        return $customerWrittenContainerEvent;
    }

    public function createNewsletterSubscriptionIfNotExists(string $customerId, Context $context): EntityWrittenContainerEvent
    {
        /** @var CustomerEntity $customer */
        $customer = $this->entityManager->getByPrimaryKey(
            CustomerDefinition::class,
            $customerId,
            $context,
            ['defaultBillingAddress'],
        );
        $publicSalesChannelDomainId = $this->configService->getPublicSalesChannelDomainId(
            $customer->getSalesChannelId(),
        );
        if ($publicSalesChannelDomainId === null) {
            /** @var SalesChannelEntity $salesChannel */
            $salesChannel = $this->entityManager->getByPrimaryKey(
                SalesChannelDefinition::class,
                $customer->getSalesChannelId(),
                $context,
            );

            throw CustomerCreationException::salesChannelDomainMissing(
                $customer->getSalesChannelId(),
                $salesChannel->getName(),
            );
        }
        /** @var SalesChannelDomainEntity $publicSalesChannelDomain */
        $publicSalesChannelDomain = $this->entityManager->getByPrimaryKey(
            SalesChannelDomainDefinition::class,
            $publicSalesChannelDomainId,
            $context,
        );

        $existingNewsletterRecipientsForEmail = $this->entityManager->findBy(
            NewsletterRecipientDefinition::class,
            [
                'email' => $customer->getEmail(),
                'languageId' => $publicSalesChannelDomain->getLanguageId(),
                'salesChannelId' => $publicSalesChannelDomain->getSalesChannelId(),
            ],
            $context,
        );
        if ($existingNewsletterRecipientsForEmail->count() > 0) {
            return EntityWrittenContainerEventExtension::makeEmptyEntityWrittenContainerEvent($context);
        }

        return $this->entityManager->create(NewsletterRecipientDefinition::class, [
            [
                'id' => Uuid::randomHex(),
                'languageId' => $publicSalesChannelDomain->getLanguageId(),
                'salesChannelId' => $publicSalesChannelDomain->getSalesChannelId(),
                // `notSet` is the correct status for an unconfirmed newsletter subscription, see: https://github.com/shopware/shopware/blob/90a9ba9e7514c264435505f191eb92dec5cec1a9/src/Core/Content/Newsletter/SalesChannel/NewsletterSubscribeRoute.php#L317
                'status' => NewsletterSubscribeRoute::STATUS_NOT_SET,
                'hash' => Uuid::randomHex(),
                'email' => $customer->getEmail(),
                'title' => $customer->getTitle(),
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'zipCode' => $customer->getDefaultBillingAddress()->getZipcode(),
                'city' => $customer->getDefaultBillingAddress()->getCity(),
                'street' => $customer->getDefaultBillingAddress()->getStreet(),
                'tags' => [],
                'salutationId' => $customer->getSalutationId(),
            ],
        ], $context);
    }

    private function ensureCustomerPayloadHasDefaultPaymentMethodId(array &$customerPayload): void
    {
        if (!empty($customerPayload['defaultPaymentMethodId']) || !empty($customerPayload['defaultPaymentMethod'])) {
            return;
        }

        $customerPayload['defaultPaymentMethodId'] = $this->getPaymentMethodIdForNewCustomer(
            $customerPayload['salesChannelId'],
        );
    }

    private function getPaymentMethodIdForNewCustomer(?string $salesChannelId = null): ?string
    {
        return $this->systemConfigService->get(
            PickwarePosBundle::PLUGIN_CONFIG_DOMAIN . '.posCashPaymentMethodId',
            $salesChannelId,
        );
    }

    private function ensureCustomerPayloadHasCustomerNumber(array &$customerPayload, Context $context): void
    {
        if (!empty($customerPayload['customerNumber'])) {
            return;
        }

        $customerPayload['customerNumber'] = $this->numberRangeValueGenerator->getValue(
            CustomerDefinition::ENTITY_NAME,
            $context,
            $customerPayload['salesChannelId'],
        );
    }

    private function createCustomerRecovery(string $customerId, Context $context): void
    {
        $this->entityManager->create(CustomerRecoveryDefinition::class, [
            [
                'id' => Uuid::randomHex(),
                'customerId' => $customerId,
                'hash' => Random::getAlphanumericString(32),
            ],
        ], $context);
    }
}
