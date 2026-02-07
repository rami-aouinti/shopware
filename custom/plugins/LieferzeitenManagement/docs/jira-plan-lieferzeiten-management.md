# Plan de tickets techniques (Jira-ready)

Plugin : **LieferzeitenManagement**

## EPIC 1 — Fondation & Modèle de données
**Objectif :** s’assurer que la base DAL couvre toutes les contraintes métier.

### Ticket 1.1 — Vérifier/compléter les entités DAL
**But :** valider que toutes les entités nécessaires sont présentes et correctement reliées.

**Inclut :**
- vérifier `Package`, `OrderPosition`, `TrackingNumber`, `TrackingEvent`, `DateHistory`, `Task`, `TaskAssignment`, `Settings`, `NotificationSettings`.
- ajouter les champs manquants si besoin (ex. “weitere Namen” si pas couvert via `orderCustomer`).

**Réfs :** entités déjà présentes dans le plugin.

## EPIC 2 — Interface Admin & UX
**Objectif :** rendre l’écran “Lieferzeiten” conforme aux exigences métier.

### Ticket 2.1 — Sélecteur “Bereich” + Haupt-Ansichten
**But :** au chargement, forcer la sélection de **Bereich** (First Medical / E‑Commerce / Medical Solutions) + vue (toutes commandes / ouvertes).

**Réfs :** module d’admin existant, mais pas de sélecteur à ce stade.

### Ticket 2.2 — Ajout du bloc KPI en haut
**But :** afficher 3 compteurs :
- commandes ouvertes total
- en retard expédition
- en retard livraison

**Réfs :** UI actuelle sans KPIs.

### Ticket 2.3 — Compléter toutes les colonnes demandées
**But :** ajouter les colonnes manquantes (ex. statut complet, données supplémentaires client, dates limites calculées, etc.).

**Réfs :** colonnes actuelles limitées.

### Ticket 2.4 — Implémenter tous les filtres métier
**But :** filtres sur Liefertermin Lieferant, Neuer Liefertermin, User, etc.

**Réfs :** filtres actuels insuffisants.

### Ticket 2.5 — Validation UI des champs date range
**But :**
- Liefertermin Lieferant : range 1–14 jours + KW + condition de sauvegarde
- Neuer Liefertermin : range 1–4 jours + historique

**Réfs :** datepickers actuels sans contraintes.

## EPIC 3 — Moteur métier des statuts
**Objectif :** appliquer la logique des 8 statuts et sync Shopware.

### Ticket 3.1 — StatusResolver (mapping Shopware → Lieferzeiten)
**But :**
- mapper les statuts Shopware aux statuts 1–6
- afficher le bon statut métier dans la colonne “Status”

**Réfs :** on affiche aujourd’hui uniquement `stateMachineState`.

### Ticket 3.2 — Sync “Versendet” vers Shopware
**But :** si statut 7 détecté (San6), push API Shopware.

**Réfs :** San6 sync service déjà présent, pas de push retour.

### Ticket 3.3 — Sync “Bestellung abgeschlossen” vers Shopware
**But :** statut 8 basé tracking ou San6 (si livraison interne).

**Réfs :** tracking sync stocke events sans logique de completion.

## EPIC 4 — Tracking avancé
**Objectif :** interpréter les événements et calculer livraison complète.

### Ticket 4.1 — Interprétation des événements DHL/GLS
**But :** appliquer les règles (paketshop, ablageort, retour, douane, refus) pour “abgeschlossen / nicht abgeschlossen”.

**Réfs :** events stockés mais pas analysés.

### Ticket 4.2 — Agrégation : commande terminée si tous colis arrivés
**But :** statut “Bestellung abgeschlossen” uniquement si tous les colis sont terminés.

**Réfs :** pas encore implémenté.

### Ticket 4.3 — Popup tracking “Sendungsverlauf”
**But :** enrichir le popup pour afficher timeline complète.

**Réfs :** popup simple déjà présent.

## EPIC 5 — Notifications & paramétrage
**Objectif :** envoi d’emails automatisés + options par sales channel.

### Ticket 5.1 — Écran admin “notification settings”
**But :** activer/désactiver chaque notification par sales channel.

**Réfs :** entité présente, pas d’UI.

### Ticket 5.2 — Moteur d’envoi (events/flows)
**But :** déclencher les emails demandés (commande, tracking, livraison, paiement, etc.).

**Réfs :** non implémenté.

## EPIC 6 — Tâches & workflow fournisseur
**Objectif :** automatiser et suivre les tâches fournisseurs.

### Ticket 6.1 — Bouton “Zusätzliche Liefertermin-Anfrage”
**But :** créer tâche + assignation + notification au demandeur.

**Réfs :** pas de bouton dans l’UI actuelle.

### Ticket 6.2 — Clôture automatique de tâches
**But :** fermer tâche quand Liefertermin Lieferant est mis à jour.

**Réfs :** `DateHistory` existe mais pas utilisé côté workflow.

### Ticket 6.3 — Génération automatique “overdue shipping”
**But :** déjà existant, à brancher en cron/scheduler.

**Réfs :** service + commande disponibles.

## EPIC 7 — Statistiques
**Objectif :** nouvel écran “Statistiken Lieferzeiten-Management”.

### Ticket 7.1 — Route + page stats admin
**But :** créer nouvelle vue avec graphiques + tableaux.

**Réfs :** module actuel ne contient qu’une route index.

### Ticket 7.2 — Agrégations/statistiques
**But :** calculer délais moyens, retards, volumes, etc.

## EPIC 8 — Exclusions & règles spéciales
**Objectif :** filtrer les “Testbestellungen”.

### Ticket 8.1 — Filtre “Testbestellung”
**But :** exclure ces commandes à l’import/sync/affichage.

**Réfs :** aucun filtre à ce jour dans le criteria de listing.
