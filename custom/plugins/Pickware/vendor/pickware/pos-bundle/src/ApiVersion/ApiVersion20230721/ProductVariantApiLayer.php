<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\ApiVersion\ApiVersion20230721;

use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\Attributes\EntityApiLayer;
use Pickware\ApiVersioningBundle\JsonApiResponseModifier;
use Pickware\ApiVersioningBundle\JsonRequestModifier;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwarePos\ApiVersion\ApiVersion20230721;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use stdClass;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[EntityApiLayer(entity: OrderDefinition::ENTITY_NAME, method: 'search')]
#[EntityApiLayer(entity: ProductDefinition::ENTITY_NAME, method: 'search')]
class ProductVariantApiLayer implements ApiLayer
{
    public function getVersion(): ApiVersion
    {
        return new ApiVersion20230721();
    }

    public function transformRequest(Request $request, Context $context): void
    {
        if (!self::isUserAgentValid($request)) {
            return;
        }

        JsonRequestModifier::modifyJsonContent(
            $request,
            function(int|float|bool|array|stdClass &$jsonContent): void {
                if (!property_exists($jsonContent, 'includes')) {
                    return;
                }
                if (!property_exists($jsonContent->includes, 'product')) {
                    return;
                }

                $jsonContent->includes->product = array_map(
                    fn($include) => $include == 'mainVariantId' ? 'variantListingConfig' : $include,
                    $jsonContent->includes->product,
                );
            },
            asObject: true,
        );
    }

    public function transformResponse(Request $request, Response $response, Context $context): void
    {
        if (!self::isUserAgentValid($request) || !($response instanceof JsonResponse)) {
            return;
        }

        if ($response->headers->get('Content-Type') === 'application/vnd.api+json') {
            JsonApiResponseModifier::modifyJsonApiContentForType(
                $response,
                'product',
                function(array &$jsonContent): void {
                    $variantListingConfig = $jsonContent['attributes']['variantListingConfig'];
                    unset($jsonContent['attributes']['variantListingConfig']);
                    if ($variantListingConfig === null) {
                        $jsonContent['attributes']['mainVariantId'] = null;

                        return;
                    }
                    $jsonContent['attributes']['mainVariantId'] = $variantListingConfig['mainVariantId'];
                },
            );
        } else {
            // If the content cannot be decoded, we want the client to receive the unmodified content as it might
            // contain an expected error. Throwing an error here would obfuscate the original content.
            try {
                $content = Json::decodeToArray($response->getContent());
            } catch (JsonException $exception) {
                return;
            }

            self::findAndReplaceVariantListingConfig($content);

            $response->setContent(Json::stringify($content));
        }
    }

    private static function findAndReplaceVariantListingConfig(array &$content): void
    {
        foreach ($content as $key => &$value) {
            if (is_array($value)) {
                self::findAndReplaceVariantListingConfig($value);
            }
            if ($key == 'variantListingConfig') {
                unset($content[$key]);

                if ($value === null) {
                    $content['mainVariantId'] = null;

                    return;
                }

                $content['mainVariantId'] = $value['mainVariantId'];
            }
        }
    }

    private static function isUserAgentValid(Request $request): bool
    {
        // Since this API Layer also exists in pickware-wms, we only convert requests sent by the POS app
        // Expected string format: "POS/6.16.3 (com.viison.pickware.POS; build:20230515166; iOS 16.2.0) Alamofire/5.7.1"
        $userAgentString = $request->headers->get('user-agent');
        $pattern = '|^POS/.+\\(com.+pickware\\.POS;|';
        if (!preg_match($pattern, $userAgentString)) {
            return false;
        }

        return true;
    }
}
