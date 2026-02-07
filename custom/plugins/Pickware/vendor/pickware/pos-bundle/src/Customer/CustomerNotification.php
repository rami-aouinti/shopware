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
use Pickware\PickwarePos\Config\ConfigService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Event\PasswordRecoveryUrlEvent;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterSubscribeUrlEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomerNotification
{
    private EntityManager $entityManager;
    private EventDispatcherInterface $eventDispatcher;
    private AbstractSalesChannelContextFactory $salesChannelContextFactory;
    private SystemConfigService $systemConfigService;
    private ConfigService $configService;

    public function __construct(
        EntityManager $entityManager,
        EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'Shopware\\Core\\System\\SalesChannel\\Context\\SalesChannelContextFactory')]
        AbstractSalesChannelContextFactory $salesChannelContextFactory,
        SystemConfigService $systemConfigService,
        ConfigService $configService,
    ) {
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->systemConfigService = $systemConfigService;
        $this->configService = $configService;
    }

    /**
     * Dispatches a customer registration event which ultimately results in an email being sent to the customer that his
     * account was created successfully in the shop.
     */
    public function notifyCustomerRegistration(string $customerId, Context $context): void
    {
        $customer = $this->getCustomer($customerId, $context);
        $salesChannelContext = $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            $customer->getSalesChannelId(),
        );
        $this->eventDispatcher->dispatch(
            new CustomerRegisterEvent($salesChannelContext, $customer),
            CustomerRegisterEvent::EVENT_NAME,
        );
    }

    /**
     * Dispatches a customer account recovery event which ultimately results in an email being sent to the customer to
     * reset his password via a link.
     */
    public function notifyCustomerRecovery(string $customerId, Context $context): void
    {
        $customerRecoveries = $this->entityManager->findBy(
            CustomerRecoveryDefinition::class,
            ['customerId' => $customerId],
            $context,
            ['customer'],
        );
        /** @var CustomerRecoveryEntity|null $customerRecovery */
        $customerRecovery = $customerRecoveries->first();
        if ($customerRecovery === null) {
            return;
        }
        $salesChannelId = $customerRecovery->getCustomer()->getSalesChannelId();

        $publicSalesChannelDomain = $this->getPublicSalesChannelDomain($salesChannelId, $context);
        $salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), $salesChannelId);
        $this->eventDispatcher->dispatch(
            new CustomerAccountRecoverRequestEvent(
                $salesChannelContext,
                $customerRecovery,
                $this->getRecoveryUrl(
                    $salesChannelContext,
                    $customerRecovery->getHash(),
                    $publicSalesChannelDomain->getUrl(),
                    $customerRecovery,
                ),
            ),
            CustomerAccountRecoverRequestEvent::EVENT_NAME,
        );
    }

    /**
     * Dispatches a newsletter registration event which ultimately results in an email being sent to the customer to
     * confirm the newsletter subscription.
     */
    public function notifyNewsletterSubscription(string $customerId, Context $context): void
    {
        $customer = $this->getCustomer($customerId, $context);
        $publicSalesChannelDomain = $this->getPublicSalesChannelDomain(
            $customer->getSalesChannelId(),
            $context,
        );
        $newsletterRecipientsForEmail = $this->entityManager->findBy(
            NewsletterRecipientDefinition::class,
            [
                'email' => $customer->getEmail(),
                'languageId' => $publicSalesChannelDomain->getLanguageId(),
                'salesChannelId' => $publicSalesChannelDomain->getSalesChannelId(),
            ],
            $context,
        );
        $newsletterRecipient = $newsletterRecipientsForEmail->first();
        if ($newsletterRecipient === null) {
            return;
        }

        $salesChannelContext = $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            $customer->getSalesChannelId(),
        );
        $this->eventDispatcher->dispatch(
            new NewsletterRegisterEvent(
                $context,
                $newsletterRecipient,
                $this->getNewsletterSubscribeUrl(
                    $salesChannelContext,
                    $publicSalesChannelDomain->getUrl(),
                    $newsletterRecipient,
                ),
                $publicSalesChannelDomain->getSalesChannelId(),
            ),
            NewsletterRegisterEvent::EVENT_NAME,
        );
    }

    private function getCustomer(string $customerId, Context $context): CustomerEntity
    {
        return $this->entityManager->getByPrimaryKey(
            CustomerDefinition::class,
            $customerId,
            $context,
            ['defaultBillingAddress'],
        );
    }

    private function getRecoveryUrl(
        SalesChannelContext $context,
        string $hash,
        string $storefrontUrl,
        CustomerRecoveryEntity $customerRecovery,
    ): string {
        $urlTemplate = $this->systemConfigService->get(
            'core.loginRegistration.pwdRecoverUrl',
            $context->getSalesChannelId(),
        );
        if (!\is_string($urlTemplate)) {
            $urlTemplate = '/account/recover/password?hash=%%RECOVERHASH%%';
        }

        $urlEvent = new PasswordRecoveryUrlEvent($context, $urlTemplate, $hash, $storefrontUrl, $customerRecovery);
        $this->eventDispatcher->dispatch($urlEvent);

        return rtrim($storefrontUrl, '/') . str_replace('%%RECOVERHASH%%', $hash, $urlEvent->getRecoveryUrl());
    }

    private function getNewsletterSubscribeUrl(
        SalesChannelContext $context,
        string $storefrontUrl,
        NewsletterRecipientEntity $newsletterRecipient,
    ): string {
        $urlTemplate = $this->systemConfigService->get(
            'core.newsletter.subscribeUrl',
            $context->getSalesChannelId(),
        );
        if (!\is_string($urlTemplate)) {
            $urlTemplate = '/newsletter-subscribe?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%';
        }

        $hashedEmail = hash('sha1', $newsletterRecipient->getEmail());
        $urlEvent = new NewsletterSubscribeUrlEvent(
            $context,
            $urlTemplate,
            $hashedEmail,
            $newsletterRecipient->getHash(),
            $newsletterRecipient->jsonSerialize(),
            $newsletterRecipient,
        );
        $this->eventDispatcher->dispatch($urlEvent);

        $fullUrlPath = str_replace(
            [
                '%%HASHEDEMAIL%%',
                '%%SUBSCRIBEHASH%%',
            ],
            [
                $hashedEmail,
                $newsletterRecipient->getHash(),
            ],
            $urlEvent->getSubscribeUrl(),
        );

        return rtrim($storefrontUrl, '/') . $fullUrlPath;
    }

    private function getPublicSalesChannelDomain(
        string $configSalesChannelId,
        Context $context,
    ): SalesChannelDomainEntity {
        $publicSalesChannelDomainId = $this->configService->getPublicSalesChannelDomainId($configSalesChannelId);
        if ($publicSalesChannelDomainId === null) {
            /** @var SalesChannelEntity $salesChannel */
            $salesChannel = $this->entityManager->getByPrimaryKey(
                SalesChannelDefinition::class,
                $configSalesChannelId,
                $context,
            );

            throw CustomerCreationException::salesChannelDomainMissing($configSalesChannelId, $salesChannel->getName());
        }
        /** @var SalesChannelDomainEntity $publicSalesChannelDomain */
        $publicSalesChannelDomain = $this->entityManager->getByPrimaryKey(
            SalesChannelDomainDefinition::class,
            $publicSalesChannelDomainId,
            $context,
        );

        return $publicSalesChannelDomain;
    }
}
