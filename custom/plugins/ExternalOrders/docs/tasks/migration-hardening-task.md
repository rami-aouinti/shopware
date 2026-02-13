# Aufgabe: Absicherung von Migrationen (SQL/DAL)

## Ziel
Sicherstellen, dass kommende Migrationen des Plugins `ExternalOrders` ohne Unterbrechung in Produktion ausgerollt werden können – mit klarer Strategie für Kompatibilität, Rollback und Validierung.

## Umfang
- SQL-Migrationen (`MigrationStep::update`) und DAL-Auswirkungen (Definition/Entity/Repository).
- Hinzufügen neuer Felder inkl. zugehöriger Backfill-Skripte.
- Sichere Ausführung auf bestehenden Datenbanken (heterogene Altdaten).

## Erwartete Ergebnisse

### 1) Reihenfolge von SQL-/DAL-Migrationen
- Exakte Ausführungsreihenfolge festlegen:
  1. Rückwärtskompatible SQL-Schema-Erweiterung (Tabellen/Spalten/Indizes nullable oder mit sicheren Defaults).
  2. DAL-Anpassung (Definition/Entity/Service), damit altes und neues Modell lesbar sind.
  3. Schrittweise Aktivierung von Schreibvorgängen auf neue Felder.
  4. Bereinigung/destruktive Migration in einem späteren Release.
- Abhängigkeiten zwischen Migrationen dokumentieren (Timestamp + technische Voraussetzungen).

### 2) Backfill-Skripte für neue Felder
- Eigenes Backfill-Skript (Command/Service) erstellen, das neue Felder aus Bestandsdaten befüllt.
- Batch-/Paging-Modus erzwingen (Speicherlimit, Wiederaufnahme möglich).
- Fortschrittslogs und Metriken ergänzen (gesamt verarbeitet, Fehler, Wiederholungen).
- Dry-Run-Modus vorsehen, um Auswirkungen vor echter Ausführung zu schätzen.

### 3) Kompatibilität mit Bestandsdaten
- Explizit prüfen:
  - Nullbarkeit / Standardwerte,
  - Legacy-Formate,
  - Unique-Constraints und mögliche Kollisionen,
  - Anwendungssicht vor/nach Migration.
- Randfälle und automatische Korrekturstrategie dokumentieren.

### 4) Rollback-Prozedur (technisch + operativ)
- **Technisch**:
  - Anwendungsrollback auf Version N-1,
  - DB-Strategie (Snapshot/Wiederherstellung, kompensierende Skripte, Deaktivierungs-Flags).
- **Operativ**:
  - Incident-Runbook (Verantwortlichkeiten, Reihenfolge der Aktionen, Zielzeiten),
  - interne Kommunikation (Support/Ops/Dev),
  - Go/No-Go-Kriterien und Rollback-Auslösung.

### 5) Checkliste für Post-Migrations-Validierung
- Migration ohne SQL-Fehler ausgeführt.
- Datenintegrität validiert (Zählungen vorher/nachher).
- Backfill abgeschlossen und Fehlerrate dokumentiert.
- Kritische Anwendungspfade validiert (Smoke-Test-Liste).
- Monitoring/Alerting während Stabilisierung geprüft.
- Fachliche Endabnahme und Abschluss des Changes.

## Abnahmekriterien
- Versionierter und geprüfter Migrationsplan.
- Rollback-Runbook vorhanden und in der Testumgebung validiert.
- Kompatibilitätsnachweis auf produktionsnahem Datensatz.
- Automatisierte Tests für idempotente Migrationen hinzugefügt und grün.

## Umzusetzende Tests (Idempotenz)
- Jede `update()`-Migration mindestens zweimal in Tests ausführen.
- Fehlerfreiheit sowie unveränderte Schema-/Datenintegrität verifizieren.
- Sicherstellen, dass `updateDestructive()` auch bei Wiederholung stabil bleibt.
