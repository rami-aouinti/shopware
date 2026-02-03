<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment;

use JsonSerializable;

class ShipmentBlueprintCreationConfiguration implements JsonSerializable
{
    private ?bool $skipParcelRepacking;
    private ?string $shippingMethodId;

    public function jsonSerialize(): array
    {
        return [
            'skipParcelRepacking' => $this->skipParcelRepacking,
            'shippingMethodId' => $this->shippingMethodId,
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self();
        $self->skipParcelRepacking = isset($array['skipParcelRepacking']) && $array['skipParcelRepacking'];
        $self->shippingMethodId = isset($array['shippingMethodId']) ? $array['shippingMethodId'] : null;

        return $self;
    }

    public static function makeDefault(): self
    {
        $self = new self();
        $self->skipParcelRepacking = false;
        $self->shippingMethodId = null;

        return $self;
    }

    public function getSkipParcelRepacking(): ?bool
    {
        return $this->skipParcelRepacking;
    }

    public function setSkipParcelRepacking(?bool $skipParcelRepacking): void
    {
        $this->skipParcelRepacking = $skipParcelRepacking;
    }

    public function getShippingMethodId(): ?string
    {
        return $this->shippingMethodId;
    }

    public function setShippingMethodId(?string $shippingMethodId): void
    {
        $this->shippingMethodId = $shippingMethodId;
    }
}
