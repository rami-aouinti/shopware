# Task — Endpoints write: concurrence optimiste et gestion de conflit

Statut: `done`  
Owner: `LieferzeitenAdmin`  
Référence: `src/Controller/LieferzeitenSyncController.php`, `src/Service/LieferzeitenPositionWriteService.php`

## Objectif
Sécuriser les endpoints write de position (`liefertermin-lieferant`, `neuer-liefertermin`, `comment`) contre les écrasements silencieux lors d’éditions concurrentes.

## Périmètre livré
- Contrôle de concurrence optimiste via `updatedAt` côté requête.
- Erreur API explicite en cas de conflit d’édition (`409 CONCURRENT_MODIFICATION`).
- Stratégie de refresh partiel de ligne via payload `refresh` exploitable par l’UI.
- Journalisation d’audit des conflits avec corrélation de requête (`correlation_id`).
- Test automatisé d’édition concurrente (deux utilisateurs sur la même position).

## Contrat API write (nouveau)
- Les endpoints write concernés exigent `updatedAt` dans le payload.
- En cas de jeton obsolète:
  - HTTP `409`,
  - `code = CONCURRENT_MODIFICATION`,
  - `message` explicite,
  - `refresh` contenant la version courante partielle de la ligne.

## Critères d’acceptation
- [x] Les writes refusent un payload sans `updatedAt`.
- [x] Les conflits d’édition ne peuvent plus écraser la version courante.
- [x] L’API renvoie une réponse de conflit lisible par le frontend.
- [x] Le frontend applique le refresh partiel de ligne après conflit.
- [x] Les événements de conflit sont tracés dans l’audit log avec corrélation.
- [x] Test d’édition concurrente présent et au vert.
