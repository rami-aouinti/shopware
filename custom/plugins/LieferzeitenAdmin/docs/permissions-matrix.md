# Berechtigungsmatrix — LieferzeitenAdmin

## Rollen
- `lieferzeiten.viewer`
- `lieferzeiten.editor`

## Grundregeln
- `viewer` darf lesen.
- `editor` darf lesen und schreiben.
- Schreibende Endpunkte müssen mindestens `editor` erfordern.

## Matrix

| Bereich | Endpoint / Funktion | viewer | editor |
|---|---|---:|---:|
| Bestellungen | `GET /api/_action/lieferzeiten/orders` | ✅ | ✅ |
| Tracking | `GET /api/_action/lieferzeiten/tracking/{carrier}/{trackingNumber}` | ✅ | ✅ |
| Tasks lesen | `GET /api/_action/lieferzeiten/tasks` | ✅ | ✅ |
| Sync starten | `POST /api/_action/lieferzeiten/sync` | ❌ | ✅ |
| Position schreiben | `POST /api/_action/lieferzeiten/position/{id}/liefertermin-lieferant` | ❌ | ✅ |
| Position schreiben | `POST /api/_action/lieferzeiten/position/{id}/neuer-liefertermin` | ❌ | ✅ |
| Position schreiben | `POST /api/_action/lieferzeiten/position/{id}/comment` | ❌ | ✅ |
| Position schreiben | `POST /api/_action/lieferzeiten/position/{id}/additional-delivery-request` | ❌ | ✅ |
| Task zuweisen | `POST /api/_action/lieferzeiten/tasks/{taskId}/assign` | ❌ | ✅ |
| Task schließen | `POST /api/_action/lieferzeiten/tasks/{taskId}/close` | ❌ | ✅ |
| Task wieder öffnen | `POST /api/_action/lieferzeiten/tasks/{taskId}/reopen` | ❌ | ✅ |
| Task abbrechen | `POST /api/_action/lieferzeiten/tasks/{taskId}/cancel` | ❌ | ✅ |

## Hinweise
- Die Notification `liefertermin.anfrage.geschlossen` ist **geschäftskritisch** und wird beim Schließen/Abbrechen einer Zusatzliefertermin-Anfrage trotz deaktivierter Toggles erzwungen gequeued (`forceIfCritical`).
- Fehlende Berechtigung führt typischerweise zu HTTP `403`.
- Rollen müssen dem Admin-Benutzer explizit zugewiesen werden.
