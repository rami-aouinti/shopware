<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PaketEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $paketNumber = null;

    protected ?string $status = null;

    protected ?\DateTimeInterface $shippingDate = null;

    protected ?string $lastChangedBy = null;

    protected ?\DateTimeInterface $lastChangedAt = null;

    protected ?PositionCollection $positions = null;

    public function getPaketNumber(): ?string
    {
        return $this->paketNumber;
    }

    public function setPaketNumber(?string $paketNumber): void
    {
        $this->paketNumber = $paketNumber;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getShippingDate(): ?\DateTimeInterface
    {
        return $this->shippingDate;
    }

    public function setShippingDate(?\DateTimeInterface $shippingDate): void
    {
        $this->shippingDate = $shippingDate;
    }

    public function getLastChangedBy(): ?string
    {
        return $this->lastChangedBy;
    }

    public function setLastChangedBy(?string $lastChangedBy): void
    {
        $this->lastChangedBy = $lastChangedBy;
    }

    public function getLastChangedAt(): ?\DateTimeInterface
    {
        return $this->lastChangedAt;
    }

    public function setLastChangedAt(?\DateTimeInterface $lastChangedAt): void
    {
        $this->lastChangedAt = $lastChangedAt;
    }

    public function getPositions(): ?PositionCollection
    {
        return $this->positions;
    }

    public function setPositions(?PositionCollection $positions): void
    {
        $this->positions = $positions;
    }
}
