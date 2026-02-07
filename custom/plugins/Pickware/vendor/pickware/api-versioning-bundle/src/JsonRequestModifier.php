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

use JsonException;
use Pickware\PhpStandardLibrary\Json\Json;
use ReflectionClass;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

class JsonRequestModifier
{
    /**
     * Allows modifying the json content of a request by passing it to a callable function.
     * The modified content is then written back to the request using reflection.
     * Note: Both the raw content and the parameter bag are overwritten.
     * If the content type is not 'application/json' or cannot be decoded, it does nothing.
     *
     * @param callable(int|float|bool|array|stdClass):void $modifyContent A callback that should modify the JSON by
     *     reference
     */
    public static function modifyJsonContent(Request $request, callable $modifyContent, bool $asObject = false): void
    {
        if ($asObject === false) {
            trigger_error('Calling this method with `$asObject===false` is deprecated. This argument will be' .
            ' removed and the default behaviour will become `$asObject===true`', E_USER_DEPRECATED);
        }
        if (($request->headers->get('Content-Type') !== 'application/json')) {
            return;
        }

        // If the content cannot be decoded, we want the client to receive the unmodified content as it might contain an
        // expected error. Throwing an error here would obfuscate the original content.
        try {
            if ($asObject) {
                $content = Json::decodeToObject($request->getContent());
            } else {
                $content = Json::decodeToArray($request->getContent());
            }
        } catch (JsonException $exception) {
            return;
        }

        $modifyContent($content);

        $encodedContent = Json::stringify($content);
        // The request->request expects an array type. But we can't deserialize the json as associative array because
        // that would lose the information of object types because they are decoded to associative arrays. This breaks
        // our json validation. That's why we have to convert the $content of type stdClass to an associative array type
        // here by encoding and decoding it accordingly.
        $request->request->replace($asObject ? Json::decodeToArray($encodedContent) : $content);
        $responseReflection = new ReflectionClass($request);
        $contentProperty = $responseReflection->getProperty('content');
        $contentProperty->setAccessible(true);
        $contentProperty->setValue($request, $encodedContent);
    }
}
