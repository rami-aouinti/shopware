# Task — Echte Task-Entität

Status: `done`
Owner: `LieferzeitenAdmin`

## Erlaubte Statuswerte
- `open`
- `in_progress`
- `done`
- `reopened`
- `cancelled`

## Erlaubte Transitionen
- `open` -> `in_progress`, `done`, `cancelled`
- `in_progress` -> `done`, `cancelled`
- `done` -> `reopened`
- `reopened` -> `in_progress`, `done`, `cancelled`
- `cancelled` -> `reopened`

## Regeln zum Schließen
- **Manuell**: über API `POST /api/_action/lieferzeiten/tasks/{taskId}/close` (Transition nach `done`).
- **Automatisch**: Jede vorhandene geschlossene Task (`done`/`cancelled`) wird bei einer Neuanlage für dasselbe Paar `(positionId, triggerKey)` automatisch wieder geöffnet.

## Regeln zur Duplikatvermeidung (Position/Trigger)
- Deduplizierungs-Schlüssel: `(positionId, triggerKey)`.
- Eine Unique-Constraint verhindert doppelte DB-Einträge für dieses Paar.
- Bei einer Erstellung:
  - existiert bereits eine aktive Task für dieses Paar, wird deren `id` wiederverwendet;
  - ist die vorhandene Task geschlossen, wird sie wieder geöffnet.

## Benachrichtigungen für Initiator
- Benachrichtigung an Initiator bei Abschluss (`done` / `cancelled`).
- Benachrichtigung an Initiator bei Wiedereröffnung (`reopened`).

## Erwartete Tests
- Unit-Tests der Transitionen (gültig/ungültig, Abschluss, Wiedereröffnung, Abbruch).
- API-Integrationstests für Transition-Endpunkte (`assign`, `close`, `reopen`, `cancel`) inkl. ACL-Validierung.
