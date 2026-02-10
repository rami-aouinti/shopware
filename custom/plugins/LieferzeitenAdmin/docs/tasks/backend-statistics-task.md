# Task — Statistiques backend

Statut: `todo`  
Owner: `LieferzeitenAdmin`  
Référence: `docs/observability-sla-runbook.md`

## Objectif
Fiabiliser les KPI d'administration en imposant une agrégation backend exclusivement basée sur des données persistées, traçables et testées.

## Périmètre imposé
- Agrégation KPI depuis les tables persistées suivantes (et uniquement celles-ci) :
  - `lieferzeiten_paket` (`paket`),
  - `lieferzeiten_position` (`position`),
  - `lieferzeiten_audit_log` (`audit`),
  - `lieferzeiten_task` (`task`).
- Interdiction explicite d'utiliser des mocks frontend comme source de vérité KPI.
- Définition standardisée des fenêtres temporelles pour les KPI (au minimum `7j`, `30j`, `90j`, plus fenêtre custom bornée).
- Normalisation de tous les calculs temporels et regroupements en timezone `Europe/Berlin`.

## Exigences techniques

### 1) Source de données et agrégation
- Les KPI doivent être calculés côté backend via requêtes SQL/DAL sur tables persistées.
- Toute donnée de démonstration frontend est limitée à l'affichage de fallback visuel (jamais pour une valeur KPI business).
- Les agrégations doivent être documentées KPI par KPI (formule, table source, filtres, dimensions temporelles).

### 2) Fenêtres temporelles + timezone
- Chaque endpoint de statistiques accepte une fenêtre temporelle explicite (`from`, `to` ou `period`).
- Les bornes temporelles et regroupements journaliers/hebdomadaires sont évalués en `Europe/Berlin`.
- Le contrat d'API précise le comportement aux changements d'heure (DST) et l'inclusivité des bornes.

### 3) Endpoint versionné
- Exposer un endpoint versionné dédié aux statistiques backend (exemple: `/api/_action/lieferzeiten/v1/statistics`).
- Maintenir la compatibilité des consommateurs existants via plan de transition (dépréciation documentée si endpoint historique).
- Versionner explicitement le schéma de réponse (champs KPI, dimensions, metadata de fenêtre).

### 4) Validation par jeux de données de référence
- Créer un ou plusieurs jeux de données de référence (fixtures) couvrant:
  - cas nominal,
  - cas limites temporels (DST, minuit, bornes),
  - données manquantes/partielles,
  - volumes multi-sources (`paket`, `position`, `audit`, `task`).
- Définir les valeurs KPI attendues pour ces fixtures et les stocker dans une oracle de test versionnée.

### 5) Tests d'agrégation reproductibles
- Ajouter des tests d'intégration (ou fonctionnels) qui:
  - chargent les fixtures de référence,
  - exécutent les agrégations backend,
  - comparent le résultat aux KPI attendus.
- Les tests doivent être déterministes (freeze time, timezone forcée, seed fixe).
- Les tests doivent être rejouables en CI et local, sans dépendre d'un état externe.

## Critères d'acceptation
- [ ] Aucun KPI affiché en admin ne dépend de mocks frontend.
- [ ] Les KPI backend proviennent de `paket`, `position`, `audit`, `task` persistés.
- [ ] Fenêtres temporelles et timezone `Europe/Berlin` sont appliquées et testées.
- [ ] Endpoint versionné statistiques exposé et documenté.
- [ ] Jeux de données de référence versionnés + KPI attendus validés.
- [ ] Tests d'agrégation reproductibles au vert en CI.
