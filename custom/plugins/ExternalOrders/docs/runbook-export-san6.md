# Runbook d’exploitation — Export SAN6 (`ExternalOrders`)

## 1) Statuts d’export et actions opérateur

Les exports sont tracés dans la table `external_order_export` avec un cycle d’état orienté reprise automatique.

| Statut | Signification | Action automatique | Action opérateur |
|---|---|---|---|
| `processing` | Export en cours de traitement (ligne créée avant l’appel SAN6). | Aucune, état transitoire. | Surveiller si l’état reste bloqué (incident applicatif probable). |
| `sent` | SAN6 a répondu avec un code de succès (`response_code = 0`). | Fin du flux. | Aucun traitement, monitoring standard. |
| `failed` | SAN6 a répondu, mais le code retour indique un échec. | Planification d’un retry immédiate via `retry_scheduled`. | Vérifier `response_code`/`response_message` et la cause métier. |
| `retry_scheduled` | Retry planifié avec `next_retry_at` (backoff progressif). | Pris en charge par la tâche planifiée `external_orders.export_retry`. | Vérifier le backlog et la progression des retries. |
| `failed_permanent` | Plus de retry possible (max atteint) **ou** configuration SAN6 invalide. | Aucune reprise automatique. | Reprise manuelle après correction de la cause racine. |

### Détails retries
- Limite de retries : **5 tentatives max**.
- Backoff : `+5 min`, `+10 min`, `+15 min`, ... selon le compteur `attempts`.
- Le retry automatique ne traite que les lignes `retry_scheduled` dont `next_retry_at <= NOW(3)`.

---

## 2) Diagnostic rapide (logs, DB, scheduled task)

## 2.1 Logs applicatifs

Chercher les messages d’erreur/succès liés à l’export SAN6 :

```bash
rg "TopM order export|invalid SAN6 config|TopM san6" var/log -n
```

Signaux utiles dans les logs :
- `TopM order export response received.` (réponse SAN6 reçue)
- `TopM order export failed.` (exception transport/runtime)
- `TopM order export skipped: invalid SAN6 config.` (erreur de configuration)

## 2.2 État global en base (`external_order_export`)

Vue synthétique par statut :

```sql
SELECT status, COUNT(*) AS total
FROM external_order_export
GROUP BY status
ORDER BY total DESC;
```

Derniers exports (ordre chronologique inverse) :

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
  updated_at,
  created_at
FROM external_order_export
ORDER BY created_at DESC
LIMIT 50;
```

Backlog retry en retard (à traiter immédiatement) :

```sql
SELECT COUNT(*) AS overdue_retries
FROM external_order_export
WHERE status = 'retry_scheduled'
  AND next_retry_at IS NOT NULL
  AND next_retry_at <= NOW(3);
```

## 2.3 Tâche planifiée de retry

Lister la tâche :

```sql
SELECT name, status, run_interval, last_execution_time, next_execution_time
FROM scheduled_task
WHERE name = 'external_orders.export_retry';
```

Déclenchement manuel du scheduler (si nécessaire côté exploitation Shopware) :

```bash
bin/console scheduled-task:run --no-wait
```

> Si l’infra exécute déjà le scheduler via worker/cron, ne pas lancer en parallèle sans coordination.

---

## 3) Alertes minimales recommandées

## 3.1 Taux d’échec export

Alerte **warning** si taux d’échec > 5% sur 15 min, **critical** > 15%.

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

## 3.2 Backlog retry

Alerte **warning** si backlog `retry_scheduled` > 20, **critical** > 100.

```sql
SELECT COUNT(*) AS retry_backlog
FROM external_order_export
WHERE status = 'retry_scheduled';
```

Alerte dédiée si backlog en retard (`next_retry_at` dépassé) > 0 sur 10 min :

```sql
SELECT COUNT(*) AS overdue_retry_backlog
FROM external_order_export
WHERE status = 'retry_scheduled'
  AND next_retry_at IS NOT NULL
  AND next_retry_at <= NOW(3);
```

## 3.3 Erreurs de configuration SAN6

Déclencher une alerte immédiate si apparition des logs :
- `TopM order export skipped: invalid SAN6 config.`

Contrôles de config minimaux :
- `ExternalOrders.config.externalOrdersSan6BaseUrl`
- `ExternalOrders.config.externalOrdersSan6Authentifizierung`
- `ExternalOrders.config.externalOrdersSan6WriteFunction`
- `ExternalOrders.config.externalOrdersSan6SendStrategy`

Vérification SQL (présence des clés) :

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

Pré-requis : disposer d’un token Admin API et de l’`order_id` concerné.

1. **Identifier le dernier échec**
   - Récupérer le dernier enregistrement `failed_permanent` ou `retry_scheduled` pour la commande.
2. **Corriger la cause racine**
   - Config SAN6, connectivité réseau, timeout, erreur métier SAN6.
3. **Relancer l’export manuellement**
   - Endpoint Admin :

```bash
curl -sS -X POST "https://<shop-domain>/api/_action/external-orders/export/<orderId>" \
  -H "Authorization: Bearer <admin-api-token>" \
  -H "Content-Type: application/json"
```

4. **Valider le résultat**
   - Vérifier `status` retourné (`sent` attendu) et `responseCode`.
   - Contrôler en base que le dernier enregistrement est `sent`.

```sql
SELECT status, response_code, response_message, attempts, created_at
FROM external_order_export
WHERE order_id = UNHEX(REPLACE('<orderId>', '-', ''))
ORDER BY created_at DESC
LIMIT 5;
```

5. **Clôturer l’incident**
   - Documenter `correlation_id`, cause racine, action corrective, durée d’indisponibilité.

---

## 5) Règles d’escalade

- Escalade **N2/N3** si :
  - `failed_permanent` en hausse continue,
  - backlog retry non résorbé > 30 min,
  - erreurs de config SAN6 récurrentes.
- Escalade **infra** si :
  - indisponibilité SAN6,
  - erreurs TLS/DNS/proxy,
  - saturation workers empêchant `scheduled-task:run`.

