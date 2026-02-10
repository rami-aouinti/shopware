<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Notification;

class NotificationTriggerCatalog
{
    public const ORDER_CREATED = 'commande.creee';
    public const ORDER_STATUS_CHANGED = 'commande.changement_statut';
    public const TRACKING_UPDATED = 'tracking.mis_a_jour';
    public const DELIVERY_DATE_CHANGED = 'changements.date_livraison';
    public const CUSTOMS_REQUIRED = 'douane.requise';
    public const PAYMENT_REMINDER_VORKASSE = 'rappel.vorkasse';
    public const SHIPPING_DATE_OVERDUE = 'versand.datum.ueberfaellig';
    public const ADDITIONAL_DELIVERY_DATE_REQUESTED = 'liefertermin.anfrage.zusaetzlich';
    public const ADDITIONAL_DELIVERY_DATE_REQUEST_CLOSED = 'liefertermin.anfrage.geschlossen';

    /** @return array<int,string> */
    public static function all(): array
    {
        return [
            self::ORDER_CREATED,
            self::ORDER_STATUS_CHANGED,
            self::TRACKING_UPDATED,
            self::DELIVERY_DATE_CHANGED,
            self::CUSTOMS_REQUIRED,
            self::PAYMENT_REMINDER_VORKASSE,
            self::SHIPPING_DATE_OVERDUE,
            self::ADDITIONAL_DELIVERY_DATE_REQUESTED,
            self::ADDITIONAL_DELIVERY_DATE_REQUEST_CLOSED,
        ];
    }

    /** @return array<int,string> */
    public static function channels(): array
    {
        return ['email', 'sms', 'webhook'];
    }
}
