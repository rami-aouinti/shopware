# Task: sécurisation des migrations (SQL/DAL)

## Objectif
Assurer que les prochaines migrations du plugin `ExternalOrders` sont déployables en production sans interruption, avec une stratégie claire de compatibilité, de rollback et de validation.

## Scope
- Migrations SQL (`MigrationStep::update`) et impacts DAL (Definition/Entity/Repository).
- Ajout de nouveaux champs et scripts de backfill associés.
- Exécution sûre sur bases existantes (données historiques hétérogènes).

## Livrables attendus

### 1) Ordre des migrations SQL/DAL
- Définir l'ordre exact d'exécution:
  1. Ajout schéma SQL backward-compatible (tables/colonnes/index nullable ou avec defaults sûrs).
  2. Adaptation DAL (Definition/Entity/Service) pour lire l'ancien + nouveau modèle.
  3. Activation progressive de l'écriture sur nouveaux champs.
  4. Nettoyage/destructive migration dans une release ultérieure.
- Documenter les dépendances inter-migrations (timestamp + prérequis techniques).

### 2) Scripts de backfill pour nouveaux champs
- Créer un script de backfill dédié (command/service) pour remplir les nouveaux champs à partir des données existantes.
- Exiger un mode batch/paginé (limitation mémoire, reprise possible).
- Ajouter des logs de progression + métriques (total traité, erreurs, relances).
- Prévoir un mode dry-run pour estimer l'impact avant exécution réelle.

### 3) Compatibilité avec les données existantes
- Vérifier explicitement:
  - nullabilité / valeurs par défaut,
  - formats legacy,
  - contraintes uniques et collisions possibles,
  - lecture applicative avant/après migration.
- Documenter les cas limites et la stratégie de correction automatique.

### 4) Procédure de rollback (technique + opérationnelle)
- **Technique**:
  - rollback applicatif vers version N-1,
  - stratégie DB (snapshot/restauration, scripts compensatoires, flags de désactivation).
- **Opérationnelle**:
  - runbook incident (qui fait quoi, ordre des actions, délais cibles),
  - communication interne (support/ops/dev),
  - critères Go/No-Go et déclenchement rollback.

### 5) Checklist de validation post-migration
- Migration exécutée sans erreur SQL.
- Intégrité des données validée (comptages avant/après).
- Backfill terminé et taux d'échec documenté.
- Parcours applicatifs critiques validés (liste de smoke tests).
- Monitoring/alerting vérifié pendant la fenêtre de stabilisation.
- Validation métier finale et clôture du changement.

## Critères d'acceptation
- Plan de migration versionné et relu.
- Runbook de rollback disponible et testé en environnement de recette.
- Preuve de compatibilité sur dataset représentatif de production.
- Tests automatisés de migration idempotente ajoutés et au vert.

## Tests à implémenter (idempotence)
- Exécuter chaque migration `update()` au moins 2 fois dans les tests.
- Vérifier l'absence d'erreur et l'absence de corruption de schéma/données.
- Valider que `updateDestructive()` reste sûr lorsqu'il est rejoué.
