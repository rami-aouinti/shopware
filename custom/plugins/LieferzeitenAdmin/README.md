# LieferzeitenAdmin

## 1. Présentation

### Objectif du plugin
`LieferzeitenAdmin` ajoute un module d’administration Shopware pour piloter les délais de livraison et les opérations associées (import, suivi transporteurs, gestion de tâches, notifications et actions manuelles), avec un focus sur la visibilité opérationnelle des commandes.  
Le plugin expose aussi des endpoints Admin API (`/api/_action/lieferzeiten/...`) pour orchestrer ces flux.

### Périmètre fonctionnel
- **Administration** : module `lieferzeiten` sous **Orders** + module `lieferzeiten-settings` sous **Settings**.
- **Import** : synchronisation de données via sync on-demand et tâche planifiée.
- **Tracking** : consultation historique tracking par transporteur/numéro.
- **Statuts** : consultation liste commandes et statistiques agrégées.
- **Notifications** : rappels prépaiement, overdue shipping date, dispatch des notifications.
- **Tâches** : listage, assignation, clôture/réouverture/annulation des tâches métier.

---

## 2. Prérequis

### Versions
- **Shopware** : compatible avec ce repo en `6.7.6.2` (cf. `composer.json` racine).
- **PHP** : utiliser une version compatible avec Shopware 6.7 (l’environnement courant exécute `PHP 8.5.3-dev`).

### Services attendus
- **Base de données MySQL** accessible via `DATABASE_URL` (dans ce repo: `mysql://root:root@localhost/shopware`).
- **Queue/Messenger** active pour les scheduled tasks (`MESSENGER_TRANSPORT_DSN` selon votre infra, ou transport Doctrine selon configuration).
- **APIs externes optionnelles** (si utilisées) configurables dans le plugin:
  - `shopwareApiUrl`, `shopwareStatusPushApiUrl`
  - `gambioApiUrl`, `gambioStatusPushApiUrl`
  - `san6ApiUrl`
  - tracking transporteurs (DHL/GLS) côté services backend.

---

## 3. Installation / Activation plugin

Depuis la racine du projet (`/workspace/shopware`):

```bash
# 0) Dépendances projet (si nécessaire)
composer install

# 1) Refresh plugins
bin/console plugin:refresh

# 2) Installation du plugin
bin/console plugin:install --activate LieferzeitenAdmin

# 3) (optionnel) si déjà installé et mise à jour de code
bin/console plugin:update LieferzeitenAdmin

# 4) Clear cache (recommandé après activation/update)
bin/console cache:clear
```

---

## 4. Migrations base de données

### Exécuter les migrations
```bash
# Migrations globales
bin/console database:migrate --all

# Alternative ciblée plugin (si supportée par votre version)
bin/console database:migrate --identifier=LieferzeitenAdmin
```

### Vérifier les tables du plugin
Exemple SQL (via client MySQL):

```sql
SHOW TABLES LIKE 'lieferzeiten_%';
```

Tables principales attendues (non exhaustif):
- `lieferzeiten_paket`
- `lieferzeiten_position`
- `lieferzeiten_task`
- `lieferzeiten_notification_event`
- `lieferzeiten_notification_template`
- `lieferzeiten_audit_log`
- `lieferzeiten_dead_letter`

### Rollback minimal
Les migrations Shopware du plugin ne fournissent pas de rollback fin-grain. En pratique:
1. restaurer un backup DB, **ou**
2. désinstaller le plugin avec suppression des données:

```bash
bin/console plugin:uninstall --clearUserData LieferzeitenAdmin
```

---

## 5. Build et exécution Administration

### Build administration
```bash
# Build administration global projet
bin/build-administration.sh
```

### Mode watch (développement)
Selon votre setup front local Shopware:
```bash
# Exemple fréquent
bin/watch-administration.sh
```

### Où vérifier la visibilité du module admin
Après build + login admin:
- Menu **Orders**: entrée **Lieferzeiten** (`lieferzeiten.index`).
- Menu **Settings**: entrée **Lieferzeiten Settings** (`lieferzeiten.settings.channelSettings`, puis sous-pages rules/notifications).

---

## 6. Commandes runtime importantes

### Sync on-demand
L’action sync on-demand est exposée par API admin:

```bash
curl -X POST "${APP_URL}/api/_action/lieferzeiten/sync" \
  -H "Authorization: Bearer <ADMIN_API_TOKEN>" \
  -H "Content-Type: application/json"
```

### Scheduled tasks
```bash
# Lister les tâches planifiées
bin/console scheduled-task:list

# Exécuter le scheduler
bin/console scheduled-task:run

# Consommer la queue messenger
bin/console messenger:consume -vv
```

Tâches plugin attendues:
- `lieferzeiten_admin.import_task`
- `lieferzeiten_admin.vorkasse_payment_reminder_task`
- `lieferzeiten_admin.shipping_date_overdue_task`
- `lieferzeiten_admin.notification_dispatch_task`

### Debug / logs utiles
```bash
# Logs app
ls -lah var/log/
tail -f var/log/*.log

# État queues/worker (selon transport)
bin/console messenger:stats
```

