# Runbook d’exploitation — Export SAN6 (`ExternalOrders`)

## 1) Statuts d’export (`sent`, `failed`, retries) et signification

Les tentatives d’export sont historisées dans `external_order_export` (une ligne par tentative).

| Statut | Signification opérationnelle | Déclencheur | Action automatique |
|---|---|---|---|
| `processing` | Tentative en cours. | Une tentative est créée avant l’appel SAN6. | État transitoire uniquement. |
| `sent` | Export accepté côté SAN6 (`response_code = 0`). | Réponse SAN6 valide avec code succès. | Aucune reprise. |
| `failed` | Réponse SAN6 reçue mais en échec métier (`response_code != 0`). | Erreur fonctionnelle renvoyée par SAN6. | Replanification immédiate via `retry_scheduled` si retries restants. |
| `retry_scheduled` | Échec temporaire en file d’attente de retry. | Erreur technique (timeout, transport, exception) ou `failed` replanifié. | Repris par la tâche planifiée `external_orders.export_retry`. |
| `failed_permanent` | Échec définitif. | Retries épuisés **ou** configuration SAN6 invalide. | Pas de reprise automatique (action manuelle requise). |

### Politique de retries
- Maximum : `MAX_RETRIES = 5`.
- Backoff : `+5 min`, `+10 min`, `+15 min`, ... (`(attempts + 1) * 5`).
- Fenêtre de prise en charge retry : `status = 'retry_scheduled'` et `next_retry_at <= NOW(3)`.
- Taille de lot retry par exécution : `LIMIT 20`.

---

## 2) Diagnostic (logs, table `external_order_export`, tâche retry)

### 2.1 Logs applicatifs

Filtre minimal (succès, erreurs techniques, config invalide) :

```bash
rg "TopM order export response received|TopM order export failed|TopM order export skipped: invalid SAN6 config" var/log -n
```

Interprétation :
- `TopM order export response received.` : fin de tentative (succès ou échec métier).
- `TopM order export failed.` : erreur technique/runtime ; retry attendu.
- `TopM order export skipped: invalid SAN6 config.` : export marqué `failed_permanent`.

### 2.2 Base de données `external_order_export`

Répartition des statuts :

```sql
SELECT status, COUNT(*) AS total
FROM external_order_export
GROUP BY status
ORDER BY total DESC;
```

Dernières tentatives :

```sql
SELECT
  LOWER(HEX(id)) AS export_id,
  LOWER(HEX(order_id)) AS order_id,
  status,
  attempts,
  response_code,
  response_message,
  last_error,
  next_retry_at,
  correlation_id,
  updated_at,
  created_at
FROM external_order_export
ORDER BY created_at DESC
LIMIT 50;
```

Backlog retries en retard :

```sql
SELECT COUNT(*) AS overdue_retries
FROM external_order_export
WHERE status = 'retry_scheduled'
  AND next_retry_at IS NOT NULL
  AND next_retry_at <= NOW(3);
```

### 2.3 Tâche planifiée de retry

Contrôle de la tâche Shopware :

```sql
SELECT name, status, run_interval, last_execution_time, next_execution_time
FROM scheduled_task
WHERE name = 'external_orders.export_retry';
```

Exécution manuelle du scheduler (si worker/cron indisponible) :

```bash
bin/console scheduled-task:run --no-wait
```

> Éviter les exécutions parallèles non coordonnées.

---

## 3) Métriques, seuils et alertes recommandées


### 3.0 KPI prioritaires à instrumenter

- `failed_exports_total` : nombre d'exports en statut `failed` sur 15 min glissantes.
  - **Warning** : `>= 10`
  - **Critical** : `>= 30`
- `retry_pending_total` : nombre d'exports en attente (`status = retry_scheduled`).
  - **Warning** : `> 20`
  - **Critical** : `> 100`
- `oldest_retry_age_minutes` : âge du plus ancien retry planifié.
  - **Warning** : `> 15 min`
  - **Critical** : `> 30 min`

```sql
SELECT
  SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_exports_total,
  SUM(CASE WHEN status = 'retry_scheduled' THEN 1 ELSE 0 END) AS retry_pending_total,
  ROUND(MAX(CASE
    WHEN status = 'retry_scheduled' AND next_retry_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, next_retry_at, NOW(3))
    ELSE NULL
  END), 2) AS oldest_retry_age_minutes
FROM external_order_export
WHERE created_at >= (NOW() - INTERVAL 15 MINUTE);
```

### 3.1 Échecs répétés d’exports

- **Warning** : `failure_rate_pct > 5%` sur 15 min glissantes.
- **Critical** : `failure_rate_pct > 15%` sur 15 min glissantes.

```sql
SELECT
  ROUND(
    100.0 * SUM(CASE WHEN status IN ('failed', 'retry_scheduled', 'failed_permanent') THEN 1 ELSE 0 END)
    / NULLIF(COUNT(*), 0),
    2
  ) AS failure_rate_pct,
  COUNT(*) AS total_exports
FROM external_order_export
WHERE created_at >= (NOW() - INTERVAL 15 MINUTE);
```

### 3.2 Backlog de retries

- **Warning** : `retry_backlog > 20`.
- **Critical** : `retry_backlog > 100`.
- Alerte complémentaire : `overdue_retry_backlog > 0` pendant 10 minutes.

```sql
SELECT COUNT(*) AS retry_backlog
FROM external_order_export
WHERE status = 'retry_scheduled';
```

```sql
SELECT COUNT(*) AS overdue_retry_backlog
FROM external_order_export
WHERE status = 'retry_scheduled'
  AND next_retry_at IS NOT NULL
  AND next_retry_at <= NOW(3);
```

### 3.3 Configuration SAN6 invalide

