<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use LieferzeitenAdmin\Service\Notification\NotificationTriggerCatalog;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021410NotificationTemplateSeeds extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021410;
    }

    public function update(Connection $connection): void
    {
        $templates = [
            NotificationTriggerCatalog::PAYMENT_RECEIVED_VORKASSE => [
                'subject' => '[{{ sourceSystem }}] Paiement reçu pour la commande {{ externalOrderId }}',
                'contentHtml' => '<p>Le paiement Vorkasse est confirmé pour la commande <strong>{{ externalOrderId }}</strong>.</p><p>Date de paiement: {{ paymentDate }}</p>',
                'contentPlain' => "Le paiement Vorkasse est confirmé pour la commande {{ externalOrderId }}.\nDate de paiement: {{ paymentDate }}",
            ],
            NotificationTriggerCatalog::ORDER_COMPLETED_REVIEW_REMINDER => [
                'subject' => '[{{ sourceSystem }}] Commande {{ externalOrderId }} terminée - rappel d\'évaluation',
                'contentHtml' => '<p>La commande <strong>{{ externalOrderId }}</strong> est terminée.</p><p>Vous pouvez demander un avis client.</p>',
                'contentPlain' => "La commande {{ externalOrderId }} est terminée.\nVous pouvez demander un avis client.",
            ],
            NotificationTriggerCatalog::DELIVERY_DATE_ASSIGNED => [
                'subject' => '[{{ sourceSystem }}] Date de livraison attribuée - {{ externalOrderId }}',
                'contentHtml' => '<p>Une date de livraison a été attribuée pour la commande <strong>{{ externalOrderId }}</strong>.</p><p>Date: {{ deliveryDate }}</p>',
                'contentPlain' => "Une date de livraison a été attribuée pour la commande {{ externalOrderId }}.
Date: {{ deliveryDate }}",
            ],
            NotificationTriggerCatalog::DELIVERY_DATE_UPDATED => [
                'subject' => '[{{ sourceSystem }}] Date de livraison modifiée - {{ externalOrderId }}',
                'contentHtml' => '<p>La date de livraison a été modifiée pour la commande <strong>{{ externalOrderId }}</strong>.</p><p>Nouvelle date: {{ deliveryDate }}</p>',
                'contentPlain' => "La date de livraison a été modifiée pour la commande {{ externalOrderId }}.
Nouvelle date: {{ deliveryDate }}",
            ],
            NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUESTED => [
                'subject' => '[{{ sourceSystem }}] Demande de date de livraison supplémentaire - {{ externalOrderId }}',
                'contentHtml' => '<p>Une demande de date supplémentaire a été créée pour la commande <strong>{{ externalOrderId }}</strong>.</p><p>Initiateur: {{ initiator }}</p>',
                'contentPlain' => "Une demande de date supplémentaire a été créée pour la commande {{ externalOrderId }}.\nInitiateur: {{ initiator }}",
            ],
            NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUEST_CLOSED => [
                'subject' => '[{{ sourceSystem }}] Demande de date clôturée - {{ externalOrderId }}',
                'contentHtml' => '<p>La demande de date supplémentaire est clôturée pour la commande <strong>{{ externalOrderId }}</strong>.</p><p>Statut: {{ status }}</p>',
                'contentPlain' => "La demande de date supplémentaire est clôturée pour la commande {{ externalOrderId }}.\nStatut: {{ status }}",
            ],
            NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUEST_REOPENED => [
                'subject' => '[{{ sourceSystem }}] Demande de date réouverte - {{ externalOrderId }}',
                'contentHtml' => '<p>La demande de date supplémentaire a été réouverte pour la commande <strong>{{ externalOrderId }}</strong>.</p>',
                'contentPlain' => "La demande de date supplémentaire a été réouverte pour la commande {{ externalOrderId }}.",
            ],
        ];

        foreach ($templates as $triggerKey => $template) {
            $exists = $connection->fetchOne(
                'SELECT 1 FROM `lieferzeiten_notification_template` WHERE `trigger_key` = :triggerKey AND `sales_channel_id` IS NULL AND `language_id` IS NULL LIMIT 1',
                ['triggerKey' => $triggerKey],
            );

            if ($exists !== false) {
                continue;
            }

            $connection->executeStatement(
                'INSERT INTO `lieferzeiten_notification_template` (`id`, `trigger_key`, `sales_channel_id`, `language_id`, `subject`, `content_html`, `content_plain`, `created_at`) VALUES (UNHEX(REPLACE(UUID(), "-", "")), :triggerKey, NULL, NULL, :subject, :contentHtml, :contentPlain, :createdAt)',
                [
                    'triggerKey' => $triggerKey,
                    'subject' => $template['subject'],
                    'contentHtml' => $template['contentHtml'],
                    'contentPlain' => $template['contentPlain'],
                    'createdAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
                ],
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
