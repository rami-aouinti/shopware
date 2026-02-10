# Lieferzeiten permissions matrix

## ACL roles

- `lieferzeiten.viewer`: read-only access to Lieferzeiten API data and admin views.
- `lieferzeiten.editor`: write access for all data-changing Lieferzeiten actions (depends on `lieferzeiten.viewer`).

## API endpoints

| Action | Endpoint | Method | Required permission |
|---|---|---|---|
| List tasks | `/api/_action/lieferzeiten/tasks` | `GET` | `lieferzeiten.viewer` |
| List orders | `/api/_action/lieferzeiten/orders` | `GET` | `lieferzeiten.viewer` |
| Read statistics | `/api/_action/lieferzeiten/statistics` | `GET` | `lieferzeiten.viewer` |
| Read tracking history | `/api/_action/lieferzeiten/tracking/{carrier}/{trackingNumber}` | `GET` | `lieferzeiten.viewer` |
| Assign task | `/api/_action/lieferzeiten/tasks/{taskId}/assign` | `POST` | `lieferzeiten.editor` |
| Close task | `/api/_action/lieferzeiten/tasks/{taskId}/close` | `POST` | `lieferzeiten.editor` |
| Start sync/import | `/api/_action/lieferzeiten/sync` | `POST` | `lieferzeiten.editor` |
| Save supplier delivery range | `/api/_action/lieferzeiten/position/{positionId}/liefertermin-lieferant` | `POST` | `lieferzeiten.editor` |
| Save new delivery range | `/api/_action/lieferzeiten/position/{positionId}/neuer-liefertermin` | `POST` | `lieferzeiten.editor` |
| Save comment | `/api/_action/lieferzeiten/position/{positionId}/comment` | `POST` | `lieferzeiten.editor` |
| Create additional delivery request | `/api/_action/lieferzeiten/position/{positionId}/additional-delivery-request` | `POST` | `lieferzeiten.editor` |

## Admin UI actions

| UI area | Action | Required permission | Behavior without permission |
|---|---|---|---|
| Order table | Save supplier date | `lieferzeiten.editor` | Save button hidden; date pickers read-only |
| Order table | Save new delivery date | `lieferzeiten.editor` | Save button hidden; date pickers read-only |
| Order table | Save comment | `lieferzeiten.editor` | Save button hidden; text field read-only |
| Order table | Additional delivery request | `lieferzeiten.editor` | Button hidden |
| Settings lists | Create entries | `lieferzeiten.editor` | Create button hidden |
| Settings lists | Inline save/delete | `lieferzeiten.editor` | Inline edit + delete disabled |
| Tasks API close action | Close task | `lieferzeiten.editor` | API returns `403` via ACL when missing permission |

## Notes

- Read operations are consistently bound to `lieferzeiten.viewer`.
- Write operations are consistently bound to `lieferzeiten.editor`.
- ACL enforcement for missing permissions is handled centrally by Shopware admin API (`403 Forbidden`).
