# Observability, Alerting, SLA und Runbook — LieferzeitenAdmin

Version: `1.0.0`  
Letzte Aktualisierung: `2026-02-10`

## 1) Ziel

Definition eines belastbaren Betriebsfundaments, um den Plugin-Zustand zu überwachen, handlungsfähige Alarme auszulösen und Incidents schneller zu lösen – auf Basis bereits persistierter Daten in:
- `lieferzeiten_audit_log`,
- `lieferzeiten_dead_letter`,
- `lieferzeiten_notification_event`,
- `lieferzeiten_task`,
- `lieferzeiten_paket` / `lieferzeiten_position`.

## 2) Zu überwachende Metriken

### 2.1 Sync
- **Sync-Volumen**: Anzahl `order_synced`-Events in `lieferzeiten_audit_log` (pro 5 Min / 1h / 24h).
- **Sync-Erfolgsrate**: Verhältnis `integration_success` zu `integration_dead_letter` je `source_system`.
- **Sync übersprungen**: Vorkommen des App-Logs `Lieferzeiten sync skipped due to active lock.`.

### 2.2 Status-Push 7/8
- **Backlog Status 7/8**: Anzahl `lieferzeiten_paket` mit `status` in (`7`, `8`) ohne aktuelle Fortschrittsereignisse.
- **Alter Status 7/8**: Mittelwert und P95 seit `last_changed_at` für Aufträge in Status 7/8.
- **Status-Regression**: Fälle von `8` auf niedrigeren Status (über Audit/Correlation-ID erkennbar).

### 2.3 Benachrichtigungen
- **Ereignisse in Queue**: Anzahl `lieferzeiten_notification_event.status = queued`.
- **Alter der Notification-Queue**: P95 von `now - dispatched_at` für nicht konsumierte Events.
- **Vermeidete Duplikate**: durch Idempotenz ignorierte Events (`event_key` bereits vorhanden).

### 2.4 Überfällige Aufgaben
- **Overdue Count**: offene Tasks (`status != closed`) mit `due_date < now`.
- **Overdue Aging**: Mittelwert/P95 des Alters überfälliger Tasks.
- **Abschlussfluss**: Verhältnis abgeschlossener zu neu eröffneten Tasks pro Zeitraum.

## 3) Alerting (Schwellen und Schweregrade)

### 3.1 Wiederholte Integrationsfehler
- **Warnung**: > 20 `integration_dead_letter` in 15 Minuten pro `source_system`.
- **Kritisch**: > 50 `integration_dead_letter` in 15 Minuten oder > 15 Minuten ohne `integration_success`.
- **Erstmaßnahmen**:
  1. Letzte Dead-Letter-Einträge prüfen.
  2. Externe API-Erreichbarkeit prüfen.
  3. Scheduler-/Worker-Prozesse verifizieren.

### 3.2 Dead-Letter-Anstieg
- **Warnung**: Dead-Letter-Queue wächst 3 Intervalle hintereinander.
- **Kritisch**: Queue-Alter > 30 Minuten bei gleichzeitiger Wachstumsrate.
- **Erstmaßnahmen**:
  1. Fehlercodes clustern.
  2. Hohe Wiederholungsrate identifizieren.
  3. Problematische Quellen temporär drosseln.

### 3.3 API-Latenz
- **Warnung**: P95 > 2 s auf Tracking-/Sync-Endpunkten.
- **Kritisch**: P99 > 5 s über 10 Minuten.
- **Erstmaßnahmen**:
  1. Upstream-Latenz vergleichen.
  2. DB-Locks und langsame Queries prüfen.
  3. Worker-Kapazität erhöhen.

## 4) SLA-Ziele

### 4.1 Listing-SLA (Admin-Ansicht)
- P95 Antwortzeit Listings: **< 1,5 s**.
- P99 Antwortzeit Listings: **< 3 s**.
- Fehlerquote 5xx auf Listing-Endpunkten: **< 1 %** je Stunde.

### 4.2 Sync-SLA
- End-to-End-Zeit Eingang → persistiert: **P95 < 10 Min**.
- Geplante Sync-Läufe pro Stunde: **≥ 99 % erfolgreich gestartet**.
- Dead-Letter-Wiederaufnahme: **< 30 Min** bis Erstbearbeitung.

## 5) Incident-Runbook

### 5.1 Incident-Typ A — Integrationsfehler (Shop/Gambio/San6)
1. Incident über `source_system`, Fehlercode, Correlation-ID eingrenzen.
2. Letzte Einträge in `lieferzeiten_dead_letter` und `lieferzeiten_audit_log` prüfen.
3. Payload-Vertrag gegen `IntegrationContractValidator` validieren.
4. Bei externem Ausfall: Fallback aktiv lassen, Stakeholder informieren.
5. Nach Behebung: Replay betroffener Dead-Letter-Batches.

### 5.2 Incident-Typ B — Tracking-Feed verzögert
1. Queue-Backlog und `tracking_history`-Lücken prüfen.
2. Carrier-API-Limits/429 verifizieren.
3. Polling-Intervalle und Retry-Strategie temporär anpassen.
4. Nach Stabilisierung Rückstellung auf Standardkonfiguration.

### 5.3 Incident-Typ C — Überfällige Aufgaben steigen stark an
1. Task-Backlog nach `triggerKey`, Team, Priorität analysieren.
2. Blockierende Abhängigkeiten im Notification-/Sync-Pfad prüfen.
3. Kurzfristig Rebalancing der Task-Zuweisung aktivieren.
4. Operative Nachsteuerung dokumentieren und Ursachenanalyse anstoßen.

## 6) Betriebscadence
- **Täglich**: Dashboard-Review (Sync, Dead-Letter, Overdue-Tasks).
- **Wöchentlich**: SLA-Review und Top-Fehlerquellen.
- **Monatlich**: Schwellwerte neu kalibrieren, Runbook-Drills durchführen.

## 7) Mindestanforderungen an Dashboards
- Filter nach `source_system`, Zeitraum, Schweregrad.
- Drill-down bis Correlation-ID und Einzelereignis.
- Overlay von Deployments zur Korrelation von Regressionen.
- Sicht auf Queue-Alter und Durchsatz gleichzeitig.

## 8) Change-Management
- Jede Änderung an Schwellwerten/SLAs versionieren.
- Änderungen im Changelog und Operations-Channel kommunizieren.
- Nach kritischen Incidents Post-Mortem inkl. Maßnahmen-Tracking pflegen.
