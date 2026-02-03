<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PickingProperty\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PickingPropertyOrderRecordValueEntity extends Entity
{
    use EntityIdTrait;

    protected string $pickingPropertyOrderRecordId;
    protected ?PickingPropertyOrderRecordEntity $pickingPropertyOrderRecord = null;
    protected string $name;
    protected string $value;

    public function getPickingPropertyOrderRecordId(): string
    {
        return $this->pickingPropertyOrderRecordId;
    }

    public function setPickingPropertyOrderRecordId(string $pickingPropertyOrderRecordId): void
    {
        if ($this->pickingPropertyOrderRecord?->getId() !== $pickingPropertyOrderRecordId) {
            $this->pickingPropertyOrderRecord = null;
        }

        $this->pickingPropertyOrderRecordId = $pickingPropertyOrderRecordId;
    }

    public function getPickingPropertyOrderRecord(): PickingPropertyOrderRecordEntity
    {
        if (!$this->pickingPropertyOrderRecord) {
            throw new AssociationNotLoadedException('pickingPropertyOrderRecord', $this);
        }

        return $this->pickingPropertyOrderRecord;
    }

    public function setPickingPropertyOrderRecord(PickingPropertyOrderRecordEntity $pickingPropertyOrderRecord): void
    {
        $this->pickingPropertyOrderRecord = $pickingPropertyOrderRecord;
        $this->pickingPropertyOrderRecordId = $pickingPropertyOrderRecord->getId();
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
