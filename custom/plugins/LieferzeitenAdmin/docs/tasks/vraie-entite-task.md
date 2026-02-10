# Task — vraie entité Task

Statut: `done`  
Owner: `LieferzeitenAdmin`

## Statuts autorisés
- `open`
- `in_progress`
- `done`
- `reopened`
- `cancelled`

## Transitions autorisées
- `open` -> `in_progress`, `done`, `cancelled`
- `in_progress` -> `done`, `cancelled`
- `done` -> `reopened`
- `reopened` -> `in_progress`, `done`, `cancelled`
- `cancelled` -> `reopened`

## Règles de fermeture
- **Manuelle**: via API `POST /api/_action/lieferzeiten/tasks/{taskId}/close` (transition vers `done`).
- **Automatique**: toute tâche fermée existante (`done`/`cancelled`) est réouverte automatiquement lors d'une nouvelle création pour le même couple `(positionId, triggerKey)`.

## Règles de non-duplication (position/trigger)
- Clé de déduplication: `(positionId, triggerKey)`.
- Une contrainte unique empêche la création de doublons DB pour ce couple.
- Lors d'une création:
  - si une tâche active existe déjà sur ce couple, son `id` est réutilisé;
  - si la tâche existante est fermée, elle est réouverte.

## Notifications initiateur
- Notification envoyée à l'initiateur sur clôture (`done` / `cancelled`).
- Notification envoyée à l'initiateur sur réouverture (`reopened`).

## Tests attendus
- Tests unitaires des transitions (valide/invalide, fermeture, réouverture, annulation).
- Tests d'intégration API des endpoints de transition (`assign`, `close`, `reopen`, `cancel`) + validation des ACL.
