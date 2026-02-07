<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Installation;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\DefaultTranslationProvider;
use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\Config\DatevCustomerGroupInformationCustomFieldSet;
use Pickware\DatevBundle\Config\DatevCustomerInformationCustomFieldSet;
use Pickware\DatevBundle\Config\DatevProductInformationCustomFieldSet;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchExporter;
use Pickware\DatevBundle\Installation\Steps\AddPaymentCaptureDefaultConfig;
use Pickware\DatevBundle\Installation\Steps\CreateConfigInstallationStep;
use Pickware\InstallationLibrary\CustomFieldSet\CustomFieldSetInstaller;
use Pickware\PickwareErpStarter\Installation\Installer\ImportExportProfileInstaller;
use Pickware\PickwareErpStarter\Installation\Steps\UpsertImportExportProfilesInstallationStep;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PickwareDatevBundleInstaller
{
    private function __construct(
        private readonly Connection $db,
        private readonly CustomFieldSetInstaller $customFieldSetInstaller,
    ) {
        // Create an instance with ::initFromContainer()
    }

    public static function initFromContainer(ContainerInterface $container): self
    {
        $connection = $container->get(Connection::class);
        $defaultTranslationProvider = new DefaultTranslationProvider($container, $connection);
        $entityManager = new EntityManager($container, $connection, $defaultTranslationProvider, new EntityDefinitionQueryHelper());

        return new self(
            $connection,
            new CustomFieldSetInstaller($entityManager),
        );
    }

    public function install(InstallContext $installContext): void
    {
        (new CreateConfigInstallationStep($this->db))->install();
        (new AddPaymentCaptureDefaultConfig($this->db))->install();
        if (class_exists(ImportExportProfileInstaller::class)) {
            (new ImportExportProfileInstaller($this->db))
                ->ensureImportExportProfile(EntryBatchExporter::TECHNICAL_NAME, logRetentionDays: 365 * 2);
        } else {
            (new UpsertImportExportProfilesInstallationStep($this->db, [
                EntryBatchExporter::TECHNICAL_NAME,
            ]))->install();
        }
        $this->customFieldSetInstaller->installCustomFieldSet(
            new DatevCustomerInformationCustomFieldSet(),
            $installContext->getContext(),
        );
        $this->customFieldSetInstaller->installCustomFieldSet(
            new DatevCustomerGroupInformationCustomFieldSet(),
            $installContext->getContext(),
        );
        $this->customFieldSetInstaller->installCustomFieldSet(
            new DatevProductInformationCustomFieldSet(),
            $installContext->getContext(),
        );
    }

    public function uninstall(): void {}
}
