# Checklist finale — Bascule SAN6 (`ExternalOrders`)

## 1) Ordre exact de déploiement

1. **Migration DB**
   - Déployer le code et exécuter les migrations plugin (non destructives puis destructives si prévues dans la fenêtre).
   - Vérifier l’absence d’erreurs SQL dans les logs et la disponibilité des tables/champs attendus.
2. **Configuration SAN6**
   - Renseigner/valider les clés :
     - `ExternalOrders.config.externalOrdersSan6BaseUrl`
     - `ExternalOrders.config.externalOrdersSan6Authentifizierung`
     - `ExternalOrders.config.externalOrdersSan6WriteFunction`
     - `ExternalOrders.config.externalOrdersSan6SendStrategy`
   - Contrôler que la configuration est cohérente avec l’environnement cible (URL, auth, stratégie d’envoi).
3. **Activation des tâches planifiées**
   - Vérifier que `external_orders.export_retry` est `scheduled`.
   - Confirmer que le worker/cron Shopware exécute bien `scheduled-task:run`.
4. **Smoke test post-déploiement**
   - Déclencher un export manuel sur une commande de test.
   - Confirmer la création d’une tentative dans `external_order_export`.
   - Valider le statut final `sent` et `response_code = 0`.

---

## 2) Fenêtre de bascule (go-live)

- **Préparer une fenêtre dédiée** (Ops + Dev + métier), avec gel des changements non essentiels.
- **Go/No-Go d’entrée de fenêtre** :
  - migrations prêtes,
  - configuration SAN6 validée,
  - supervision/logs accessibles.
- **Validation immédiate obligatoire** :
  - exécuter un **export test** dans les 5 minutes suivant l’activation,
  - succès attendu : `sent` + `response_code = 0`,
  - en cas d’échec : enclencher le rollback opérationnel ci-dessous.

---

## 3) Rollback opérationnel (ordre strict)

1. **Désactivation de l’export**
   - Basculer la stratégie d’envoi vers une valeur neutralisée (ou désactiver la fonctionnalité via config/feature flag opérationnel).
   - Objectif : stopper immédiatement tout nouvel envoi SAN6.
2. **Purge / suspension des retries**
   - Suspendre la tâche planifiée `external_orders.export_retry` (ou stopper le worker scheduler).
   - Mettre en file d’attente contrôlée ou purger les retries selon la politique incident validée.
3. **Restauration de la configuration précédente**
   - Restaurer les valeurs SAN6 connues stables (snapshot/config sauvegardée avant bascule).
   - Vérifier la cohérence des clés critiques puis relancer un export test avant reprise.

---

## 4) Checklist de clôture

- [ ] Migrations DB exécutées sans erreur.
- [ ] Clés SAN6 configurées et vérifiées.
- [ ] Tâche `external_orders.export_retry` active et planifiée.
- [ ] Export test post-bascule validé (`sent`, `response_code = 0`).
- [ ] Rollback prêt et partagé (Ops/Dev/Support).
- [ ] Journal de bascule complété (heure, intervenants, résultat, anomalies).
