# Runbook d’exploitation — Export SAN6 (`ExternalOrders`)

## 1) Statuts d’export et leur signification

Les exports sont historisés dans `external_order_export` (une ligne par tentative). Le statut final d’une tentative dépend de la réponse SAN6 ou d’une erreur technique/configuration.

| Statut | Signification opérationnelle | Déclencheur | Suite automatique |
|---|---|---|---|
| `processing` | Tentative en cours (ligne créée, appel SAN6 non finalisé). | Création d’un export via API/controller ou retry. | Aucun (état transitoire). |
| `sent` | Export accepté par SAN6 (`response_code = 0`). | Réponse SAN6 valide avec code succès. | Fin du flux (pas de retry). |
| `failed` | SAN6 a répondu mais le résultat métier est en échec (`response_code != 0`). | Réponse SAN6 explicite en erreur. | Replanification immédiate vers `retry_scheduled`. |
| `retry_scheduled` | Échec temporaire, retry en attente (`next_retry_at`). | Échec transport/runtime ou réponse métier en erreur avec retries restants. | Pris en charge par la tâche `external_orders.export_retry`. |
| `failed_permanent` | Échec définitif (retries épuisés) ou configuration SAN6 invalide. | `attempts` max atteints ou exception de config (`InvalidArgumentException`). | Aucune reprise automatique. Intervention manuelle requise. |

### Politique de retry
- Nombre maximal de tentatives : **5** (`MAX_RETRIES`).
- Incrément du compteur : `attempts = attempts + 1` à chaque planification.
- Backoff progressif : `+5 min`, `+10 min`, `+15 min`, ... (formule `(attempts+1)*5`).
- Fenêtre d’exécution retry : uniquement `status = 'retry_scheduled'` et `next_retry_at <= NOW(3)`.
- Limite de lot par exécution scheduler : **20 retries**.

---

## 2) Contrôles opérationnels

## 2.1 Logs applicatifs

Filtrer les événements critiques de l’export SAN6 :

```bash
rg "TopM order export response received|TopM order export failed|TopM order export skipped: invalid SAN6 config" var/log -n
```

Interprétation rapide :
- `TopM order export response received.` : tentative terminée (succès/échec métier).
- `TopM order export failed.` : erreur technique (transport/runtime), retry attendu.
- `TopM order export skipped: invalid SAN6 config.` : erreur de configuration, export en `failed_permanent`.

## 2.2 Contrôles base de données (`external_order_export`)

Répartition par statut :

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

Backlog retry à traiter (retard) :

```sql
SELECT COUNT(*) AS overdue_retries
FROM external_order_export
WHERE status = 'retry_scheduled'
  AND next_retry_at IS NOT NULL
  AND next_retry_at <= NOW(3);
```

## 2.3 Scheduled task retry

Vérifier la tâche Shopware :

```sql
SELECT name, status, run_interval, last_execution_time, next_execution_time
FROM scheduled_task
WHERE name = 'external_orders.export_retry';
```

Exécution manuelle du scheduler (si absence de worker/cron actif) :

```bash
bin/console scheduled-task:run --no-wait
```

> Ne pas multiplier les lancements parallèles sans coordination d’exploitation.

---

## 3) Seuils d’alerte recommandés

## 3.1 Taux d’échec export (15 min glissantes)

- **Warning** : `failure_rate_pct > 5%`
- **Critical** : `failure_rate_pct > 15%`

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

## 3.2 Backlog retries

- **Warning** : `retry_backlog > 20`
- **Critical** : `retry_backlog > 100`

```sql
SELECT COUNT(*) AS retry_backlog
FROM external_order_export
WHERE status = 'retry_scheduled';
```

Alerte additionnelle sur retard de traitement retry :
- **Warning** si `overdue_retry_backlog > 0` pendant 10 min.

```sql
SELECT COUNT(*) AS overdue_retry_backlog
FROM external_order_export
WHERE status = 'retry_scheduled'
  AND next_retry_at IS NOT NULL
  AND next_retry_at <= NOW(3);
```

## 3.3 Erreurs de configuration SAN6

Déclencher une alerte immédiate si le log suivant apparaît :
- `TopM order export skipped: invalid SAN6 config.`

Clés minimales à contrôler :
- `ExternalOrders.config.externalOrdersSan6BaseUrl`
- `ExternalOrders.config.externalOrdersSan6Authentifizierung`
- `ExternalOrders.config.externalOrdersSan6WriteFunction`
- `ExternalOrders.config.externalOrdersSan6SendStrategy`

Vérification SQL rapide :

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

---

## 4) Procédure de reprise manuelle d’un export en échec

Pré-requis :
- accès Admin API,
- `orderId` concerné,
- cause racine identifiée/corrigée (config SAN6, réseau, timeout, etc.).

### Étapes

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
- configuration SAN6 invalide,
- indisponibilité réseau/SAN6,
- erreur fonctionnelle renvoyée par SAN6 (`response_code`, `response_message`).

3. **Relancer manuellement l’export (méthode standard)**

```bash
curl -sS -X POST "https://<shop-domain>/api/_action/external-orders/export/<orderId>" \
  -H "Authorization: Bearer <admin-api-token>" \
  -H "Content-Type: application/json"
```

4. **Valider la reprise**
- réponse HTTP 200 attendue (ou analyser message d’erreur),
- dernier statut attendu : `sent`,
- `response_code = 0` attendu.

```sql
SELECT status, response_code, response_message, attempts, correlation_id, created_at
FROM external_order_export
WHERE order_id = UNHEX(REPLACE('<orderId>', '-', ''))
ORDER BY created_at DESC
LIMIT 5;
```

5. **Alternative (si retry automatique souhaité plutôt qu’un export immédiat)**

Replacer la dernière ligne en file de retry (usage exceptionnel, DBA/ops confirmé) :

```sql
UPDATE external_order_export
SET status = 'retry_scheduled',
    next_retry_at = NOW(3),
    updated_at = NOW(3)
WHERE id = UNHEX(REPLACE('<exportId>', '-', ''));
```

Puis lancer le scheduler :

```bash
bin/console scheduled-task:run --no-wait
```

6. **Clôture d’incident**
- consigner `correlation_id`,
- cause racine,
- action de remédiation,
- délai de reprise.

---

## 5) Publication du runbook

Ce runbook est publié dans le plugin ici :
- `custom/plugins/ExternalOrders/docs/runbook-export-san6.md`

À chaque changement de logique d’export (statuts, retry, config SAN6, scheduler), mettre à jour ce document dans la même PR.
