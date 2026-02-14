# Résolution `salesChannelId` pour les notifications

## Sources par flux

- **Import commandes (`LieferzeitenImportService`)**
  - Priorité: `payload.salesChannelId` entrant -> résolution `order.order_number` -> fallback custom field `externalOrderId` -> mapping config `sourceSystem -> salesChannelId`.
  - Le résultat est persisté dans `lieferzeiten_paket.sales_channel_id`.
- **Reminders Vorkasse (`VorkassePaymentReminderService`)**
  - Priorité: `paket.salesChannelId` persistant -> même stratégie de résolution que ci-dessus.
  - Si résolu et manquant en base, la valeur est réécrite sur le paquet.
- **Événements task (`LieferzeitenPositionWriteService` / `LieferzeitenTaskService`)**
  - Le contexte task reçoit `salesChannelId` depuis le paquet lié à la position.
  - Lors des transitions de task, la valeur est revalidée via le resolver avant `dispatch`.

## Fallback global explicite

Si aucun canal n'est déterminable:

1. `NotificationToggleResolver::isEnabled(...)` est appelé avec `salesChannelId = null`.
2. Le resolver de toggle applique alors la configuration globale (`sales_channel_id IS NULL`).
3. Si aucune ligne globale n'existe, le comportement reste **enabled par défaut**.

## Mapping de secours

Configurer `LieferzeitenAdmin.config.notificationSalesChannelMapping` avec un objet JSON:

```json
{
  "shopware": "018f4f5f5fe873f9a43b8b0c4d2e6f11",
  "gambio": "018f4f5f6a6e7a41b8f977a11d4e9a22"
}
```

La clé est normalisée en lowercase (`sourceSystem/domain`).
