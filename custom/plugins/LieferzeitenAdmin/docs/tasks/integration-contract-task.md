# Task — Contrat d'intégration multi-sources

Statut: `done`  
Owner: `LieferzeitenAdmin`  
Référence: `docs/integration-contract.md`

## Objectif
Définir, documenter et valider un contrat d'intégration unique pour:
- Shopware,
- Gambio,
- San6,
- DHL/GLS.

## Périmètre livré
- Contrats d'entrée/sortie documentés.
- Priorité des sources en cas de conflit documentée et implémentée.
- Règles de fallback documentées et appliquées.
- Champs minimaux de persistance définis et validés.

## Critères d'acceptation
- [x] Document versionné présent: `docs/integration-contract.md`
- [x] Exemples de payloads réels (anonymisés) inclus.
- [x] Validation technique centralisée dans un validateur unique.
- [x] Tests unitaires couvrant les contrats API, persistance et priorité.
