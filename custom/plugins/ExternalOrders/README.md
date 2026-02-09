# External Orders (Externe Bestellungen / External Orders)

## Überblick / Overview
Das Plugin **External Orders** erweitert die Shopware-Administration um eine zentrale Übersicht für externe Bestellungen aus Marktplätzen. Es stellt eine Kanal-Auswahl, Filter und eine Detailansicht bereit, um Bestellungen schnell zu finden und zu analysieren.

The **External Orders** plugin extends the Shopware administration with a central overview of external orders from marketplaces. It provides channel selection, filters, and a detail view to find and analyze orders quickly.

## Funktionsumfang / Features
- **Bestellübersicht** mit Suchfeld, Statusanzeige und aggregierten Kennzahlen.  
  **Order overview** with search, status indicator, and aggregated metrics.
- **Kanalfilter** für verschiedene Marktplätze (z. B. B2B, eBay, Kaufland).  
  **Channel filter** for different marketplaces (e.g., B2B, eBay, Kaufland).
- **Detailansicht** inklusive Kunden-, Zahlungs-, Liefer- und Zusatzdaten.  
  **Detail view** including customer, payment, delivery, and additional data.
- **Statushistorie** und Positionen je Bestellung.  
  **Status history** and line items per order.

## Anforderungen / Requirements
- Shopware 6 (Platform)

## Installation
1. Plugin in das Verzeichnis `custom/plugins/ExternalOrders` legen.  
   Place the plugin in `custom/plugins/ExternalOrders`.
2. Plugin in der Administration installieren und aktivieren.  
   Install and activate the plugin in the administration.

## Update & Migration (CLI)
Wenn das Plugin aktualisiert wurde (z. B. neue Version aus dem Repo), können die Schritte per CLI so aussehen:

When the plugin has been updated (e.g., new version from the repository), the CLI steps can look like this:

1. Plugin-Informationen neu einlesen / Refresh plugin information:
   ```bash
   bin/console plugin:refresh
   ```
2. Plugin aktualisieren / Update the plugin:
   ```bash
   bin/console plugin:update ExternalOrders
   ```
3. Datenbank-Migrationen ausführen (alle Plugins + Core) / Run database migrations (all plugins + core):
   ```bash
   bin/console database:migrate --all
   ```
   Optional nur für dieses Plugin / Optional only for this plugin:
   ```bash
   bin/console database:migrate --identifier ExternalOrders
   ```

## Nutzung / Usage
Nach der Aktivierung erscheint in der Administration ein neuer Menüpunkt unter **Bestellungen**. Dort können externe Bestellungen eingesehen und nach Kanal oder Suchbegriff gefiltert werden.

After activation, a new menu entry appears in the administration under **Orders**. External orders can be viewed there and filtered by channel or search term.

## Version
- **1.0.0**

## Entwickler / Developer
- **Name:** Mohamed Rami Aouinti  
- **E-Mail:** mohamed.rami.aouinti@first-medical.de

## Support
Bei Fragen oder Support-Anfragen bitte die oben genannte E-Mail-Adresse verwenden.  
For questions or support requests, please use the email address above.
