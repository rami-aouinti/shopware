<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class NeuerLieferterminPaketHistoryEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $paketId = null;

    protected ?\DateTimeInterface $lieferterminFrom = null;

    protected ?\DateTimeInterface $lieferterminTo = null;

    protected ?\DateTimeInterface $liefertermin = null;

    protected ?string $lastChangedBy = null;

    protected ?\DateTimeInterface $lastChangedAt = null;

    protected ?PaketEntity $paket = null;

    public function getPaketId(): ?string
    {
        return $this->paketId;
    }

    public function setPaketId(?string $paketId): void
    {
        $this->paketId = $paketId;
    }

    public function getLieferterminFrom(): ?\DateTimeInterface
    {
        return $this->lieferterminFrom;
    }

    public function setLieferterminFrom(?\DateTimeInterface $lieferterminFrom): void
    {
        $this->lieferterminFrom = $lieferterminFrom;
    }

    public function getLieferterminTo(): ?\DateTimeInterface
    {
        return $this->lieferterminTo;
    }

    public function setLieferterminTo(?\DateTimeInterface $lieferterminTo): void
    {
        $this->lieferterminTo = $lieferterminTo;
    }

    public function getLiefertermin(): ?\DateTimeInterface
    {
        return $this->liefertermin;
    }

    public function setLiefertermin(?\DateTimeInterface $liefertermin): void
    {
        $this->liefertermin = $liefertermin;
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

    public function getPaket(): ?PaketEntity
    {
        return $this->paket;
    }

    public function setPaket(?PaketEntity $paket): void
    {
        $this->paket = $paket;
    }
}
