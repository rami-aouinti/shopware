<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Installation;

use Doctrine\DBAL\Connection;
use Pickware\AclBundle\Acl\AclRoleFactory;
use Pickware\DalBundle\DefaultTranslationProvider;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\IdResolver\EntityIdResolver;
use Pickware\InstallationLibrary\AclRole\AclRoleInstaller;
use Pickware\InstallationLibrary\AclRole\AclRoleUninstaller;
use Pickware\InstallationLibrary\DocumentType\DocumentTypeInstaller;
use Pickware\InstallationLibrary\MailTemplate\MailTemplateInstaller;
use Pickware\InstallationLibrary\MailTemplate\MailTemplateUninstaller;
use Pickware\PickwarePos\Acl\PickwarePosFeaturePermissionsProvider;
use Pickware\PickwarePos\Installation\Steps\CreateDefaultConfigInstallationStep;
use Pickware\PickwarePos\Installation\Steps\CreateDeliveryTimeInstallationStep;
use Pickware\PickwarePos\Installation\Steps\CreatePaymentMethodsInstallationStep;
use Pickware\PickwarePos\Installation\Steps\CreatePosCustomerGroupFallbackInstallationStep;
use Pickware\PickwarePos\Installation\Steps\CreatePosCustomerGroupInstallationStep;
use Pickware\PickwarePos\Installation\Steps\CreatePosCustomerInstallationStep;
use Pickware\PickwarePos\Installation\Steps\CreatePosSalesChannelInstallationStep;
use Pickware\PickwarePos\Installation\Steps\CreateShippingMethodsInstallationStep;
use Pickware\PickwarePos\Installation\Steps\EnsureDefaultRuleInstallationStep;
use Pickware\PickwarePos\Installation\Steps\EnsureSalesChannelTypeInstallationStep;
use Pickware\PickwarePos\OrderDocument\CouponReceiptDocumentType;
use Pickware\PickwarePos\OrderDocument\CouponReceiptMailTemplate;
use Pickware\PickwarePos\OrderDocument\ReceiptDocumentType;
use Pickware\PickwarePos\OrderDocument\ReceiptMailTemplate;
use Pickware\PickwarePos\OrderDocument\ReturnOrderReceiptDocumentType;
use Pickware\PickwarePos\OrderDocument\ReturnOrderReceiptMailTemplate;
use Pickware\PickwarePos\SalesChannel\PickwarePosSalesChannelService;
use Pickware\ShopwareExtensionsBundle\Mail\MailSendSuppressionService;
use ReflectionClass;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\NumberRange\Api\NumberRangeController;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PickwarePosInstaller
{
    // The following IDs are only used for idempotent installation. Don't use them in any non-installation part of the
    // code, as the user can delete the entities. Don't assume these entities will still exist at any time.

    public const PAYMENT_METHOD_ID_CASH = '0f966e25cd1c4c4599331b0e91d63ce9';
    public const PAYMENT_METHOD_TECHNICAL_NAME_CASH = 'pw_pos_cash';
    public const PAYMENT_METHOD_ID_CARD = '7767147cc6d34632be77f494a3724d48';
    public const PAYMENT_METHOD_TECHNICAL_NAME_CARD = 'pw_pos_card';
    public const PAYMENT_METHOD_ID_PAY_ON_COLLECTION = 'c4a2c7003fd54a749cc89bbcfd8805f5';
    public const PAYMENT_METHOD_TECHNICAL_NAME_PAY_ON_COLLECTION = 'pw_pos_pay_on_collection';
    public const PAYMENT_METHOD_IDS_AVAILABLE_AT_POS = [
        self::PAYMENT_METHOD_ID_CASH,
        self::PAYMENT_METHOD_ID_CARD,
    ];
    public const SHIPPING_METHOD_ID_POS = '923469263e0d4b58b38636346b8e5d6c';
    public const SHIPPING_METHOD_TECHNICAL_NAME_POS = 'pw_pos_pos_delivery';
    public const SHIPPING_METHOD_ID_CLICK_AND_COLLECT = 'b7805a59e9df43cc8a51c0a1704026d3';
    public const SHIPPING_METHOD_TECHNICAL_NAME_CLICK_AND_COLLECT = 'pw_pos_click_and_collect';
    public const SHIPPING_METHOD_IDS_AVAILABLE_AT_POS = [
        self::SHIPPING_METHOD_ID_POS,
    ];
    public const DELIVERY_TIME_ID_SELF_COLLECTION = 'e16586f082d04011b2025fa75fecbe07';
    public const DELIVERY_TIME_ID_INSTANT = 'd989deac21904b71a05d09d936732fd9';
    public const CUSTOMER_ID_POS = '853444f516114e33b1d025a026c234f0';
    public const CUSTOMER_GROUP_ID_POS = 'c097088b4aff4dc89c5ba9d933ed4176';

    // Since Shopware removed the default customer group ID from the Defaults in 6.5.0.0, we provide the installation
    // with the ID, that is still the same and already present in the DB, instead.
    // If it is not created, an installation step creates the fallback customer group entity.
    // see CreatePosCustomerGroupFallbackInstallationStep
    public const CUSTOMER_GROUP_FALLBACK_ID = 'cfbd5018d38d41d8adca10d94fc8bdd6';

    private SystemConfigService $systemConfigService;
    private PickwarePosSalesChannelService $pickwarePosSalesChannelService;
    private EntityManager $entityManager;
    private DocumentTypeInstaller $documentTypeInstaller;
    private EntityIdResolver $entityIdResolver;
    private NumberRangeValueGeneratorInterface $numberRangeValueGenerator;
    private MailTemplateInstaller $mailTemplateInstaller;
    private MailTemplateUninstaller $mailTemplateUninstaller;
    private AclRoleInstaller $aclRoleInstaller;
    private AclRoleUninstaller $aclRoleUninstaller;
    private MailSendSuppressionService $mailSendSuppressionService;
    private PickwarePosAclRoleFactory $pickwarePosAclRoleFactory;

    private function __construct()
    {
        // Create an instance with ::initFromContainer()
    }

    public static function initFromContainer(ContainerInterface $container): self
    {
        $self = new self();
        $self->systemConfigService = $container->get(SystemConfigService::class);
        /** @var Connection $db */
        $db = $container->get(Connection::class);
        $defaultTranslationProvider = new DefaultTranslationProvider($container, $db);
        $self->entityManager = new EntityManager($container, $db, $defaultTranslationProvider, new EntityDefinitionQueryHelper());
        $self->documentTypeInstaller = new DocumentTypeInstaller($self->entityManager);
        $self->entityIdResolver = new EntityIdResolver($db);
        $self->pickwarePosSalesChannelService = new PickwarePosSalesChannelService(
            $self->entityManager,
            $self->entityIdResolver,
        );
        $self->mailTemplateInstaller = new MailTemplateInstaller($self->entityManager);
        $self->mailTemplateUninstaller = new MailTemplateUninstaller($self->entityManager);
        $self->aclRoleInstaller = new AclRoleInstaller($self->entityManager);
        $self->aclRoleUninstaller = new AclRoleUninstaller($self->entityManager);
        $self->mailSendSuppressionService = new MailSendSuppressionService($self->systemConfigService);
        $self->pickwarePosAclRoleFactory = new PickwarePosAclRoleFactory(
            new PickwarePosFeaturePermissionsProvider(),
            new AclRoleFactory(),
        );

        // The service `NumberRangeValueGeneratorInterface` is not public in the DIC so we need to "steal" it via
        // reflection from the corresponding controller, which is public.
        // A PR exists to make the service public: https://github.com/shopware/shopware/pull/2080
        // If you stumble across this you can check the status of the PR and maybe remove the ugly usage of reflection
        // here.
        $numberRangeGeneratorController = $container->get(NumberRangeController::class);
        $controllerReflection = new ReflectionClass(NumberRangeController::class);
        $valueGeneratorProperty = $controllerReflection->getProperty('valueGenerator');
        $valueGeneratorProperty->setAccessible(true);
        $self->numberRangeValueGenerator = $valueGeneratorProperty->getValue($numberRangeGeneratorController);

        return $self;
    }

    public function install(InstallContext $installContext): void
    {
        $this->update($installContext);

        $installContext->getContext()->scope(Context::SYSTEM_SCOPE, function(Context $context): void {
            (new CreateDeliveryTimeInstallationStep($this->entityManager))->install($context);
            (new EnsureDefaultRuleInstallationStep($this->entityManager))->install($context);
            (new CreatePaymentMethodsInstallationStep($this->entityManager))->install($context);
            (new CreateShippingMethodsInstallationStep($this->entityManager, $this->entityIdResolver))->install(
                $context,
            );
            (new CreatePosCustomerGroupInstallationStep($this->entityManager))->install($context);
            (new CreatePosCustomerGroupFallbackInstallationStep($this->entityManager))->install(
                $context,
            );
            (new CreatePosSalesChannelInstallationStep(
                $this->pickwarePosSalesChannelService,
                $this->entityManager,
            ))->install($context);
            (new CreatePosCustomerInstallationStep(
                $this->entityManager,
                $this->entityIdResolver,
                $this->numberRangeValueGenerator,
                $this->mailSendSuppressionService,
            ))->install($context);
            (new CreateDefaultConfigInstallationStep($this->systemConfigService))->install();
        });
    }

    public function update(InstallContext $installContext): void
    {
        $installContext->getContext()->scope(Context::SYSTEM_SCOPE, function(Context $context): void {
            (new EnsureSalesChannelTypeInstallationStep($this->entityManager))->install($context);
            $this->mailTemplateInstaller->installMailTemplate(new CouponReceiptMailTemplate(), $context);
            $this->mailTemplateInstaller->installMailTemplate(new ReceiptMailTemplate(), $context);
            $this->mailTemplateInstaller->installMailTemplate(new ReturnOrderReceiptMailTemplate(), $context);
            $this->documentTypeInstaller->installDocumentType(new CouponReceiptDocumentType(), $context);
            $this->documentTypeInstaller->installDocumentType(new ReceiptDocumentType(), $context);
            $this->documentTypeInstaller->installDocumentType(new ReturnOrderReceiptDocumentType(), $context);
            $this->aclRoleInstaller->installAclRole(
                $this->pickwarePosAclRoleFactory->createPickwarePosAclRole(),
                $context,
            );
        });
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        $uninstallContext->getContext()->scope(Context::SYSTEM_SCOPE, function(Context $context): void {
            $this->mailTemplateUninstaller->uninstallMailTemplate(
                new ReceiptMailTemplate(),
                $context,
            );
            $this->aclRoleUninstaller->uninstallAclRole(
                $this->pickwarePosAclRoleFactory->createPickwarePosAclRole(),
                $context,
            );
        });
    }
}
