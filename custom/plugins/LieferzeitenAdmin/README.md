# LieferzeitenAdmin

## 1. Übersicht

### Ziel des Plugins
`LieferzeitenAdmin` ergänzt Shopware um ein Administrationsmodul zur Steuerung von Lieferzeiten und zugehörigen Abläufen (Import, Carrier-Tracking, Aufgabenmanagement, Benachrichtigungen und manuelle Aktionen) mit Fokus auf operative Transparenz.
Zusätzlich stellt das Plugin Admin-API-Endpunkte unter `/api/_action/lieferzeiten/...` bereit.

### Funktionsumfang
- **Administration**: Modul `lieferzeiten` unter **Bestellungen** und Modul `lieferzeiten-settings` unter **Einstellungen**.
- **Import**: Datensynchronisierung per On-Demand-Sync und geplanten Tasks.
- **Tracking**: Historie je Carrier/Trackingnummer.
- **Status**: Auftragslisten und aggregierte Statistiken.
- **Benachrichtigungen**: Vorkasse-Erinnerungen, Overdue-Shipping-Date, Notification-Dispatch.
- **Aufgaben**: Listen, Zuweisung, Abschließen/Wiederöffnen/Abbrechen von Business-Tasks.

---

## 2. Voraussetzungen

### Versionen
- **Shopware**: kompatibel mit diesem Repository in `6.7.6.2` (siehe Root-`composer.json`).
- **PHP**: eine mit Shopware 6.7 kompatible Version verwenden (aktuelles Umfeld: `PHP 8.5.3-dev`).

### Benötigte Dienste
- **MySQL-Datenbank** über `DATABASE_URL` erreichbar (in diesem Repo: `mysql://root:root@localhost/shopware`).
- **Queue/Messenger** für Scheduled Tasks aktiv (`MESSENGER_TRANSPORT_DSN` abhängig von der Infrastruktur oder Doctrine-Transport gemäß Konfiguration).
- **Optionale externe APIs** (falls genutzt) über Plugin-Konfiguration:
  - `shopwareApiUrl`, `shopwareStatusPushApiUrl`
  - `gambioApiUrl`, `gambioStatusPushApiUrl`
  - `san6ApiUrl`
  - Carrier-Tracking (DHL/GLS) über Backend-Services.

---

## 3. Installation / Aktivierung

Aus dem Projekt-Root (`/workspace/shopware`):

```bash
# 0) Projektabhängigkeiten (falls erforderlich)
composer install

# 1) Plugins aktualisieren
bin/console plugin:refresh

# 2) Plugin installieren
bin/console plugin:install --activate LieferzeitenAdmin

# 3) (optional) bei bestehender Installation und Code-Update
bin/console plugin:update LieferzeitenAdmin

# 4) Cache leeren (nach Aktivierung/Update empfohlen)
bin/console cache:clear
```

---

## 4. Datenbank-Migrationen

### Migrationen ausführen
```bash
# Globale Migrationen
bin/console database:migrate --all

# Plugin-spezifische Alternative (falls von der Version unterstützt)
bin/console database:migrate --identifier=LieferzeitenAdmin
```

### Plugin-Tabellen prüfen
Beispiel-SQL:

```sql
SHOW TABLES LIKE 'lieferzeiten_%';
```

Wichtige Tabellen (nicht abschließend):
- `lieferzeiten_paket`
- `lieferzeiten_position`
- `lieferzeiten_task`
- `lieferzeiten_notification_event`
- `lieferzeiten_notification_template`
- `lieferzeiten_audit_log`
- `lieferzeiten_dead_letter`

### Minimaler Rollback
Die Shopware-Migrationen des Plugins bieten kein fein granulars Rollback. In der Praxis:
1. DB-Backup wiederherstellen, **oder**
2. Plugin mit Datenlöschung deinstallieren:

```bash
bin/console plugin:uninstall --clearUserData LieferzeitenAdmin
```

---

## 5. Administration-Build und Ausführung

### Administration bauen
```bash
# Globaler Build der Administration im Projekt
bin/build-administration.sh
```

### Watch-Modus (Entwicklung)
Abhängig vom lokalen Shopware-Frontend-Setup:
```bash
# Häufiges Beispiel
bin/watch-administration.sh
```

### Sichtbarkeit im Admin prüfen
Nach Build + Admin-Login:
- Menü **Bestellungen**: Eintrag **Lieferzeiten** (`lieferzeiten.index`).
- Menü **Einstellungen**: Eintrag **Lieferzeiten Settings** (`lieferzeiten.settings.channelSettings` inkl. Unterseiten für Regeln/Benachrichtigungen).

---

## 6. Wichtige Runtime-Befehle

### On-Demand-Sync
```bash
curl -X POST "${APP_URL}/api/_action/lieferzeiten/sync" \
  -H "Authorization: Bearer <ADMIN_API_TOKEN>" \
  -H "Content-Type: application/json"
```

### Scheduled Tasks
```bash
# Geplante Tasks auflisten
bin/console scheduled-task:list

# Scheduler ausführen
bin/console scheduled-task:run

# Messenger-Queue konsumieren
bin/console messenger:consume -vv
```

Erwartete Plugin-Tasks:
- `lieferzeiten_admin.import_task`
- `lieferzeiten_admin.vorkasse_payment_reminder_task`
- `lieferzeiten_admin.shipping_date_overdue_task`
- `lieferzeiten_admin.notification_dispatch_task`

### Debug / nützliche Logs
```bash
# App-Logs
ls -lah var/log/
tail -f var/log/*.log

# Queue-/Worker-Status (abhängig vom Transport)
bin/console messenger:stats
```

---

## 7. Plugin-API

