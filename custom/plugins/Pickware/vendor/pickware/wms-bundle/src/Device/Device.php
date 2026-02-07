<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Device;

use RuntimeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\Request;

#[Exclude]
class Device
{
    private const DEVICE_ID_HEADER_KEY = 'pickware-device-uuid';
    private const DEVICE_NAME_HEADER_KEY = 'pickware-device-name';
    private const PICKWARE_DEVICE_EXTENSION_KEY = 'pickware_device';

    public function __construct(
        private readonly string $id,
        private readonly string $name,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array{id: string, name: string}
     */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }

    public function addToContext(Context $context): void
    {
        $context->addExtension(self::PICKWARE_DEVICE_EXTENSION_KEY, new ArrayStruct($this->toPayload()));
    }

    public static function getFromContext(Context $context): self
    {
        /** @var ArrayStruct $deviceStruct */
        $deviceStruct = $context->getExtension(self::PICKWARE_DEVICE_EXTENSION_KEY);
        if ($deviceStruct === null) {
            throw new RuntimeException('The current context does not have a Pickware device.');
        }

        return new self(id: $deviceStruct->get('id'), name: $deviceStruct->get('name'));
    }

    public static function tryGetFromContext(Context $context): ?self
    {
        /** @var ArrayStruct $deviceStruct */
        $deviceStruct = $context->getExtension(self::PICKWARE_DEVICE_EXTENSION_KEY);
        if ($deviceStruct === null) {
            return null;
        }

        return self::getFromContext($context);
    }

    public static function fromRequest(Request $request): ?self
    {
        $deviceId = $request->headers->get(self::DEVICE_ID_HEADER_KEY);
        $deviceName = $request->headers->get(self::DEVICE_NAME_HEADER_KEY);

        if ($deviceId === null || $deviceName === null) {
            return null;
        }

        // Convert the deviceId to the Shopware ID format.
        $deviceId = mb_strtolower(str_replace('-', '', $deviceId));

        // The device name is encoded by the app to be URL-safe. We need to decode it to get the original name.
        $deviceName = urldecode($deviceName);

        // Convert the device name to valid UTF-8 encoding to work around an encoding issue present in some versions
        // of iOS 15.
        $deviceName = mb_convert_encoding($deviceName, 'UTF-8');

        return new self(id: $deviceId, name: $deviceName);
    }
}
