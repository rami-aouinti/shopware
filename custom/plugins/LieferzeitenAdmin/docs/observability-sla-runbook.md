# Observabilité, alerting, SLA et runbook — LieferzeitenAdmin

Version: `1.0.0`  
Dernière mise à jour: `2026-02-10`

## 1) Objectif

Définir un socle opérationnel concret pour suivre la santé du plugin, déclencher des alertes actionnables et accélérer la résolution d'incidents, en s'appuyant sur les données déjà persistées dans:
- `lieferzeiten_audit_log`,
- `lieferzeiten_dead_letter`,
- `lieferzeiten_notification_event`,
- `lieferzeiten_task`,
- `lieferzeiten_paket` / `lieferzeiten_position`.

## 2) Métriques à surveiller

### 2.1 Sync
- **Volume sync**: nombre d'événements `order_synced` dans `lieferzeiten_audit_log` (par 5 min / 1h / 24h).
- **Taux de succès sync**: ratio `integration_success` vs `integration_dead_letter` par `source_system`.
- **Sync skipped**: occurrences du log applicatif `Lieferzeiten sync skipped due to active lock.`.

### 2.2 Push status 7/8
- **Backlog status 7/8**: nombre de `lieferzeiten_paket` avec `status` in (`7`, `8`) et sans événement récent de progression.
- **Âge status 7/8**: temps moyen et P95 depuis dernière mise à jour (`last_changed_at`) des commandes en status 7/8.
- **Régression status**: cas où un ordre passe de `8` vers un statut inférieur (détecté via audit/correlationId).

### 2.3 Notifications
- **Événements en file**: nombre de `lieferzeiten_notification_event.status = queued`.
- **Âge de queue notifications**: P95 du délai `now - dispatched_at` pour les événements non consommés.
- **Taux de duplication évitée**: événements ignorés par idempotence (`event_key` déjà existant).

### 2.4 Tâches overdue
- **Overdue count**: nombre de tâches ouvertes (`status != closed`) avec `due_date < now`.
- **Overdue aging**: ancienneté moyenne/P95 des tâches overdue.
- **Flux de clôture**: ratio tâches clôturées / tâches créées par jour.

## 3) Alertes

### 3.1 Erreurs répétées (intégration)
- **Signal source**: `lieferzeiten_audit_log.action = integration_dead_letter` + logs applicatifs `Integration call failed.`.
- **Condition**: ≥ 5 erreurs sur 10 minutes pour un couple (`source_system`, `target_id`).
- **Sévérité**: `high`.
- **Action initiale**: activer runbook section 6.1.

### 3.2 Dead-letter en hausse
- **Signal source**: table `lieferzeiten_dead_letter`.
- **Condition**: pente > +30% sur 30 minutes (comparée à la fenêtre précédente) ou > 20 nouvelles entrées/15 min.
- **Sévérité**: `high`.
- **Action initiale**: activer runbook section 6.2.

### 3.3 Latence API
- **Signal source**: mesure applicative autour de `IntegrationReliabilityService::executeWithRetry()` + logs corrélés.
- **Condition**: P95 latence > 2.5s sur 15 minutes pour un système externe (`shopware`, `gambio`, `san6`, `mails`, transporteur).
- **Sévérité**: `medium` (puis `high` si > 5 min consécutives).
- **Action initiale**: activer runbook section 6.3.

## 4) SLA cibles

- **Temps de chargement listing** (`/api/_action/lieferzeiten/orders`):
  - P95 < **1.5s**,
  - P99 < **2.5s**.
- **Délai sync** (ordre disponible en vue admin après événement source):
  - P95 < **5 min**,
  - P99 < **10 min**.
- **Délai notification** (création d'événement à `queued`):
  - P95 < **60s**.
- **Overdue task ratio**:
  - < **10%** des tâches ouvertes.

## 5) Branchement aux données déjà présentes

### 5.1 Audit log (`lieferzeiten_audit_log`)
Utiliser les actions existantes:
- `integration_success`,
- `integration_dead_letter`,
- `order_synced`,
- `notification_queued`,
- événements métier (mise à jour dates/commentaires, etc.).

Usage:
- séries temporelles de succès/échec,
- suivi des corrélations (`correlation_id`) pour un incident donné,
- analyse de parcours d'une commande (`externalOrderId` dans payload).

### 5.2 Dead-letter (`lieferzeiten_dead_letter`)
Utiliser:
- `system`, `operation`, `attempts`, `error_message`, `correlation_id`, `created_at`.

Usage:
- alertes de volumétrie et de pente,
- top erreurs par système/opération,
- file de reprise manuelle.

### 5.3 Notifications (`lieferzeiten_notification_event`)
Utiliser:
- `event_key`, `trigger_key`, `channel`, `status`, `dispatched_at`, `source_system`.

Usage:
- backlog de queue,
- latence de dispatch,
- audit de non-réémission (idempotence).

### 5.4 Tasks (`lieferzeiten_task`)
Utiliser:
- `status`, `due_date`, `created_at`, `closed_at`, `payload`.

Usage:
- overdue et aging,
- capacité de traitement,
- détection des goulots par type de tâche.

## 6) Runbook incident

### 6.1 Incident "erreurs répétées"
1. **Qualifier** la fenêtre d'incident (début, système, opération).
2. **Corréler** via `correlation_id` dans audit/dead-letter/logs.
3. **Vérifier** connectivité/API externe et validité payload minimal.
4. **Décider**: retry manuel ciblé ou bascule fallback métier.
5. **Documenter** cause racine + action préventive.

### 6.2 Incident "dead-letter en hausse"
1. **Lister** top `system + operation` sur 30 min.
2. **Classer** erreurs (auth, timeout, schéma, données manquantes).
3. **Isoler** les payloads invalides (sans bloquer le reste du flux).
4. **Relancer** progressivement (batch réduit) après correctif.
5. **Surveiller** retour à la normale (pente dead-letter < 0).

### 6.3 Incident "latence API"
1. **Identifier** le système externe impacté (P95/P99).
2. **Vérifier** saturation locale (CPU/DB lock/queue) vs lenteur fournisseur.
3. **Adapter** temporairement retries/timeouts/fenêtres de sync.
4. **Limiter** les appels non critiques si nécessaire.
5. **Confirmer** normalisation latence avant clôture incident.

## 7) Requêtes SQL de base (dashboard / debug)

```sql
-- 1) Succès vs dead-letter sur 24h
SELECT action, source_system, COUNT(*) AS total
FROM lieferzeiten_audit_log
WHERE created_at >= NOW() - INTERVAL 24 HOUR
  AND action IN ('integration_success', 'integration_dead_letter')
GROUP BY action, source_system;

-- 2) Dead-letter par système/opération sur 1h
SELECT system, operation, COUNT(*) AS total
FROM lieferzeiten_dead_letter
WHERE created_at >= NOW() - INTERVAL 1 HOUR
GROUP BY system, operation
ORDER BY total DESC;

-- 3) Notifications en queue et âgées
SELECT status,
       COUNT(*) AS total,
       MAX(TIMESTAMPDIFF(MINUTE, dispatched_at, NOW())) AS max_queue_age_min
FROM lieferzeiten_notification_event
WHERE status = 'queued'
GROUP BY status;

-- 4) Tâches overdue ouvertes
SELECT COUNT(*) AS overdue_open_tasks
FROM lieferzeiten_task
WHERE (closed_at IS NULL)
  AND due_date IS NOT NULL
  AND due_date < NOW();
```
