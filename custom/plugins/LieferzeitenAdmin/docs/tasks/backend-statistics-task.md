# Task — Backend-Statistiken auf Basis echter Persistenzdaten

Status: `open`
Owner: `LieferzeitenAdmin`

## Ziel
Sicherstellen, dass alle in der Administration angezeigten KPI aus den real persistierten Backend-Daten kommen und nicht aus Frontend-Mocks.

## Verbindlicher Umfang
- KPI-Aggregation ausschließlich aus den folgenden Tabellen:
  - `lieferzeiten_paket` (`paket`)
  - `lieferzeiten_position` (`position`)
  - `lieferzeiten_audit_log` (`audit`)
  - `lieferzeiten_task` (`task`)
- Explizites Verbot, Frontend-Mocks als KPI-Quelle zu verwenden.
- Standardisierte Zeitfenster für KPI (`7d`, `30d`, `90d` + begrenztes benutzerdefiniertes Fenster).
- Einheitliche Auswertung aller Zeitberechnungen in `Europe/Berlin`.

## Technische Anforderungen

### 1) Datenquelle und Aggregation
- KPI-Berechnung im Backend per SQL/DAL auf persistierten Tabellen.
- Frontend-Demodaten nur als visuelle Fallback-Anzeige, nie als Business-KPI.
- KPI-Dokumentation je Kennzahl (Formel, Quelltabelle, Filter, Zeitdimensionen).

### 2) Zeitfenster + Zeitzone
- Statistik-Endpunkte akzeptieren explizit `from`, `to` oder `period`.
- Zeitgrenzen und Tages-/Wochenaggregation werden in `Europe/Berlin` berechnet.
- API-Vertrag beschreibt DST-Verhalten (Sommer-/Winterzeit) und Inklusivität der Grenzen.

### 3) Versionierter Endpoint
- Versionierten Statistik-Endpoint bereitstellen (z. B. `/api/_action/lieferzeiten/v1/statistics`).
- Kompatibilität für bestehende Verbraucher per Übergangsplan (dokumentierte Deprecation).
- Antwortschema explizit versionieren (KPI-Felder, Dimensionen, Fenster-Metadaten).

### 4) Validierung über Referenzdatensätze
- Referenz-Fixtures für folgende Szenarien erstellen:
  - Normalfall,
  - zeitliche Randfälle (DST, Mitternacht, Grenzwerte),
  - fehlende/partielle Daten,
  - Multi-Source-Volumen (`paket`, `position`, `audit`, `task`).
- Erwartete KPI pro Fixture als versionierte Test-Oracle pflegen.

### 5) Reproduzierbare Aggregationstests
- Integrations-/funktionale Tests, die:
  - Referenz-Fixtures laden,
  - Backend-Aggregationen ausführen,
  - Ergebnisse mit erwarteten KPI vergleichen.
- Deterministisch durch Time-Freeze, erzwungene Zeitzone und festen Seed.
- Lokal und in CI wiederholbar ohne externe Abhängigkeiten.

## Abnahmekriterien
- [ ] Kein in der Admin angezeigter KPI hängt von Frontend-Mocks ab.
- [ ] Backend-KPI stammen aus persistierten `paket`-, `position`-, `audit`- und `task`-Daten.
- [ ] Zeitfenster und Zeitzone `Europe/Berlin` sind implementiert und getestet.
- [ ] Versionierter Statistik-Endpoint ist verfügbar und dokumentiert.
- [ ] Versionierte Referenzdatensätze + erwartete KPI sind validiert.
- [ ] Reproduzierbare Aggregationstests sind in CI grün.
