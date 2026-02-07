<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing\Controller;

use DateTimeImmutable;
use Pickware\ApiVersioningBundle\Attributes\ApiLayer;
use Pickware\HttpUtils\ResponseFactory;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwarePos\CashPointClosing\ApiVersioning\ApiVersion20230126\CashPointClosingTransactionSaveApiLayer as ApiVersion20230126CashPointClosingTransactionSaveApiLayer;
use Pickware\PickwarePos\CashPointClosing\ApiVersioning\ApiVersion20230308\CashPointClosingTransactionSaveApiLayer as ApiVersion20230308CashPointClosingTransactionSaveApiLayer;
use Pickware\PickwarePos\CashPointClosing\CashPointClosingException;
use Pickware\PickwarePos\CashPointClosing\CashPointClosingTransactionService;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class CashPointClosingTransactionController
{
    private const CASH_POINT_CLOSING_TRANSACTION_JSON_SCHEMA_FILE = '../cash-point-closing-transaction.schema.json';

    public function __construct(
        private readonly CashPointClosingTransactionService $cashPointClosingTransactionService,
    ) {}

    #[ApiLayer(ids: [
        ApiVersion20230126CashPointClosingTransactionSaveApiLayer::class,
        ApiVersion20230308CashPointClosingTransactionSaveApiLayer::class,
    ])]
    #[Route(path: '/api/_action/pickware-pos/cash-point-closing-transaction/save', methods: ['POST'])]
    #[JsonValidation(schemaFilePath: self::CASH_POINT_CLOSING_TRANSACTION_JSON_SCHEMA_FILE)]
    public function saveCashPointClosingTransaction(Request $request, Context $context): Response
    {
        /** @var AdminApiSource $contextSource */
        $contextSource = $context->getSource();
        if (!($contextSource instanceof AdminApiSource)) {
            return ResponseFactory::createUnsupportedContextSourceResponse(
                get_class($context->getSource()),
                [AdminApiSource::class],
            );
        }
        if ($request->getContentTypeFormat() !== 'json') {
            return ResponseFactory::createInvalidPosContentType('json');
        }
        $payloadJson = $request->getContent();
        if ($payloadJson === '') {
            return ResponseFactory::createEmptyPostContentResponse();
        }

        // The initial app version did not have an API version. The first API version is 2021-11-17. When the header is
        // missing we assume that this initial version of the app sent the request.
        $apiVersionString = $request->headers->get('X-Pickware-Api-Version', '2020-01-01');
        $apiVersion = new DateTimeImmutable($apiVersionString);
        if ((new DateTimeImmutable('2021-11-17'))->diff($apiVersion)->format('%R') === '-') {
            $this->migrateRequestJsonTo20211117($payloadJson);
        }

        try {
            $this->cashPointClosingTransactionService->saveCashPointClosingTransaction(
                Json::decodeToArray($payloadJson),
                $contextSource->getUserId(),
                $context,
            );
        } catch (CashPointClosingException $exception) {
            return $exception->serializeToJsonApiError()->setStatus(Response::HTTP_BAD_REQUEST)->toJsonApiErrorResponse();
        }

        return new JsonResponse();
    }

    private function migrateRequestJsonTo20211117(string &$payloadJson): void
    {
        $decoded = Json::decodeToObject($payloadJson);
        if (isset($decoded->total->inclVat) && isset($decoded->payment->amount)) {
            // The app accidentally wrote the physically given amount to payment.amount (e.g. when the customer paid an
            // amount of 15.22 with a 50 Euro note, payment.amount contained 50.0 instead of 15.22). It should be the
            // total value instead. total.inclVat was set correctly, so we can reclaim the that value.
            $decoded->payment->amount = $decoded->total->inclVat;
        }
        if (isset($decoded->total->exclVat) && isset($decoded->total->inclVat)) {
            // The app accidentally wrote the average tax rate in percent to total.vat (e.g. 19.00). It should be the
            // total paid tax amount. We can reclaim the correct value by calculating the difference of inclVat and
            // exclVat. Round by 10 decimals to avoid float value precision differences.
            $decoded->total->vat = round($decoded->total->inclVat - $decoded->total->exclVat, 10);
        }
        if (
            isset($decoded->cashPointClosingTransactionLineItems)
            && is_array($decoded->cashPointClosingTransactionLineItems)
        ) {
            foreach ($decoded->cashPointClosingTransactionLineItems as &$lineItem) {
                if (isset($lineItem->pricePerUnit->inclVat) && isset($lineItem->pricePerUnit->exclVat)) {
                    // The app accidentally wrote the average tax rate in percent to pricePerUnit.vat (e.g. 19.00). It
                    // should be the total paid tax amount. We can reclaim the correct value by calculating the
                    // difference of inclVat and exclVat. Round by 10 decimals to avoid float value precision
                    // differences.
                    $lineItem->pricePerUnit->vat = round(
                        $lineItem->pricePerUnit->inclVat - $lineItem->pricePerUnit->exclVat,
                        10,
                    );
                }
                if (isset($lineItem->total->inclVat) && isset($lineItem->total->exclVat)) {
                    // The app accidentally wrote the average tax rate in percent to total.vat (e.g. 19.00). It should
                    // be the total paid tax amount. We can reclaim the correct value by calculating the difference of
                    // inclVat and exclVat. Round by 10 decimals to avoid float value precision differences.
                    $lineItem->total->vat = round($lineItem->total->inclVat - $lineItem->total->exclVat, 10);
                }
            }
            unset($lineItem);
        }
        $payloadJson = Json::stringify($decoded);
    }
}