> Basis-Pfad: `/api/_action/lieferzeiten`
> ACL über Plugin-Rollen: `lieferzeiten.viewer`, `lieferzeiten.editor`.

### 7.1 Orders
- **GET** `/orders`
- **ACL**: `lieferzeiten.viewer`
- **Wichtige Parameter**: `page`, `limit`, `sort`, `order`, `bestellnummer`, `san6`, `status`, Datumsfilter (`orderDateFrom`, `orderDateTo` usw.).

Beispiel:
```bash
curl -G "${APP_URL}/api/_action/lieferzeiten/orders" \
  -H "Authorization: Bearer <ADMIN_API_TOKEN>" \
  --data-urlencode "page=1" \
  --data-urlencode "limit=25" \
  --data-urlencode "status=open"
```

Beispielantwort (vereinfacht):
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
- **Body**: keiner

Antwort:
```json
{ "status": "ok" }
```

### 7.3 Tracking
- **GET** `/tracking/{carrier}/{trackingNumber}`
- **ACL**: `lieferzeiten.viewer`
- **Typische Fehlercodes**: `400`, `429`, `502`, `504` je nach `errorCode`.

Beispiel:
```bash
curl "${APP_URL}/api/_action/lieferzeiten/tracking/dhl/00340434161234567890" \
  -H "Authorization: Bearer <ADMIN_API_TOKEN>"
```

### 7.4 Write-Endpunkte (aktuell)
- **POST** `/position/{positionId}/liefertermin-lieferant`
- **POST** `/position/{positionId}/neuer-liefertermin`
- **POST** `/position/{positionId}/comment`
- **POST** `/position/{positionId}/additional-delivery-request`

ACL: `lieferzeiten.editor`.
Die Datums-/Kommentar-Endpunkte verlangen `updatedAt` für optimistische Parallelitätskontrolle (bei Konflikt `409 CONCURRENT_MODIFICATION`).

Beispiel-Body (`neuer-liefertermin`):
```json
{
  "from": "2026-02-15",
  "to": "2026-02-17",
  "updatedAt": "2026-02-10T10:15:22+00:00"
}
```

### 7.5 Task-Endpunkte
- **GET** `/tasks`
- **POST** `/tasks/{taskId}/assign`
- **POST** `/tasks/{taskId}/close`
- **POST** `/tasks/{taskId}/reopen`
- **POST** `/tasks/{taskId}/cancel`

ACL: Lesen `viewer`, Mutation `editor`.


### 7.6 Statistics
- **GET** `/statistics`
- **ACL**: `lieferzeiten.viewer`
- **Query**: `period` (`7|30|90`), `domain`, `channel`

Antwort enthält:
- `metrics` (`openOrders`, `overdueShipping`, `overdueDelivery`)
- `channels[]`
- `timeline[]`
- `activitiesData[]` mit einheitlichem Schema: `eventType`, `status`, `message`, `eventAt`, `sourceSystem` (+ `orderNumber`, `domain`, `promisedAt`).

Die funktionale KPI-/Aktivitäts-Matrix ist in `docs/statistics-kpi-catalog.md` dokumentiert.

---

## 8. Demo-Daten

### Dateninjektion aus dem Backend starten
In der Administration:
1. **Einstellungen → Lieferzeiten Settings → Channel Settings**.
2. Auf **DemoDaten** klicken.

### Reset-Option
- Button **Reset DemoDaten**, um Daten zurückzusetzen und neu zu erzeugen.
- Zugehörige API: `POST /api/_action/lieferzeiten/demo-data` mit Body `{ "reset": true|false }`.

### Erwartetes UI-Ergebnis
Nach der Injektion:
- Daten in Lieferzeiten-Listen sichtbar (Orders/Statistiken je nach Datensatz).
- Erfolgsmeldung mit Anzahl der erzeugten Datensätze.

---

## 9. Troubleshooting

### Häufige Fehler
- **ACL / 403**: Admin-Benutzer ohne Rollen `lieferzeiten.viewer`/`lieferzeiten.editor`.
- **Externer Endpunkt nicht konfiguriert**: fehlende URLs/Tokens in der Plugin-Konfiguration.
- **Admin-Build fehlgeschlagen**: Assets nach Plugin-Update nicht neu gebaut.
- **Queue wird nicht konsumiert**: Scheduled Task markiert, aber Messenger-Worker läuft nicht.

### Schnelle Checks
```bash
# Plugin installiert / aktiv
bin/console plugin:list | rg LieferzeitenAdmin

# Geplante Tasks prüfen
bin/console scheduled-task:list | rg lieferzeiten_admin

# Applikationslogs prüfen
tail -n 200 var/log/*.log

# Prüfen, ob Admin-Code vorhanden ist
test -f custom/plugins/LieferzeitenAdmin/src/Resources/app/administration/src/main.js && echo "admin sources OK"
```

---

## 10. Quickstart (< 10 Min)

> Von der Projektwurzel ausführen.

```bash
# 0) Abhängigkeiten vorbereiten
composer install

# 1) Plugin installieren/aktivieren
bin/console plugin:refresh
bin/console plugin:install --activate LieferzeitenAdmin

# 2) Migrationen + Cache
bin/console database:migrate --all
bin/console cache:clear

# 3) Admin-Build
bin/build-administration.sh

# 4) Tasks prüfen und Scheduler/Worker starten
bin/console scheduled-task:list | rg lieferzeiten_admin
bin/console scheduled-task:run
bin/console messenger:consume -vv
```

Danach im Shopware-Admin:
- **Bestellungen → Lieferzeiten** für die Auftragsübersicht.
- **Einstellungen → Lieferzeiten Settings** für Konfiguration, Task-Regeln, Notification-Toggles und DemoDaten-Injektion.
