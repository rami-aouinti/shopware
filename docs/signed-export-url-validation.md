# Validation d’une URL signée d’export Shopware (depuis un environnement externe)

Ce runbook permet de valider qu’une URL signée d’export est **accessible publiquement**, qu’elle retourne le bon format pour un token valide, et qu’elle rejette les tokens invalides/expirés.

## 1) Générer une URL signée d’export

> Adapter la méthode à votre implémentation (export natif Shopware, plugin, endpoint custom).

### Option A — URL déjà fournie par Shopware (Admin/API)
1. Récupérer l’URL d’export signée depuis l’interface Admin ou via l’API interne.
2. Copier l’URL complète (incluant query string et token).

### Option B — Génération applicative (Symfony/Shopware)
Si votre endpoint est signé via le composant `UriSigner`, générer l’URL dans le code ou un script de maintenance avec la même clé applicative que la prod.

## 2) Tester depuis un environnement **externe** au cluster

Exécuter les tests depuis :
- un runner CI public,
- un poste opérateur hors VPC/VNet du cluster,
- ou une VM de supervision externe.

Définir les variables :

```bash
export SIGNED_URL='https://shop.example.com/path/export.xml?....'
```

### Vérifier status + content-type

```bash
curl -sS -D /tmp/export.headers -o /tmp/export.body "$SIGNED_URL"
awk 'BEGIN{IGNORECASE=1} /^HTTP\//{code=$2} /^content-type:/{ct=$0} END{print "HTTP=" code "\n" ct}' /tmp/export.headers
```

Résultat attendu :
- `HTTP=200`
- `Content-Type` contenant `application/xml` (ou `application/xml; charset=UTF-8`).

### Vérifier que le body est bien XML

```bash
head -n 5 /tmp/export.body
```

Le body doit commencer par une structure XML (`<?xml ...?>`) ou un flux XML valide.

## 3) Vérifier le rejet token expiré / invalide

## 3.1 Token invalide (signature modifiée)

Créer une URL altérée (exemple : altérer un caractère dans `signature`, `token` ou paramètre signé) :

```bash
export INVALID_URL="$(python3 - <<'PY'
import os, urllib.parse
url = os.environ['SIGNED_URL']
p = urllib.parse.urlsplit(url)
q = urllib.parse.parse_qsl(p.query, keep_blank_values=True)
out = []
changed = False
for k,v in q:
    if not changed and k.lower() in ('signature','sig','token','hash') and v:
        v = ('x' if v[0] != 'x' else 'y') + v[1:]
        changed = True
    out.append((k,v))
if not changed and out:
    k,v = out[0]
    out[0] = (k, v + 'x')
new_q = urllib.parse.urlencode(out)
print(urllib.parse.urlunsplit((p.scheme,p.netloc,p.path,new_q,p.fragment)))
PY
)"
curl -sS -o /dev/null -w "%{http_code}\n" "$INVALID_URL"
```

Résultat attendu : rejet (`401`, `403` ou `404` selon votre politique de sécurité).

## 3.2 Token expiré

Deux approches :
- Utiliser une URL à TTL court et attendre l’expiration.
- Générer une URL signée avec timestamp passé.

Puis tester :

```bash
curl -sS -o /dev/null -w "%{http_code}\n" "$EXPIRED_URL"
```

Résultat attendu : rejet (`401`, `403` ou `404`).

## 4) Vérifier `shopwareUrl` public, reverse proxy, TLS, firewall

## 4.1 `shopwareUrl` / URL publique
- Vérifier que l’URL publique utilisée pour signer est la même que celle exposée (FQDN final).
- Vérifier qu’aucune réécriture (host/scheme/path) du reverse proxy ne casse la signature.

## 4.2 Reverse proxy
- Conserver host + query string exacts.
- Propager correctement :
  - `X-Forwarded-Proto: https`
  - `X-Forwarded-Host`
  - `X-Forwarded-For`
- Éviter les normalisations agressives de query params (ordre, encodage, suppression).

## 4.3 TLS

```bash
curl -Iv "$SIGNED_URL"
```

Points de contrôle :
- certificat valide (chaîne complète),
- protocole TLS moderne (1.2+),
- aucun downgrade HTTP non voulu.

## 4.4 Firewall / sécurité réseau

Vérifier que le flux entrant autorise au minimum :
- source externe autorisée (IP egress du consommateur/exporter),
- destination `shopwareUrl` public,
- port `443/TCP`.

Si filtrage sortant côté consommateur : autoriser DNS + HTTPS vers le FQDN Shopware.

## 5) Prérequis réseau pour l’équipe Ops (checklist)

- DNS public résout le FQDN Shopware depuis l’extérieur.
- Port 443 ouvert jusqu’au reverse proxy/LB.
- Certificat TLS valide et chaîne intermédiaire complète.
- WAF/CDN n’altère pas les query params signés.
- Reverse proxy préserve host, scheme et query string.
- Time sync (NTP) correct sur les nœuds signant/validant les URLs (important pour expiration).
- Règles firewall documentées (source/destination/port/protocole).
- Monitoring : check HTTP 200 + `Content-Type: application/xml` sur URL valide, et rejet sur URL invalide.

## Exemple de test automatisable (CI/Ops)

```bash
#!/usr/bin/env bash
set -euo pipefail

SIGNED_URL="${SIGNED_URL:?SIGNED_URL is required}"

headers=$(mktemp)
body=$(mktemp)
trap 'rm -f "$headers" "$body"' EXIT

curl -sS -D "$headers" -o "$body" "$SIGNED_URL"
status=$(awk '/^HTTP\//{code=$2} END{print code}' "$headers")
ctype=$(awk 'BEGIN{IGNORECASE=1} /^content-type:/{print tolower($0)}' "$headers" | tail -1)

if [[ "$status" != "200" ]]; then
  echo "FAIL: expected HTTP 200, got $status"
  exit 1
fi

if [[ "$ctype" != *"application/xml"* ]]; then
  echo "FAIL: expected content-type application/xml, got: $ctype"
  exit 1
fi

echo "OK: valid token returns 200 + application/xml"
```
