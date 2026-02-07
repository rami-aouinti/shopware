<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocumentPicture\Export;

use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\AccountingDocumentPicture\Guid\Model\ImportExportAccountingDocumentGuidMappingDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AccountingDocumentPackagePayloadService
{
    private const FILE_NAME_PATTERN = 'Document_Package_V0600_%s_%s';

    public function __construct(
        private readonly EntityManager $entityManager,
        #[Autowire(param: 'pickware_datev.accounting_document_picture_export.maximum_documents_per_package')]
        private readonly int $maximumDocumentsPerPackage,
    ) {}

    public function getAccountingDocumentPackageBaseFilename(string $documentExportId, Context $context): string
    {
        /** @var ImportExportEntity $documentExport */
        $documentExport = $this->entityManager->getByPrimaryKey(
            ImportExportDefinition::class,
            $documentExportId,
            $context,
        );
        $salesChannelId = $documentExport->getConfig()['sales-channel-id'];
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->entityManager->getByPrimaryKey(
            SalesChannelDefinition::class,
            $salesChannelId,
            $context,
        );

        $sanitizedSalesChannelName = preg_replace(
            '/[^\\w-]/',
            '_',
            str_replace(' ', '-', mb_strtolower($salesChannel->getName())),
        );

        return sprintf(
            self::FILE_NAME_PATTERN,
            $documentExport->getCreatedAt()->format('Y-m-d-H_i_sP'),
            $sanitizedSalesChannelName,
        );
    }

    /**
     * @return array<AccountingDocumentPackagePayload>
     */
    public function calculateDocumentPackagePayloads(
        string $documentExportId,
        Context $context,
    ): array {
        $accountingDocumentGuidCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('importExportId', $documentExportId))
            ->addFilter(new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsFilter(
                        'accountingDocumentGuid.document.documentType.technicalName',
                        ZugferdRenderer::TYPE,
                    ),
                ],
            ))
            ->addAggregation(
                new CountAggregation('accounting-document-count', 'accountingDocumentGuidId'),
            );
        $totalAccountingDocuments = $this->entityManager
            ->getRepository(
                ImportExportAccountingDocumentGuidMappingDefinition::class,
            )->aggregate(
                $accountingDocumentGuidCriteria,
                $context,
            )->get('accounting-document-count')->getCount();

        $baseFilename = $this->getAccountingDocumentPackageBaseFilename($documentExportId, $context);

        if ($totalAccountingDocuments < $this->maximumDocumentsPerPackage) {
            return [
                new AccountingDocumentPackagePayload(
                    documentExportId: $documentExportId,
                    fileName: $baseFilename . '.zip',
                    limit: $totalAccountingDocuments,
                    offset: 0,
                ),
            ];
        }

        $accountingDocumentPackageConfigurations = [];
        for ($packageNumber = 1; ($packageNumber - 1) < ($totalAccountingDocuments / $this->maximumDocumentsPerPackage); $packageNumber++) {
            $documentPackageName = sprintf('%s_part%02d.zip', $baseFilename, $packageNumber);
            $offset = ($packageNumber - 1) * $this->maximumDocumentsPerPackage;
            $limit = min($totalAccountingDocuments - $offset, $this->maximumDocumentsPerPackage);
            $accountingDocumentPackageConfigurations[] = new AccountingDocumentPackagePayload(
                documentExportId: $documentExportId,
                fileName: $documentPackageName,
                limit: $limit,
                offset: $offset,
            );
        }

        return $accountingDocumentPackageConfigurations;
    }
}
