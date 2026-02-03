<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashRegister\ApiVersioning\ApiVersion20230308;

use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\Attributes\EntityApiLayer;
use Pickware\ApiVersioningBundle\JsonApiResponseModifier;
use Pickware\ApiVersioningBundle\JsonRequestModifier;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwarePos\ApiVersion\ApiVersion20230308;
use Pickware\PickwarePos\CashRegister\Model\CashRegisterDefinition;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[EntityApiLayer(entity: CashRegisterDefinition::ENTITY_NAME, method: 'search')]
class CashRegisterSearchApiLayer implements ApiLayer
{
    use CashRegisterModifying;

    public function getVersion(): ApiVersion
    {
        return new ApiVersion20230308();
    }

    public function transformRequest(Request $request, Context $context): void
    {
        JsonRequestModifier::modifyJsonContent(
            $request,
            function(&$jsonContent): void {
                if (property_exists($jsonContent, 'associations')) {
                    unset($jsonContent->associations->fiskalyConfiguration);
                }
                if (property_exists($jsonContent, 'includes')) {
                    unset($jsonContent->includes->pickware_pos_cash_register_fiskaly_configuration);
                    $jsonContent->includes->pickware_pos_cash_register[] = 'fiscalizationConfiguration';
                    $jsonContent->includes->pickware_pos_cash_register = array_values(
                        array_filter(
                            $jsonContent->includes->pickware_pos_cash_register,
                            fn($element) => $element != 'fiskalyConfiguration',
                        ),
                    );
                }
            },
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
                'pickware_pos_cash_register',
                fn(&$jsonContent) => $this->convertToFiskalyDeResponse($jsonContent['attributes']),
            );
        } else {
            // If the content cannot be decoded, we want the client to receive the unmodified content as it might
            // contain an expected error. Throwing an error here would obfuscate the original content.
            try {
                $content = Json::decodeToArray($response->getContent());
            } catch (JsonException $exception) {
                return;
            }

            foreach ($content['data'] as &$cashRegister) {
                $this->convertToFiskalyDeResponse($cashRegister);
            }
            unset($cashRegister);
            $response->setContent(Json::stringify($content));
        }
    }
}
