# Task — Observabilité, alertes, SLA et runbook incident

Statut: `done`  
Owner: `LieferzeitenAdmin`  
Référence: `docs/observability-sla-runbook.md`

## Objectif
Définir un cadre opérationnel versionné pour:
- les métriques clés (sync, push status 7/8, notifications, tâches overdue),
- les alertes (erreurs répétées, dead-letter en hausse, latence API),
- les SLA cibles (listing et délai de sync),
- un runbook incident (diagnostic + actions).

## Périmètre livré
- Catalogue de métriques actionnables et exploitables en dashboard.
- Seuils d'alerting avec niveaux de sévérité et actions initiales.
- Cibles SLA explicites (P95/P99).
- Runbook opérationnel pour 3 familles d'incident.
- Branchement documenté sur les sources existantes du plugin:
  - `lieferzeiten_audit_log`,
  - `lieferzeiten_dead_letter`,
  - `lieferzeiten_notification_event`,
  - `lieferzeiten_task`.

## Critères d'acceptation
- [x] Document versionné présent: `docs/observability-sla-runbook.md`
- [x] Métriques couvrent sync, status 7/8, notifications, overdue tasks.
- [x] Alertes définies pour erreurs répétées, dead-letter, latence API.
- [x] SLA listing + délai sync définis avec objectifs mesurables.
- [x] Runbook incident avec étapes de diagnostic et actions.
- [x] Branchement explicite aux logs/audit/dead-letter existants.
