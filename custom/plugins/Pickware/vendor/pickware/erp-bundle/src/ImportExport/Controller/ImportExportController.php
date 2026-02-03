<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\Controller;

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\ResponseFactory;
use Pickware\PhpStandardLibrary\Json\Json;
use function Pickware\PhpStandardLibrary\Optional\doIf;
use Pickware\PickwareErpStarter\ImportExport\DependencyInjection\ExporterRegistry;
use Pickware\PickwareErpStarter\ImportExport\Exception\ExporterServiceDoesNotExistException;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImporterServiceDoesNotExistException;
use Pickware\PickwareErpStarter\ImportExport\ImportExportService;
use Pickware\PickwareErpStarter\ImportExport\MemoryUtilsService;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\CsvToDatabaseReader;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\DatabaseToCsvWriter;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\FileReader;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class ImportExportController
{
    private string $requiredMemory;
    private MemoryUtilsService $memoryUtils;
    private ImportExportService $importExportService;
    private RequestCriteriaBuilder $requestCriteriaBuilder;
    private ExporterRegistry $exporterRegistry;
    private EntityManager $entityManager;

    public function __construct(
        #[Autowire('%pickware_erp.import_export.min_memory_requirement%')]
        string $requiredMemory,
        MemoryUtilsService $memoryUtils,
        ImportExportService $importExportService,
        RequestCriteriaBuilder $requestCriteriaBuilder,
        ExporterRegistry $exporterRegistry,
        EntityManager $entityManager,
    ) {
        $this->requiredMemory = $requiredMemory;
        $this->memoryUtils = $memoryUtils;
        $this->importExportService = $importExportService;
        $this->requestCriteriaBuilder = $requestCriteriaBuilder;
        $this->exporterRegistry = $exporterRegistry;
        $this->entityManager = $entityManager;
    }

    #[Route(path: '/api/_action/pickware-erp/memory-configuration', methods: ['GET'])]
    public function getMemoryConfiguration(): JsonResponse
    {
        $expectedMemoryBytes = $this->memoryUtils->parseMemoryString($this->requiredMemory);
        $currentMemoryBytes = $this->memoryUtils->parseMemoryString($this->memoryUtils->getMemoryLimit());

        return new JsonResponse([
            'requiredMemoryInBytes' => $expectedMemoryBytes,
            'actualMemoryInBytes' => $currentMemoryBytes,
            'hasEnoughMemory' => $this->memoryUtils->hasEnoughMemory(
                $expectedMemoryBytes,
                $currentMemoryBytes,
            ),
        ]);
    }

    #[Route(path: '/api/_action/pickware-erp/import-csv', methods: ['POST'])]
    public function importCsv(Request $request, Context $context): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('file');
        if (!$file) {
            return ResponseFactory::createParameterMissingResponse('file');
        }
        if (!$file->isValid()) {
            return ResponseFactory::createFileUploadErrorResponse($file, 'file');
        }
        $profileTechnicalName = $request->request->get('profileTechnicalName');
        if (!$profileTechnicalName) {
            return ResponseFactory::createParameterMissingResponse('profileTechnicalName');
        }
        $configRaw = $request->request->get('config');
        $config = doIf($configRaw, Json::decodeToArray(...));

        $source = $context->getSource();
        $userId = ($source instanceof AdminApiSource) ? $source->getUserId() : null;

        try {
            $importExportId = $this->importExportService->importAsync($file, [
                'profileTechnicalName' => $profileTechnicalName,
                'readerTechnicalName' => CsvToDatabaseReader::TECHNICAL_NAME,
                'mimeType' => FileReader::MIMETYPE_CSV,
                'userId' => $userId,
                'config' => $config,
                'fileName' => $file->getClientOriginalName(),
                'userComment' => $request->request->get('userComment', null),
            ], $context);
        } catch (ImporterServiceDoesNotExistException $e) {
            return ResponseFactory::createParameterInvalidValueResponse('profileTechnicalName', $e);
        }

        return new JsonResponse(['importExportId' => $importExportId], Response::HTTP_ACCEPTED);
    }

    #[Route(path: '/api/_action/pickware-erp/export-csv', methods: ['POST'])]
    public function exportCsv(Request $request, Context $context): JsonResponse
    {
        $profileTechnicalName = $request->request->get('profileTechnicalName');
        if (!$profileTechnicalName) {
            return ResponseFactory::createParameterMissingResponse('profileTechnicalName');
        }

        if (!$this->exporterRegistry->hasExporter($profileTechnicalName)) {
            return ResponseFactory::createParameterInvalidValueResponse(
                'profileTechnicalName',
                new ExporterServiceDoesNotExistException($profileTechnicalName),
            );
        }

        $exporter = $this->exporterRegistry->getExporterByTechnicalName($profileTechnicalName);

        $criteria = $this->requestCriteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->entityManager->getEntityDefinition($exporter->getEntityDefinitionClassName()),
            $context,
        );

        $config = array_merge([
            'locale' => $request->headers->get('sw-admin-locale', 'en-GB'),
            'criteria' => $this->requestCriteriaBuilder->toArray($criteria),
            'totalCount' => $request->request->get('totalCount', 0),
        ], $request->request->all('config'));

        $source = $context->getSource();
        $userId = ($source instanceof AdminApiSource) ? $source->getUserId() : null;

        $importExportId = $this->importExportService->exportAsync([
            'profileTechnicalName' => $profileTechnicalName,
            'writerTechnicalName' => DatabaseToCsvWriter::TECHNICAL_NAME,
            'config' => $config,
            'userId' => $userId,
            'userComment' => $request->request->get('userComment', null),
        ], $context);

        return new JsonResponse(['importExportId' => $importExportId], Response::HTTP_ACCEPTED);
    }
}
