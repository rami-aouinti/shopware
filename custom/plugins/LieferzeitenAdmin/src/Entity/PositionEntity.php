<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PositionEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $positionNumber = null;

    protected ?string $articleNumber = null;

    protected ?string $status = null;

    protected ?\DateTimeInterface $orderedAt = null;

    protected ?string $paketId = null;

    protected ?string $lastChangedBy = null;

    protected ?\DateTimeInterface $lastChangedAt = null;

    protected ?PaketEntity $paket = null;

    protected ?LieferterminLieferantHistoryCollection $lieferterminLieferantHistories = null;

    protected ?NeuerLieferterminHistoryCollection $neuerLieferterminHistories = null;

    protected ?SendenummerHistoryCollection $sendenummerHistories = null;

    public function getPositionNumber(): ?string
    {
        return $this->positionNumber;
    }

    public function setPositionNumber(?string $positionNumber): void
    {
        $this->positionNumber = $positionNumber;
    }

    public function getArticleNumber(): ?string
    {
        return $this->articleNumber;
    }

    public function setArticleNumber(?string $articleNumber): void
    {
        $this->articleNumber = $articleNumber;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getOrderedAt(): ?\DateTimeInterface
    {
        return $this->orderedAt;
    }

    public function setOrderedAt(?\DateTimeInterface $orderedAt): void
    {
        $this->orderedAt = $orderedAt;
    }

    public function getPaketId(): ?string
    {
        return $this->paketId;
    }

    public function setPaketId(?string $paketId): void
    {
        $this->paketId = $paketId;
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

    public function getLieferterminLieferantHistories(): ?LieferterminLieferantHistoryCollection
    {
        return $this->lieferterminLieferantHistories;
    }

    public function setLieferterminLieferantHistories(
        ?LieferterminLieferantHistoryCollection $lieferterminLieferantHistories
    ): void {
        $this->lieferterminLieferantHistories = $lieferterminLieferantHistories;
    }

    public function getNeuerLieferterminHistories(): ?NeuerLieferterminHistoryCollection
    {
        return $this->neuerLieferterminHistories;
    }

    public function setNeuerLieferterminHistories(
        ?NeuerLieferterminHistoryCollection $neuerLieferterminHistories
    ): void {
        $this->neuerLieferterminHistories = $neuerLieferterminHistories;
    }

    public function getSendenummerHistories(): ?SendenummerHistoryCollection
    {
        return $this->sendenummerHistories;
    }

    public function setSendenummerHistories(?SendenummerHistoryCollection $sendenummerHistories): void
    {
        $this->sendenummerHistories = $sendenummerHistories;
    }
}
