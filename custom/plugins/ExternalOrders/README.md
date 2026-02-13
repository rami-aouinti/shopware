# External Orders (Externe Bestellungen)

## Überblick
Das Plugin **External Orders** erweitert die Shopware-Administration um eine zentrale Übersicht für externe Bestellungen aus Marktplätzen. Es bietet Kanalauswahl, Filter und eine Detailansicht, um Bestellungen schnell zu finden und zu analysieren.

## Funktionsumfang
- **Bestellübersicht** mit Suchfeld, Statusanzeige und aggregierten Kennzahlen.
- **Kanalfilter** für verschiedene Marktplätze (z. B. B2B, eBay, Kaufland).
- **Detailansicht** inklusive Kunden-, Zahlungs-, Liefer- und Zusatzdaten.
- **Statushistorie** und Positionen je Bestellung.

## Anforderungen
- Shopware 6 (Platform)

## Installation
1. Plugin in das Verzeichnis `custom/plugins/ExternalOrders` legen.
2. Plugin in der Administration installieren und aktivieren.

## Update & Migration (CLI)
Wenn das Plugin aktualisiert wurde (z. B. neue Version aus dem Repository), können die Schritte per CLI so aussehen:

1. Plugin-Informationen neu einlesen:
   ```bash
   bin/console plugin:refresh
   ```
2. Plugin aktualisieren:
   ```bash
   bin/console plugin:update ExternalOrders
   ```
3. Datenbank-Migrationen ausführen (alle Plugins + Core):
   ```bash
   bin/console database:migrate --all
   ```
   Optional nur für dieses Plugin:
   ```bash
   bin/console database:migrate --identifier ExternalOrders
   ```

## Nutzung
Nach der Aktivierung erscheint in der Administration unter **Bestellungen** ein neuer Menüpunkt. Dort können externe Bestellungen eingesehen und nach Kanal oder Suchbegriff gefiltert werden.

### SAN6 Versandstrategie `filetransferurl`
- Bei der Strategie `filetransferurl` erzeugt das Plugin für jeden Export eine signierte Download-URL (`api.external-orders.export.file-transfer`).
- Diese URL ist explizit für Machine-to-Machine-Zugriffe ohne Admin-API-Login freigegeben (`auth_required=false`, keine ACL).
- Der Schutz erfolgt ausschließlich über den signierten Token in der URL (HMAC-Signatur + Ablaufzeit).
- Gültiger Token: Rückgabe `200` mit `Content-Type: application/xml` und Export-XML.
- Ungültiger oder abgelaufener Token: Rückgabe `404`.

## Version
- **1.0.0**

## Entwickler
- **Name:** Mohamed Rami Aouinti
- **E-Mail:** mohamed.rami.aouinti@first-medical.de

## Support
Bei Fragen oder Support-Anfragen bitte die oben genannte E-Mail-Adresse verwenden.
