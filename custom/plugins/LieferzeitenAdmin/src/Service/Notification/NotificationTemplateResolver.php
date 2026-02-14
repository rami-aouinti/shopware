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
            return $this->resolveDefaultTemplate($triggerKey, $variables);
        }

        return [
            'subject' => $this->render($template->getSubject(), $variables),
            'contentHtml' => $this->render($template->getContentHtml(), $variables),
            'contentPlain' => $this->render($template->getContentPlain(), $variables),
        ];
    }

    /**
     * @param array<string,mixed> $variables
     * @return array{subject:string,contentHtml:string,contentPlain:string}
     */
    private function resolveDefaultTemplate(string $triggerKey, array $variables): array
    {
        $sourceSystem = (string) ($variables['sourceSystem'] ?? 'lieferzeiten');
        $externalOrderId = (string) ($variables['externalOrderId'] ?? '-');

        $defaults = [
            NotificationTriggerCatalog::PAYMENT_RECEIVED_VORKASSE => [
                'subject' => sprintf('[%s] Paiement reçu pour la commande %s', $sourceSystem, $externalOrderId),
                'contentHtml' => sprintf('<p>Le paiement Vorkasse est confirmé pour la commande <strong>%s</strong>.</p><p>Date de paiement: %s</p>', $externalOrderId, (string) ($variables['paymentDate'] ?? '-')),
                'contentPlain' => sprintf("Le paiement Vorkasse est confirmé pour la commande %s.
Date de paiement: %s", $externalOrderId, (string) ($variables['paymentDate'] ?? '-')),
            ],
            NotificationTriggerCatalog::ORDER_COMPLETED_REVIEW_REMINDER => [
                'subject' => sprintf("[%s] Commande %s terminée - rappel d'évaluation", $sourceSystem, $externalOrderId),
                'contentHtml' => sprintf('<p>La commande <strong>%s</strong> est terminée.</p><p>Vous pouvez demander un avis client.</p>', $externalOrderId),
                'contentPlain' => sprintf("La commande %s est terminée.
Vous pouvez demander un avis client.", $externalOrderId),
            ],
            NotificationTriggerCatalog::DELIVERY_DATE_ASSIGNED => [
                'subject' => sprintf('[%s] Date de livraison attribuée - %s', $sourceSystem, $externalOrderId),
                'contentHtml' => sprintf('<p>Une date de livraison a été attribuée pour la commande <strong>%s</strong>.</p><p>Date: %s</p>', $externalOrderId, (string) ($variables['deliveryDate'] ?? '-')),
                'contentPlain' => sprintf("Une date de livraison a été attribuée pour la commande %s.
Date: %s", $externalOrderId, (string) ($variables['deliveryDate'] ?? '-')),
            ],
            NotificationTriggerCatalog::DELIVERY_DATE_UPDATED => [
                'subject' => sprintf('[%s] Date de livraison modifiée - %s', $sourceSystem, $externalOrderId),
                'contentHtml' => sprintf('<p>La date de livraison a été modifiée pour la commande <strong>%s</strong>.</p><p>Nouvelle date: %s</p>', $externalOrderId, (string) ($variables['deliveryDate'] ?? '-')),
                'contentPlain' => sprintf("La date de livraison a été modifiée pour la commande %s.
Nouvelle date: %s", $externalOrderId, (string) ($variables['deliveryDate'] ?? '-')),
            ],
        ];

        return $defaults[$triggerKey] ?? [
            'subject' => sprintf('[%s] Notification %s', $sourceSystem, $triggerKey),
            'contentHtml' => sprintf('<p>Order: %s</p><p>Trigger: %s</p><p>Event: %s</p>', $externalOrderId, $triggerKey, (string) ($variables['eventKey'] ?? '-')),
            'contentPlain' => sprintf("Order: %s
Trigger: %s
Event: %s", $externalOrderId, $triggerKey, (string) ($variables['eventKey'] ?? '-')),
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
