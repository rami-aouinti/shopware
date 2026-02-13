# External Orders (Externe Bestellungen)

## Überblick
Das Plugin **External Orders** erweitert die Shopware-Administration um eine zentrale Übersicht für externe Bestellungen aus Marktplätzen. Es bietet Kanalauswahl, Filter und eine Detailansicht, um Bestellungen schnell zu finden und zu analysieren.

## Funktionsumfang
- **Bestellübersicht** mit Suchfeld, Statusanzeige und aggregierten Kennzahlen.
- **Kanalfilter** für verschiedene Marktplätze (z. B. B2B, eBay, Kaufland).
- **Detailansicht** inklusive Kunden-, Zahlungs-, Liefer- und Zusatzdaten.
- **Statushistorie** und Positionen je Bestellung.

## Anforderungen
- Shopware 6 (Platform)

## Installation
1. Plugin in das Verzeichnis `custom/plugins/ExternalOrders` legen.
2. Plugin in der Administration installieren und aktivieren.

## Update & Migration (CLI)
Wenn das Plugin aktualisiert wurde (z. B. neue Version aus dem Repository), können die Schritte per CLI so aussehen:

1. Plugin-Informationen neu einlesen:
   ```bash
   bin/console plugin:refresh
   ```
2. Plugin aktualisieren:
   ```bash
   bin/console plugin:update ExternalOrders
   ```
3. Datenbank-Migrationen ausführen (alle Plugins + Core):
   ```bash
   bin/console database:migrate --all
   ```
   Optional nur für dieses Plugin:
   ```bash
   bin/console database:migrate --identifier ExternalOrders
   ```

## Nutzung
Nach der Aktivierung erscheint in der Administration unter **Bestellungen** ein neuer Menüpunkt. Dort können externe Bestellungen eingesehen und nach Kanal oder Suchbegriff gefiltert werden.

### SAN6 Versandstrategie `filetransferurl`
- Bei der Strategie `filetransferurl` erzeugt das Plugin für jeden Export eine signierte Download-URL (`api.external-orders.export.file-transfer`).
- Diese URL ist explizit für Machine-to-Machine-Zugriffe ohne Admin-API-Login freigegeben (`auth_required=false`, keine ACL).
- Der Schutz erfolgt ausschließlich über den signierten Token in der URL (HMAC-Signatur + Ablaufzeit).
- Gültiger Token: Rückgabe `200` mit `Content-Type: application/xml` und Export-XML.
- Ungültiger oder abgelaufener Token: Rückgabe `404`.

## Validation en intégration / préprod (SAN6 `filetransferurl`)

Cette procédure permet de valider le comportement de l’URL signée en dehors du réseau interne Shopware.

### 1) Générer une URL signée via `TopmSan6OrderExportService`

1. Vérifier que la stratégie d’envoi est bien `filetransferurl` dans la configuration du plugin.
2. Déclencher un export depuis l’API admin (cela appelle `TopmSan6OrderExportService::exportOrder()` qui génère l’URL signée et la transmet à SAN6) :
   ```bash
   curl -sS -X POST "https://<shop-domain>/api/_action/external-orders/export/<orderId>" \
     -H "Authorization: Bearer <admin-api-token>" \
     -H "Content-Type: application/json"
   ```
3. Récupérer l’URL signée depuis la trace de la requête sortante vers SAN6 (proxy sortant/WAF/log applicatif SAN6).
   - Le format attendu est : `https://<shop-domain>/api/external-orders/topm-export/<token>`.

Alternative CLI (génération directe par `exportId`) :

```bash
bin/console external-orders:export:generate-signed-url <exportId> --validate-exists
```

Optionnel, TTL personnalisé pour test d’expiration :

```bash
bin/console external-orders:export:generate-signed-url <exportId> --expires-in=30 --validate-exists
```

### 2) Tester l’URL depuis l’extérieur (hors réseau interne Shopware)

Depuis une machine externe (ex: poste hors VPN, runner public, etc.) :

```bash
curl -i "https://<shop-domain>/api/external-orders/topm-export/<token>"
```

Résultat attendu :
- HTTP `200 OK`
- Header `Content-Type: application/xml; charset=utf-8`
- Body XML non vide (payload exporté)

### 3) Tester token invalide / expiré

#### Token invalide
```bash
curl -i "https://<shop-domain>/api/external-orders/topm-export/<token_invalide>"
```

Attendu : HTTP `404 Not Found`.

#### Token expiré
Le token signé expire après ~10 minutes (TTL 600 s). Rejouer exactement la même URL après expiration :

```bash
curl -i "https://<shop-domain>/api/external-orders/topm-export/<token_expire>"
```

Attendu : HTTP `404 Not Found`.

### 4) Vérifier reverse proxy et base URL (`core.basicInformation.shopwareUrl`)

Le service construit l’URL signée à partir de `core.basicInformation.shopwareUrl` (fallback: `APP_URL`). Cette valeur doit être publiquement routable.

Vérifier la valeur configurée :

```sql
SELECT configuration_value
FROM system_config
WHERE configuration_key = 'core.basicInformation.shopwareUrl';
```

Critères de conformité infra :
- domaine public résolvable (DNS externe) ;
- terminaison TLS valide sur le reverse proxy (`https`) ;
- routage vers Shopware pour `GET /api/external-orders/topm-export/{token}` ;
- conservation du host/proto (`X-Forwarded-Host`, `X-Forwarded-Proto`) cohérente avec l’URL publique ;
- pas de blocage WAF/CDN sur cette route de type machine-to-machine.

Vérification TLS depuis un hôte externe :

```bash
openssl s_client -connect <shop-domain>:443 -servername <shop-domain> </dev/null 2>/dev/null | openssl x509 -noout -subject -issuer -dates
```

### 5) Prérequis d’infrastructure (checklist)

- `core.basicInformation.shopwareUrl` pointe sur l’URL publique finale.
- Le reverse proxy publie `/api/external-orders/topm-export/*` sans authentification additionnelle.
- Les sorties réseau vers SAN6 sont autorisées (DNS/443).
- Horloge serveur synchronisée (NTP), sinon faux positifs “token expiré”.
- La valeur `APP_SECRET` est stable entre nœuds (si multi-instance), sinon validation HMAC incohérente.
- Monitoring conseillé : taux HTTP 404 sur la route signée + alertes sur erreurs SAN6.

## Runbooks
- Exploitation export SAN6: `docs/runbook-export-san6.md`

## Version
- **1.0.0**

## Entwickler
- **Name:** Mohamed Rami Aouinti
- **E-Mail:** mohamed.rami.aouinti@first-medical.de

## Support
Bei Fragen oder Support-Anfragen bitte die oben genannte E-Mail-Adresse verwenden.