Alerte immédiate si présence du log :
- `TopM order export skipped: invalid SAN6 config.`

Clés minimales à surveiller :
- `ExternalOrders.config.externalOrdersSan6BaseUrl`
- `ExternalOrders.config.externalOrdersSan6Authentifizierung`
- `ExternalOrders.config.externalOrdersSan6WriteFunction`
- `ExternalOrders.config.externalOrdersSan6SendStrategy`

```sql
SELECT configuration_key
FROM system_config
WHERE configuration_key IN (
  'ExternalOrders.config.externalOrdersSan6BaseUrl',
  'ExternalOrders.config.externalOrdersSan6Authentifizierung',
  'ExternalOrders.config.externalOrdersSan6WriteFunction',
  'ExternalOrders.config.externalOrdersSan6SendStrategy'
);
```


### 3.4 Routage alertes mail/Slack

Implémentation minimale recommandée :
- Une alerte **mail** pour le support et l'astreinte Ops (Warning/Critical).
- Une alerte **Slack** dans `#incident-topm` avec niveau, métrique, valeur, seuil, lien dashboard et runbook.

Payload minimum d'alerte :
- `service=ExternalOrders`,
- `metric` (`failed_exports_total`, `retry_pending_total`, `oldest_retry_age_minutes`),
- `severity` (`warning`/`critical`),
- `value`, `threshold`,
- `timeWindow=15m`,
- `runbook=custom/plugins/ExternalOrders/docs/runbook-export-san6.md`.

---

## 4) Procédure de reprise manuelle d’un export en échec

Pré-requis : accès Admin API + `orderId` + cause racine identifiée/corrigée.

1. **Identifier la dernière tentative en échec**

```sql
SELECT
  LOWER(HEX(id)) AS export_id,
  status,
  attempts,
  response_code,
  response_message,
  last_error,
  next_retry_at,
  correlation_id,
  created_at
FROM external_order_export
WHERE order_id = UNHEX(REPLACE('<orderId>', '-', ''))
ORDER BY created_at DESC
LIMIT 5;
```

2. **Corriger la cause racine**
- configuration SAN6,
- connectivité/réseau,
- erreur métier SAN6 (`response_code`, `response_message`).

3. **Relancer l’export manuellement (méthode recommandée)**

```bash
curl -sS -X POST "https://<shop-domain>/api/_action/external-orders/export/<orderId>" \
  -H "Authorization: Bearer <admin-api-token>" \
  -H "Content-Type: application/json"
```

4. **Valider la reprise**
- HTTP 200 attendu (sinon analyser le message retourné).
- Dernier statut attendu : `sent` avec `response_code = 0`.

```sql
SELECT status, response_code, response_message, attempts, correlation_id, created_at
FROM external_order_export
WHERE order_id = UNHEX(REPLACE('<orderId>', '-', ''))
ORDER BY created_at DESC
LIMIT 5;
```

5. **Option alternative (forcer un retry planifié)**

> Option exceptionnelle (DBA/Ops confirmé).

```sql
UPDATE external_order_export
SET status = 'retry_scheduled',
    next_retry_at = NOW(3),
    updated_at = NOW(3)
WHERE id = UNHEX(REPLACE('<exportId>', '-', ''));
```

Puis :

```bash
bin/console scheduled-task:run --no-wait
```

6. **Clôture d’incident**
- tracer `correlation_id`,
- cause racine,
- action de remédiation,
- délai de reprise.

---

## 5) Support — lecture rapide de `external_order_export`

Checklist de lecture pour l'équipe support :
- Identifier la dernière ligne par `order_id` (tri `created_at DESC`).
- Vérifier `status` :
  - `sent` => export OK,
  - `retry_scheduled` => reprise automatique en attente,
  - `failed_permanent` => intervention manuelle obligatoire.
- Contrôler `attempts`, `last_error`, `response_code`, `response_message`.
- Utiliser `correlation_id` pour corréler DB/logs applicatifs.

Commande SQL support (vue synthétique) :

```sql
SELECT
  LOWER(HEX(id)) AS export_id,
  LOWER(HEX(order_id)) AS order_id,
  status,
  attempts,
  response_code,
  response_message,
  last_error,
  correlation_id,
  next_retry_at,
  updated_at
FROM external_order_export
ORDER BY updated_at DESC
LIMIT 100;
```

---

## 6) Escalade en cas de panne TopM prolongée

Définition « panne prolongée » :
- `retry_pending_total > 100` **ou** `oldest_retry_age_minutes > 30` pendant 15 min.

Procédure d'escalade :
1. **T+0 min (Support L1)** : qualifier l'incident, ouvrir ticket incident, notifier `#incident-topm`.
2. **T+10 min (Ops)** : vérifier connectivité SAN6, scheduler `external_orders.export_retry`, disponibilité API TopM.
3. **T+20 min (Dev on-call)** : activer mode dégradé (suspension des relances manuelles non critiques), confirmer stratégie de reprise.
4. **T+30 min (Management/PO)** : communication métier (impact commandes), ETA et décisions de contournement.
5. **Rétablissement** : relancer exports bloqués par lot, surveiller retour à `retry_pending_total < 20`.

Post-incident (obligatoire) :
- Rédiger un REX avec timeline, cause racine, actions correctives, et ajustement des seuils/alertes si nécessaire.

---

## 7) Publication du runbook

Le runbook est publié dans :
- `custom/plugins/ExternalOrders/docs/runbook-export-san6.md`

À chaque évolution de la logique d’export (statuts, retries, config SAN6, scheduler), mettre à jour ce document dans la même PR.

---

## 6) Référence bascule/rollback (checklist finale)

Pour les mises en production, utiliser la checklist dédiée :
- `custom/plugins/ExternalOrders/docs/checklist-bascule-san6.md`
