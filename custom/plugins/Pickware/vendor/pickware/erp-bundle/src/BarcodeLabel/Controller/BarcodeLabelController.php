<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\BarcodeLabel\Controller;

use Pickware\PickwareErpStarter\BarcodeLabel\BarcodeLabelConfiguration;
use Pickware\PickwareErpStarter\BarcodeLabel\BarcodeLabelService;
use Pickware\PickwareErpStarter\BarcodeLabel\DataProvider\ProductDataProviderException;
use Pickware\ShopwareExtensionsBundle\GeneratedDocument\GeneratedDocumentExtension as DocumentBundleResponseFactory;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class BarcodeLabelController
{
    public function __construct(private readonly BarcodeLabelService $barcodeLabelService) {}

    #[JsonValidation(schemaFilePath: 'payload-create-barcode-labels.schema.json')]
    #[Route(path: '/api/_action/pickware-erp/barcode-label/create-barcode-labels', methods: ['POST'])]
    public function createBarcodeLabels(Request $request, Context $context): Response
    {
        $labelConfiguration = BarcodeLabelConfiguration::fromArray($request->request->all());

        try {
            $renderedDocument = $this->barcodeLabelService->createBarcodeLabels($labelConfiguration, $context);
        } catch (ProductDataProviderException $exception) {
            return $exception
                ->serializeToJsonApiError()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return DocumentBundleResponseFactory::createPdfResponse(
            $renderedDocument->getContent(),
            $renderedDocument->getName(),
            $renderedDocument->getContentType(),
        );
    }
}
