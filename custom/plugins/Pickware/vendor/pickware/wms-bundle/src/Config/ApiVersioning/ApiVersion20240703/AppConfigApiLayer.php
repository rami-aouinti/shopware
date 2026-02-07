<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Config\ApiVersioning\ApiVersion20240703;

use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareWms\ApiVersion\ApiVersion20240703;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AppConfigApiLayer implements ApiLayer
{
    public function __construct(private readonly EntityManager $entityManager) {}

    public function getVersion(): ApiVersion
    {
        return new ApiVersion20240703();
    }

    public function transformRequest(Request $request, Context $context): void {}

    public function transformResponse(Request $request, Response $response, Context $context): void
    {
        if (!($response instanceof JsonResponse)) {
            return;
        }

        // If the content cannot be decoded, we want the client to receive the unmodified content as it might
        // contain an expected error. Throwing an error here would obfuscate the original content.
        try {
            $content = Json::decodeToArray($response->getContent());
        } catch (JsonException $exception) {
            return;
        }

        if (!isset($content['documentPrintingConfiguration'])) {
            return;
        }

        $documentPrintingConfigsByShippingMethodId = [];
        foreach ($content['documentPrintingConfiguration'] as $documentPrintingConfig) {
            $documentPrintingConfigsByShippingMethodId[$documentPrintingConfig['shippingMethodId']][] = $documentPrintingConfig;
        }

        /** @var DocumentTypeEntity $invoiceDocumentType */
        $invoiceDocumentType = $this->entityManager->getOneBy(
            DocumentTypeDefinition::class,
            ['technicalName' => InvoiceRenderer::TYPE],
            $context,
        );
        /** @var DocumentTypeEntity $deliveryNoteDocumentType */
        $deliveryNoteDocumentType = $this->entityManager->getOneBy(
            DocumentTypeDefinition::class,
            ['technicalName' => DeliveryNoteRenderer::TYPE],
            $context,
        );
        $legacyPrintingConfigs = [];
        foreach ($documentPrintingConfigsByShippingMethodId as $shippingMethodId => $documentPrintingConfigs) {
            $invoiceConfig = null;
            $deliveryNoteConfig = null;
            foreach ($documentPrintingConfigs as $documentPrintingConfig) {
                if ($documentPrintingConfig['documentTypeId'] === $invoiceDocumentType->getId()) {
                    $invoiceConfig = $documentPrintingConfig;
                }
                if ($documentPrintingConfig['documentTypeId'] === $deliveryNoteDocumentType->getId()) {
                    $deliveryNoteConfig = $documentPrintingConfig;
                }
                if ($invoiceConfig !== null && $deliveryNoteConfig !== null) {
                    break;
                }
            }
            $legacyPrintingConfigs[] = [
                'shippingMethodId' => $shippingMethodId,
                'copiesOfInvoices' => $invoiceConfig['copies'] ?? 0,
                'copiesOfDeliveryNotes' => $deliveryNoteConfig['copies'] ?? 0,
            ];
        }

        $content['documentPrintingConfiguration'] = $legacyPrintingConfigs;

        $response->setContent(Json::stringify($content));
    }
}
