# Task — Write-Endpunkte: Optimistische Parallelität und Konfliktbehandlung

Status: `done`
Owner: `LieferzeitenAdmin`
Referenz: `src/Controller/LieferzeitenSyncController.php`, `src/Service/LieferzeitenPositionWriteService.php`

## Ziel
Write-Endpunkte für Positionen (`liefertermin-lieferant`, `neuer-liefertermin`, `comment`) gegen stille Überschreibungen bei parallelen Bearbeitungen absichern.

## Gelieferter Umfang
- Optimistische Parallelitätskontrolle über `updatedAt` in der Anfrage.
- Expliziter API-Fehler bei Bearbeitungskonflikten (`409 CONCURRENT_MODIFICATION`).
- Strategie für partielles Zeilen-Refresh via `refresh`-Payload für die UI.
- Audit-Logging von Konflikten mit Request-Korrelation (`correlation_id`).
- Automatisierter Test für parallele Bearbeitung (zwei Benutzer auf derselben Position).

## Neuer Write-API-Vertrag
- Betroffene Write-Endpunkte verlangen `updatedAt` im Payload.
- Bei veraltetem Token:
  - HTTP `409`,
  - `code = CONCURRENT_MODIFICATION`,
  - eindeutige Fehlermeldung,
  - `refresh` mit aktueller partieller Zeilenversion.

## Abnahmekriterien
- [x] Writes ohne `updatedAt` werden abgelehnt.
- [x] Bearbeitungskonflikte können die aktuelle Version nicht mehr überschreiben.
- [x] API liefert eine vom Frontend auswertbare Konfliktantwort.
- [x] Frontend wendet partielles Zeilen-Refresh nach Konflikt an.
- [x] Konflikt-Ereignisse werden im Audit-Log mit Korrelation protokolliert.
- [x] Test für parallele Bearbeitung vorhanden und grün.
