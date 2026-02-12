# Task — Observability, Alerts, SLA und Incident-Runbook

Status: `done`
Owner: `LieferzeitenAdmin`
Referenz: `docs/observability-sla-runbook.md`

## Ziel
Definition eines versionierten operativen Rahmens für:
- zentrale Metriken (Sync, Status-Push 7/8, Benachrichtigungen, überfällige Tasks),
- Alerts (wiederholte Fehler, steigende Dead-Letter, API-Latenz),
- Ziel-SLAs (Listing und Sync-Dauer),
- Incident-Runbook (Diagnose + Maßnahmen).

## Gelieferter Umfang
- Katalog handlungsorientierter Metriken für Dashboards.
- Alert-Schwellen mit Schweregrad und Sofortmaßnahmen.
- Explizite SLA-Ziele (P95/P99).
- Operatives Runbook für drei Incident-Familien.
- Dokumentierte Anbindung an bestehende Plugin-Quellen:
  - `lieferzeiten_audit_log`,
  - `lieferzeiten_dead_letter`,
  - `lieferzeiten_notification_event`,
  - `lieferzeiten_task`.

## Abnahmekriterien
- [x] Versioniertes Dokument vorhanden: `docs/observability-sla-runbook.md`
- [x] Metriken decken Sync, Status 7/8, Benachrichtigungen und Overdue-Tasks ab.
- [x] Alerts für wiederholte Fehler, Dead-Letter und API-Latenz sind definiert.
- [x] Listing-/Sync-SLAs sind messbar definiert.
- [x] Incident-Runbook enthält Diagnoseschritte und Maßnahmen.
- [x] Explizite Anbindung an bestehende Log-/Audit-/Dead-Letter-Quellen.