---

## 7. API du plugin

> Base path: `/api/_action/lieferzeiten`  
> ACL via rôles plugin: `lieferzeiten.viewer`, `lieferzeiten.editor`.

### 7.1 Orders
- **GET** `/orders`
- **ACL**: `lieferzeiten.viewer`
- **Paramètres principaux**: `page`, `limit`, `sort`, `order`, `bestellnummer`, `san6`, `status`, filtres dates (`orderDateFrom`, `orderDateTo`, etc.).

Exemple:
```bash
curl -G "${APP_URL}/api/_action/lieferzeiten/orders" \
  -H "Authorization: Bearer <ADMIN_API_TOKEN>" \
  --data-urlencode "page=1" \
  --data-urlencode "limit=25" \
  --data-urlencode "status=open"
```

Réponse (exemple simplifié):
```json
{
  "data": [
    {
      "bestellnummer": "100045",
      "status": "offen"
    }
  ],
  "total": 1
}
```

### 7.2 Sync
- **POST** `/sync`
- **ACL**: `lieferzeiten.editor`
- **Body**: none

Réponse:
```json
{ "status": "ok" }
```

### 7.3 Tracking
- **GET** `/tracking/{carrier}/{trackingNumber}`
- **ACL**: `lieferzeiten.viewer`
- **Codes d’erreur typiques**: `400`, `429`, `502`, `504` selon `errorCode`.

Exemple:
```bash
curl "${APP_URL}/api/_action/lieferzeiten/tracking/dhl/00340434161234567890" \
  -H "Authorization: Bearer <ADMIN_API_TOKEN>"
```

### 7.4 Endpoints write (actuels)
- **POST** `/position/{positionId}/liefertermin-lieferant`
- **POST** `/position/{positionId}/neuer-liefertermin`
- **POST** `/position/{positionId}/comment`
- **POST** `/position/{positionId}/additional-delivery-request`

ACL: `lieferzeiten.editor`.  
Les endpoints de date/comment exigent `updatedAt` pour la concurrence optimiste (retour `409 CONCURRENT_MODIFICATION` en cas de conflit).

Exemple body (`neuer-liefertermin`):
```json
{
  "from": "2026-02-15",
  "to": "2026-02-17",
  "updatedAt": "2026-02-10T10:15:22+00:00"
}
```

### 7.5 Endpoints tasks
- **GET** `/tasks`
- **POST** `/tasks/{taskId}/assign`
- **POST** `/tasks/{taskId}/close`
- **POST** `/tasks/{taskId}/reopen`
- **POST** `/tasks/{taskId}/cancel`

ACL lecture: `viewer`, mutation: `editor`.

---

## 8. DemoDaten

### Lancer l’injection depuis le backend
Dans l’Administration:
1. **Settings → Lieferzeiten Settings → Channel Settings**.
2. Cliquer sur **DemoDaten**.

### Option reset
- Bouton **Reset DemoDaten** pour réinitialiser puis regénérer.
- API sous-jacente: `POST /api/_action/lieferzeiten/demo-data` avec body `{ "reset": true|false }`.

### Résultat attendu UI
Après injection:
- Données visibles dans listings Lieferzeiten (orders/statistics selon jeux injectés).
- Message de notification de succès avec nombre d’enregistrements créés.

---

## 9. Troubleshooting

### Erreurs fréquentes
- **ACL / 403**: utilisateur admin sans rôles `lieferzeiten.viewer`/`lieferzeiten.editor`.
- **Endpoint externe non configuré**: URLs/tokens manquants dans config plugin.
- **Build admin KO**: assets non rebuild après mise à jour plugin.
- **Queue non consommée**: scheduled task marquée mais worker messenger arrêté.

### Checks rapides
```bash
# Plugin installé / actif
bin/console plugin:list | rg LieferzeitenAdmin

# Vérifier tâches planifiées
bin/console scheduled-task:list | rg lieferzeiten_admin

# Vérifier logs applicatifs
tail -n 200 var/log/*.log

# Vérifier que le code admin est présent
test -f custom/plugins/LieferzeitenAdmin/src/Resources/app/administration/src/main.js && echo "admin sources OK"
```

---

## 10. Quickstart (< 10 min)

> Copiez-collez depuis la racine projet.

```bash
# 0) Préparer dépendances
composer install

# 1) Installer/activer le plugin
bin/console plugin:refresh
bin/console plugin:install --activate LieferzeitenAdmin

# 2) Appliquer migrations + cache
bin/console database:migrate --all
bin/console cache:clear

# 3) Build admin
bin/build-administration.sh

# 4) Vérifier tâches et lancer scheduler/worker
bin/console scheduled-task:list | rg lieferzeiten_admin
bin/console scheduled-task:run
bin/console messenger:consume -vv
```

Ensuite, ouvrir l’admin Shopware:
- **Orders → Lieferzeiten** pour l’overview commandes.
- **Settings → Lieferzeiten Settings** pour config, règles de tâches, toggles notifications, et injection DemoDaten.
