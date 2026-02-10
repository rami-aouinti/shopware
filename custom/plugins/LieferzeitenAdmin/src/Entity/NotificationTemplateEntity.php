<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class NotificationTemplateEntity extends Entity
{
    use EntityIdTrait;

    protected string $triggerKey;
    protected ?string $salesChannelId = null;
    protected ?string $languageId = null;
    protected string $subject;
    protected string $contentHtml;
    protected string $contentPlain;

    public function getTriggerKey(): string { return $this->triggerKey; }
    public function setTriggerKey(string $triggerKey): void { $this->triggerKey = $triggerKey; }
    public function getSalesChannelId(): ?string { return $this->salesChannelId; }
    public function setSalesChannelId(?string $salesChannelId): void { $this->salesChannelId = $salesChannelId; }
    public function getLanguageId(): ?string { return $this->languageId; }
    public function setLanguageId(?string $languageId): void { $this->languageId = $languageId; }
    public function getSubject(): string { return $this->subject; }
    public function setSubject(string $subject): void { $this->subject = $subject; }
    public function getContentHtml(): string { return $this->contentHtml; }
    public function setContentHtml(string $contentHtml): void { $this->contentHtml = $contentHtml; }
    public function getContentPlain(): string { return $this->contentPlain; }
    public function setContentPlain(string $contentPlain): void { $this->contentPlain = $contentPlain; }
}
