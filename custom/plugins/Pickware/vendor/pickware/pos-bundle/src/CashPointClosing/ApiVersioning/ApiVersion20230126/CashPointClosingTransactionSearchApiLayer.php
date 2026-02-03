<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing\ApiVersioning\ApiVersion20230126;

use JsonException;
use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\Attributes\EntityApiLayer;
use Pickware\ApiVersioningBundle\JsonApiResponseModifier;
use Pickware\ApiVersioningBundle\JsonRequestModifier;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwarePos\ApiVersion\ApiVersion20230126;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionDefinition;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[EntityApiLayer(entity: CashPointClosingTransactionDefinition::ENTITY_NAME, method: 'search')]
class CashPointClosingTransactionSearchApiLayer implements ApiLayer
{
    use CashPointClosingTransactionLineItemModifying;

    public function getVersion(): ApiVersion
    {
        return new ApiVersion20230126();
    }

    public function transformRequest(Request $request, Context $context): void
    {
        JsonRequestModifier::modifyJsonContent(
            $request,
            fn(&$jsonContent) => $this->includeVatTableInRequest($jsonContent),
            asObject: true,
        );
    }

    public function transformResponse(Request $request, Response $response, Context $context): void
    {
        if (!($response instanceof JsonResponse)) {
            return;
        }

        if ($response->headers->get('Content-Type') === 'application/vnd.api+json') {
            JsonApiResponseModifier::modifyJsonApiContentForType(
                $response,
                'pickware_pos_cash_point_closing_transaction_line_item',
                fn(&$jsonContent) => $this->removeVatTable($jsonContent['attributes']),
            );
        } else {
            try {
                $content = Json::decodeToArray($response->getContent());
            } catch (JsonException $exception) {
                return;
            }

            foreach ($content['data'] as &$transaction) {
                foreach ($transaction['cashPointClosingTransactionLineItems'] as &$lineItem) {
                    $this->removeVatTable($lineItem);
                }
                unset($lineItem);
            }
            unset($transaction);
            $response->setContent(Json::stringify($content));
        }
    }
}
