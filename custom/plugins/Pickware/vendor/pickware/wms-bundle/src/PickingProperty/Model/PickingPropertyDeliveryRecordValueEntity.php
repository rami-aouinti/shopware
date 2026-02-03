<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProperty\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PickingPropertyDeliveryRecordValueEntity extends Entity
{
    use EntityIdTrait;

    protected string $pickingPropertyDeliveryRecordId;
    protected ?PickingPropertyDeliveryRecordEntity $pickingPropertyDeliveryRecord = null;
    protected string $name;
    protected string $value;

    public function getPickingPropertyDeliveryRecordId(): string
    {
        return $this->pickingPropertyDeliveryRecordId;
    }

    public function setPickingPropertyDeliveryRecordId(string $pickingPropertyDeliveryRecordId): void
    {
        if ($this->pickingPropertyDeliveryRecord?->getId() !== $pickingPropertyDeliveryRecordId) {
            $this->pickingPropertyDeliveryRecord = null;
        }

        $this->pickingPropertyDeliveryRecordId = $pickingPropertyDeliveryRecordId;
    }

    public function getPickingPropertyDeliveryRecord(): PickingPropertyDeliveryRecordEntity
    {
        if (!$this->pickingPropertyDeliveryRecord) {
            throw new AssociationNotLoadedException('pickingPropertyDeliveryRecord', $this);
        }

        return $this->pickingPropertyDeliveryRecord;
    }

    public function setPickingPropertyDeliveryRecord(PickingPropertyDeliveryRecordEntity $pickingPropertyDeliveryRecord): void
    {
        $this->pickingPropertyDeliveryRecord = $pickingPropertyDeliveryRecord;
        $this->pickingPropertyDeliveryRecordId = $pickingPropertyDeliveryRecord->getId();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}
