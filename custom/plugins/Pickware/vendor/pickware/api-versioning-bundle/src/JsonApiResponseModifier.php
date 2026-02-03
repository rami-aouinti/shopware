<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiVersioningBundle;

use Pickware\PhpStandardLibrary\Json\Json;
use Symfony\Component\HttpFoundation\Response;

class JsonApiResponseModifier
{
    /**
     * Allows modifying the json api content of a response by passing all objects of a given type to a callable function.
     * The modified content is then written back to the response.
     * If the content type is not 'application/vnd.api+json' or cannot be decoded, it does nothing.
     *
     * @param Response $response The response to be modified
     * @param string $type The type of entity to be modified
     * @param callable $modifyContent The changes to be applied. Should have one argument of type array, which is passed by reference. I.e. `function (array &$jsonContent): void`
     */
    public static function modifyJsonApiContentForType(Response $response, string $type, callable $modifyContent): void
    {
        $content = JsonApiResponseProcessor::parseContent($response);
        if ($content === false) {
            return;
        }

        $elements = JsonApiResponseProcessor::getElements($content, $type);

        foreach ($elements as &$element) {
            $modifyContent($element);
        }
        unset($element);

        $response->setContent(Json::stringify($content));
    }

    /**
     * Allows modifying the json api content of a response by passing all objects of given types to a callable functions.
     * If the content type is not 'application/vnd.api+json' or cannot be decoded, it does nothing.
     *
     * @param mixed $content The content to be modified
     * @param array $typeModifiers An associative array of types and the respective callbacks to modify the content. I.e. `['type1' => function (array &$jsonContent): void, 'type2' => function (array &$jsonContent): void]`
     */
    public static function modifyJsonApiContentForTypes(mixed &$content, array $typeModifiers): void
    {
        foreach ($typeModifiers as $type => $modifyContent) {
            $elements = JsonApiResponseProcessor::getElements($content, $type);

            foreach ($elements as &$element) {
                $modifyContent($element);
            }
            unset($element);
        }
    }
}
