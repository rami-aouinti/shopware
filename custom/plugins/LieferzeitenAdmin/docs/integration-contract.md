# Integration Contract — LieferzeitenAdmin

Version: `1.0.0`  
Dernière mise à jour: `2026-02-10`

## 1) Contrats d'entrée/sortie des APIs

## 1.1 Shopware API (channel: `shopware`)

### Entrée minimale attendue
- `externalId` **ou** `id` **ou** `orderNumber`
- `status`
- `date` **ou** `orderDate`

### Sortie normalisée utilisée dans le plugin
- `externalId`
- `orderNumber`
- `status`
- `orderDate`
- `sourceSystem`

### Exemple de payload réel (anonymisé)
```json
{
  "id": "SW-2026-100045",
  "orderNumber": "100045",
  "status": "5",
  "date": "2026-02-08T09:12:00+00:00",
  "customerEmail": "kunde@example.com",
  "paymentMethod": "prepayment"
}
```

## 1.2 Gambio API (channel: `gambio`)

### Entrée minimale attendue
- `externalId` **ou** `id` **ou** `orderNumber`
- `status`
- `date` **ou** `orderDate`

### Sortie normalisée utilisée dans le plugin
- `externalId`
- `orderNumber`
- `status`
- `orderDate`
- `sourceSystem`

### Exemple de payload réel (anonymisé)
```json
{
  "externalId": "GX-556677",
  "orderNumber": "556677",
  "status": "processing",
  "orderDate": "2026-02-08T07:15:22+00:00",
  "customerEmail": "shopper@example.org",
  "shippingDate": null
}
```

## 1.3 San6 API

### Entrée minimale attendue
- `orderNumber`
- `shippingDate` **ou** `deliveryDate`

### Sortie (merge) vers le modèle métier
- `shippingDate`
- `deliveryDate`
- `parcels`
- `sourceSystem` (optionnel; prend la priorité si présent)

### Exemple de payload réel (anonymisé)
```json
{
  "orderNumber": "100045",
  "shippingDate": "2026-02-09",
  "deliveryDate": "2026-02-11",
  "sourceSystem": "san6",
  "customer": {
    "email": "kunde@example.com"
  },
  "payment": {
    "method": "prepayment"
  },
  "parcels": [
    {"trackingNumber": "00340434161234567890", "carrier": "dhl"}
  ]
}
```

## 1.4 DHL / GLS Tracking API

### Entrée minimale attendue
- `trackingNumber`
- `status`
- `eventTime` **ou** `timestamp`

### Sortie normalisée
- `trackingNumber`
- `status`
- `eventTime`
- `carrier`

### Exemple de payload réel (anonymisé)
```json
{
  "trackingNumber": "00340434161234567890",
  "status": "in_transit",
  "eventTime": "2026-02-09T15:45:10+01:00",
  "carrier": "dhl"
}
```

## 2) Priorité des sources en cas de conflit

Règle globale (par défaut):
1. `San6`
2. `Tracking (DHL/GLS)`
3. `Shop (Shopware/Gambio)`

Application actuelle:
- `sourceSystem` persistant: San6 gagne si fourni.
- `shippingDate` / `deliveryDate`: San6 gagne sur la valeur shop.
- Données tracking (états colis): tracking gagne sur shop pour les événements de transport.

## 3) Règles de fallback (source indisponible)

- **San6 indisponible ou invalide**: le flux continue avec la donnée shop normalisée.
- **Tracking indisponible**: aucune rupture de sync; le statut colis reste inchangé jusqu'au prochain cycle.
- **Shop API invalide**: l'enregistrement est ignoré (pas de persistance partielle) avec log de violation de contrat.
- **Date de paiement manquante en prépaiement**: fallback sur `orderDate` (comportement métier existant).

## 4) Champs obligatoires/minimaux pour persister

### 4.1 Enregistrement `paket`
- `externalOrderId` **ou** `externalId` **ou** `orderNumber`
- `paketNumber` **ou** `packageNumber` **ou** `orderNumber`
- `sourceSystem`

### 4.2 Enregistrement `position`
- `positionNumber` **ou** `orderNumber` **ou** `externalId`
- `status`

### 4.3 Enregistrement `tracking_history`
- `trackingNumber` **ou** `sendenummer`
- `status`
- `eventTime` **ou** `timestamp`

## 5) Validation implémentée

La validation contractuelle est centralisée dans:
- `LieferzeitenAdmin\Service\Integration\IntegrationContractValidator`

Validation active:
- Contrats d'entrée Shopware/Gambio avant traitement.
- Contrat San6 avant merge.
- Contrat minimal de persistance (`paket`) avant upsert.

## 6) Évolution du contrat

Toute évolution doit:
1. incrémenter la version du document,
2. mettre à jour les tests unitaires,
3. conserver la rétrocompatibilité des alias de clés (`externalId|id|orderNumber`, etc.) quand possible.
