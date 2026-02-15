# KPI & Activité — Lieferzeiten Statistics

## KPI fonctionnels inclus

| KPI | Définition | Sources | Filtres domaine/canal |
|---|---|---|---|
| `openOrders` | Nombre de paquets non clôturés (positions ouvertes > 0) sur la période. | `lieferzeiten_paket`, `lieferzeiten_position` | `domain`/`channel` via `source_system` |
| `overdueShipping` | Tâches d’expédition en retard selon seuil PDMS (jours ouvrés/cutoff). | `lieferzeiten_paket`, `lieferzeiten_position`, settings PDMS | `domain`/`channel` via `source_system` |
| `overdueDelivery` | Livraisons en retard selon seuil PDMS. | `lieferzeiten_paket`, `lieferzeiten_position`, settings PDMS | `domain`/`channel` via `source_system` |
| `channels[]` | Répartition des volumes de paquets par source. | `lieferzeiten_paket` | `domain`/`channel` cohérents |
| `timeline[]` | Compte journalier de tous les événements consolidés. | Voir matrice activités ci-dessous | `domain`/`channel` appliqué sur `sourceSystem` |

## Schéma unifié des activités

Chaque activité retournée dans `activitiesData` suit le schéma:

- `eventType`: type métier de l’événement,
- `status`: statut/état compact,
- `message`: libellé opérationnel,
- `eventAt`: horodatage effectif de l’événement,
- `sourceSystem`: système source normalisé (utilisé aussi pour les filtres),
- plus: `id`, `orderNumber`, `domain`, `promisedAt`.

## Matrice entités / événements inclus

| Source | `eventType` | `status` | `message` | `eventAt` |
|---|---|---|---|---|
| `lieferzeiten_audit_log` | `audit` | `action` | `action` | `created_at` |
| `lieferzeiten_notification_event` | `notification_event` | `status` (`queued`, `sent`, `failed`, …) | `trigger_key` | `dispatched_at` / `updated_at` / `created_at` |
| `lieferzeiten_task` (création) | `task` | `status` | `payload.taskType` / `task_created` | `created_at` |
| `lieferzeiten_task` (transition) | `task_transition` | `status` | `transition:{status}` | `updated_at` (si différent de `created_at`) |
| `lieferzeiten_dead_letter` | `dead_letter` | `attempts` | `operation` | `created_at` |
| `lieferzeiten_sendenummer_history` | `tracking_history` | `sendenummer` / `updated` | `tracking_number_updated` | `last_changed_at` / `created_at` |
| `lieferzeiten_neuer_liefertermin_history` | `delivery_date_history` | date cible (`liefertermin_to`) | `delivery_date_range_updated` | `last_changed_at` / `created_at` |
| `lieferzeiten_position` | `position` | `status` | `position_updated` | `last_changed_at` |
| `lieferzeiten_paket` | `paket` | `status` | `paket_updated` | `last_changed_at` |

## Décision d’inclusion statistique

- Inclus en **timeline**: toutes les sources ci-dessus (vision volumétrique globale).
- Inclus en **activitiesData**: mêmes sources, triées par `eventAt` décroissant.
- Les événements sans `orderNumber` restent visibles (ex: certains dead-letter/audit) pour l’observabilité.
