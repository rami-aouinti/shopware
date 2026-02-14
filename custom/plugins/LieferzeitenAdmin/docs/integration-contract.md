# Integrationsvertrag — LieferzeitenAdmin

Version: `1.1.0`  
Letzte Aktualisierung: `2026-02-14`

## 1) Ein-/Ausgabe-Verträge der APIs

## 1.1 Shopware API (Kanal: `shopware`)

### Minimale erwartete Eingabe
- `externalId` **oder** `id` **oder** `orderNumber`
- `status`
- `date` **oder** `orderDate`

### Normalisierte Ausgabe im Plugin
- `externalId`
- `orderNumber`
- `status`
- `orderDate`
- `sourceSystem`
- `customerFirstName` (optional)
- `customerLastName` (optional)
- `customerAdditionalName` (optional)

### Beispiel-Payload (anonymisiert)
```json
{
  "id": "SW-2026-100045",
  "orderNumber": "100045",
  "status": "5",
  "date": "2026-02-08T09:12:00+00:00",
  "customerEmail": "kunde@example.com",
  "customerFirstName": "Max",
  "customerLastName": "Mustermann",
  "customerAdditionalName": "c/o Praxis Nord",
  "paymentMethod": "prepayment"
}
```

## 1.2 Gambio API (Kanal: `gambio`)

### Minimale erwartete Eingabe
- `externalId` **oder** `id` **oder** `orderNumber`
- `status`
- `date` **oder** `orderDate`

### Normalisierte Ausgabe im Plugin
- `externalId`
- `orderNumber`
- `status`
- `orderDate`
- `sourceSystem`
- `customerFirstName` (optional)
- `customerLastName` (optional)
- `customerAdditionalName` (optional)

### Beispiel-Payload (anonymisiert)
```json
{
  "externalId": "GX-556677",
  "orderNumber": "556677",
  "status": "processing",
  "orderDate": "2026-02-08T07:15:22+00:00",
  "customerEmail": "shopper@example.org",
  "customerFirstName": "Erika",
  "customerLastName": "Musterfrau",
  "customerAdditionalName": "Station 3",
  "shippingDate": null
}
```

## 1.3 San6 API

### Minimale erwartete Eingabe
- `orderNumber`
- `shippingDate` **oder** `deliveryDate`

### Ausgabe (Merge) ins Geschäftsmodell
- `shippingDate`
- `deliveryDate`
- `parcels`
- `sourceSystem` (optional; hat Priorität, wenn vorhanden)
- `customerFirstName` / `customerLastName` / `customerAdditionalName` (aus `customer.*`, optional)

### Beispiel-Payload (anonymisiert)
```json
{
  "orderNumber": "100045",
  "shippingDate": "2026-02-09",
  "deliveryDate": "2026-02-11",
  "sourceSystem": "san6",
  "customer": {
    "email": "kunde@example.com",
    "firstName": "Max",
    "lastName": "Mustermann",
    "additionalName": "c/o Praxis Nord"
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

### Minimale erwartete Eingabe
- `trackingNumber`
- `status`
- `eventTime` **oder** `timestamp`

### Normalisierte Ausgabe
- `trackingNumber`
- `status`
- `eventTime`
- `carrier`

### Beispiel-Payload (anonymisiert)
```json
{
  "trackingNumber": "00340434161234567890",
  "status": "in_transit",
  "eventTime": "2026-02-09T15:45:10+01:00",
  "carrier": "dhl"
}
```

## 2) Priorität der Quellen bei Konflikten

Standardregel:
1. `San6`
2. `Tracking (DHL/GLS)`
3. `Shop (Shopware/Gambio)`

Aktuelle Anwendung:
- Persistentes `sourceSystem`: San6 gewinnt, wenn vorhanden.
- `shippingDate` / `deliveryDate`: San6 hat Vorrang vor Shop-Werten.
- Tracking-Daten (Paketstatus): Tracking gewinnt gegenüber Shop-Events.

## 3) Fallback-Regeln (Quelle nicht verfügbar)

- **San6 nicht verfügbar oder ungültig**: Fluss läuft mit normalisierten Shop-Daten weiter.
- **Tracking nicht verfügbar**: Kein Sync-Abbruch; Paketstatus bleibt bis zum nächsten Zyklus unverändert.
- **Shop-API ungültig**: Datensatz wird verworfen (keine partielle Persistenz) und als Vertragsverletzung geloggt.
- **Fehlendes Zahlungsdatum bei Vorkasse**: Fallback auf `orderDate` (bestehendes Business-Verhalten).

## 4) Pflicht-/Minimalfelder für Persistenz

### 4.1 Datensatz `paket`
- `externalOrderId` **oder** `externalId` **oder** `orderNumber`
- `paketNumber` **oder** `packageNumber` **oder** `orderNumber`
- `sourceSystem`
- optional: `customerFirstName`, `customerLastName`, `customerAdditionalName`

### 4.2 Datensatz `position`
- `positionNumber` **oder** `orderNumber` **oder** `externalId`
- `status`

### 4.3 Datensatz `tracking_history`
- `trackingNumber` **oder** `sendenummer`
- `status`
- `eventTime` **oder** `timestamp`

## 5) Implementierte Validierung

Die Vertragsvalidierung ist zentralisiert in:
- `LieferzeitenAdmin\Service\Integration\IntegrationContractValidator`

Aktive Validierung:
- Eingangsverträge für Shopware/Gambio vor der Verarbeitung.
- San6-Vertrag vor dem Merge.
- Minimaler Persistenzvertrag (`paket`) vor dem Upsert.

## 6) Weiterentwicklung des Vertrags

Jede Vertragsänderung muss:
1. die Dokumentversion erhöhen,
2. die Unit-Tests aktualisieren,
3. Rückwärtskompatibilität von Schlüssel-Aliasen (`externalId|id|orderNumber` usw.) soweit möglich erhalten.
