<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Notification;

use LieferzeitenAdmin\Entity\NotificationTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class NotificationTemplateResolver
{
    public function __construct(private readonly EntityRepository $notificationTemplateRepository)
    {
    }

    /**
     * @param array<string,mixed> $variables
     * @return array{subject:string,contentHtml:string,contentPlain:string}
     */
    public function resolve(string $triggerKey, ?string $salesChannelId, ?string $languageId, array $variables, Context $context): array
    {
        $template = $this->findTemplate($triggerKey, $salesChannelId, $languageId, $context);
        if ($template === null) {
            return [
                'subject' => sprintf('[%s] Notification %s', (string) ($variables['sourceSystem'] ?? 'lieferzeiten'), $triggerKey),
                'contentHtml' => sprintf('<p>Order: %s</p><p>Trigger: %s</p><p>Event: %s</p>', (string) ($variables['externalOrderId'] ?? '-'), $triggerKey, (string) ($variables['eventKey'] ?? '-')),
                'contentPlain' => sprintf("Order: %s\nTrigger: %s\nEvent: %s", (string) ($variables['externalOrderId'] ?? '-'), $triggerKey, (string) ($variables['eventKey'] ?? '-')),
            ];
        }

        return [
            'subject' => $this->render($template->getSubject(), $variables),
            'contentHtml' => $this->render($template->getContentHtml(), $variables),
            'contentPlain' => $this->render($template->getContentPlain(), $variables),
        ];
    }

    private function findTemplate(string $triggerKey, ?string $salesChannelId, ?string $languageId, Context $context): ?NotificationTemplateEntity
    {
        $scopes = [
            [$salesChannelId, $languageId],
            [$salesChannelId, null],
            [null, $languageId],
            [null, null],
        ];

        foreach ($scopes as [$scopeSalesChannelId, $scopeLanguageId]) {
            $criteria = new Criteria();
            $criteria->setLimit(1);
            $criteria->addFilter(new EqualsFilter('triggerKey', $triggerKey));
            $criteria->addFilter(new EqualsFilter('salesChannelId', $scopeSalesChannelId));
            $criteria->addFilter(new EqualsFilter('languageId', $scopeLanguageId));

            $template = $this->notificationTemplateRepository->search($criteria, $context)->first();
            if ($template instanceof NotificationTemplateEntity) {
                return $template;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $variables */
    private function render(string $content, array $variables): string
    {
        $replace = [];
        foreach ($variables as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replace['{{ ' . $key . ' }}'] = (string) $value;
                $replace['{{' . $key . '}}'] = (string) $value;
            }
        }

        return strtr($content, $replace);
    }
}
