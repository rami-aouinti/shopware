# Task — Multi-Source-Integrationsvertrag

Status: `done`
Owner: `LieferzeitenAdmin`
Referenz: `docs/integration-contract.md`

## Ziel
Definition, Dokumentation und Validierung eines einheitlichen Integrationsvertrags für:
- Shopware,
- Gambio,
- San6,
- DHL/GLS.

## Gelieferter Umfang
- Dokumentierte Ein-/Ausgabe-Verträge.
- Dokumentierte und implementierte Quellenpriorität bei Konflikten.
- Dokumentierte und angewendete Fallback-Regeln.
- Definierte und validierte Mindestfelder für Persistenz.

## Abnahmekriterien
- [x] Versioniertes Dokument vorhanden: `docs/integration-contract.md`
- [x] Reale (anonymisierte) Payload-Beispiele enthalten.
- [x] Zentrale technische Validierung über einen einheitlichen Validator.
- [x] Unit-Tests decken API-Verträge, Persistenz und Priorisierung ab.
