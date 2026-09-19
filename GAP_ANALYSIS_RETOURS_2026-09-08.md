# Gap Analysis — Retours client du 08/09/2026 vs code réel `toa-back`

## 1. En-tête

- **Repo analysé :** `D:\TOA\toa-back`
- **Branche :** `develop` (non modifiée, aucun commit créé pendant l'analyse)
- **HEAD au moment de l'analyse :** `aeb811691e551784929782c3dde3483d0569884d` — *"Force APP_ENV=prod sur les conteneurs PHP pour cor..."* — 2026-09-06 18:53:49 +0000
- **Date de l'analyse :** 2026-09-14
- **Source des retours :** `D:\TOA\Retours_Consolides_2026-09-08.md` (statuts définitifs post-`precisions.png`)
- **Méthode :** lecture directe du code (entités, processors, voters, config, migrations, templates) sur `develop`. Aucune confiance accordée à `Audit_Conformite_TOA.md` (06/07/2026) ni à `Taches_toa-back.md`, tous deux partiellement obsolètes : le domaine `PlanPrevention` et `PermitTravail` existent réellement et sont nettement plus avancés que ce que ces documents laissent penser (pas de moteur Symfony Workflow branché mais un état géré par enum + Processors dédiés, fonctionnellement équivalent).
- **Périmètre :** R-03 à R-28 (impact back). R-01/R-02 (logos ISO, titre page de connexion) exclus car 100% front, sans champ/enum back concerné.

---

## 2. Tableau de synthèse

| Réf | Retour (résumé) | Statut réel | Effort restant |
|---|---|---|---|
| R-03 | Autoriser plusieurs planifications même site/date | **NON COMMENCÉ** | S |
| R-04 | Déplacer sections/modes opératoires vers le plan de prévention | **NON COMMENCÉ** | L (structurant) |
| R-05 | Restreindre création : chef projet → planif, prestataire → prévention | **FAIT** | — |
| R-06 | Import CSV des codes site (au lieu de saisie manuelle) | **NON COMMENCÉ** | S/M |
| R-07 | Liste des plans de prévention filtrée par entreprise prestataire | **PARTIEL** | S |
| R-08 | Phases + modes opératoires avec risques dans le plan de prévention | **PARTIEL** | M/L (dépend R-04) |
| R-09 | Alerte APN/API multi-site | **NON COMMENCÉ** | S (dépend R-27) |
| R-10 | Risque APN/API obligatoire (Option 1) | **NON COMMENCÉ** | S/M (dépend R-27/R-09) |
| R-11 | Traçage consultation obligatoire avant Valider/Refuser | **NON COMMENCÉ** | M |
| R-12 | Refuser avec commentaire obligatoire + notif + statut correction | **FAIT** (avec réserve) | — |
| R-13 | Notification groupée à tous les HSE **de TOA** | **PARTIEL** (mauvais périmètre) | S/M |
| R-14 | Suppression raccourci validation depuis liste | **FAIT** | — |
| R-15 | Prise de photo directe (3 modules) | **PARTIEL** | S |
| R-16 | Horodatage des photos | **PARTIEL** | S |
| R-17 | Plusieurs photos par type de document | **NON COMMENCÉ** | S/M |
| R-18 | Valeur N/A par ligne de document | **NON COMMENCÉ** | S/M |
| R-19 | Heures soumission + validation sur plan prévention ET permis | **PARTIEL** | S |
| R-20 | Permis général obligatoire hors « Nouveau site » | **NON COMMENCÉ** | S/M |
| R-21 | Renommer « Clôture permis » → « PV Environnement » | **N/A back** (aucun libellé côté API) | — |
| R-22 | 2 photos avant/après travaux à la clôture | **NON COMMENCÉ** | S/M |
| R-23 | Pièces environnementales conditionnées APN/API | **NON COMMENCÉ** | L (dépend R-27/R-09/R-10) |
| R-24 | Supprimer indicateur validation PV du dashboard | **NON COMMENCÉ** | S |
| R-25 | Afficher nombre de permis (total) | **PARTIEL** | S |
| R-26 | Couleur par type de projet | **NON COMMENCÉ** | S/M |
| R-27 | Import KMZ : 2 colonnes APN/API | **NON COMMENCÉ** | S/M (structurant) |
| R-28 | Lien direct vers écran de validation dans les emails | **NON COMMENCÉ** | S/M |

**Décompte :** FAIT = 2 (R-05, R-14) · FAIT avec réserve = 1 (R-12) · PARTIEL = 8 (R-07, R-08, R-13, R-15, R-16, R-19, R-25, +note) · NON COMMENCÉ = 16 · N/A back = 1 (R-21).

---

## 3. Détail par thème

### 3.1 Planification (R-03 à R-07)

**R-03 — NON COMMENCÉ.** Le contrôle de conflit existe toujours et bloque activement la création :
- `src/Domain/ActivityPlanning/Service/ConflictDetector.php:15-43` — requête qui compte les `ActivityPlanning` de même `siteCode` avec chevauchement de dates parmi les statuts `EN_COURS`/`PLANIFIE`.
- `src/Api/Processor/ActivityPlanningCreateProcessor.php:43-45` — `if ($this->conflictDetector->hasConflict($data)) { throw new ConflictHttpException(...) }`.
À faire : supprimer l'appel (et le service si plus utilisé ailleurs) dans `ActivityPlanningCreateProcessor`.

**R-04 — NON COMMENCÉ.** Le retour demande que la saisie des sections/modes opératoires passe de la Planification au Plan de Prévention. Constat inverse :
- `src/Domain/ActivityPlanning/Entity/ActivityPlanning.php:147-155` — `sections` reste en écriture (`Groups(['activity_planning:write'])`), toujours porté par `ActivityPlanning`.
- `src/Domain/ActivityPlanning/Entity/SectionPlanifiee.php` et `TachePlanifiee.php` — entités intactes, rattachées à `ActivityPlanning`, toujours éditables via `ActivityPlanningUpdateProcessor`.
- `src/Domain/PlanPrevention/EventListener/PlanPreventionPostLoadListener.php:56` — `$plan->setPlanificationSections($sections)` : le plan de prévention ne fait que **lire en lecture seule** (postLoad, non persisté) les sections/tâches de la planification associée, via `PlanPrevention::getPlanificationSections()` (`src/Domain/PlanPrevention/Entity/PlanPrevention.php:540-548`, propriété non mappée Doctrine).
- Aucune entité `PhasePlanPrevention` / `ModeOperatoirePlanPrevention` n'existe dans `src/Domain/PlanPrevention/Entity/`.
Conclusion : le prestataire ne peut toujours pas saisir lui-même phases/modes opératoires dans le plan de prévention ; il ne fait que consulter ce que le chef de projet a saisi dans la Planification. **Retour structurant, non implémenté.**

**R-05 — FAIT.** Le contrôle est data-driven (`Menu`/`MenuAccess`) et déjà seedé correctement :
- `src/Domain/Menu/Command/SeedMenuCommand.php:44-51` — route `/planning` : `create => true` uniquement pour `ROLE_CHEF_PROJET`/`ROLE_ADMIN`/`ROLE_SUPER_ADMIN` ; `ROLE_PRESTATAIRE` absent (refus par défaut).
- `src/Domain/Menu/Command/SeedMenuCommand.php:52-64` — route `/prevention` : `create => true` pour `ROLE_PRESTATAIRE` (et `ROLE_AGENT_TERRAIN`), `create => false` pour `ROLE_CHEF_PROJET`.
- Appliqué via `ActivityPlanningVoter::voteOnAttribute` (`src/Security/Voter/ActivityPlanningVoter.php:39-46`) et `PlanPreventionVoter` (`src/Security/Voter/PlanPreventionVoter.php:54-75`), tous deux passant par `PermissionChecker`.
À vérifier : que ce seed est bien rejoué en production (commande `app:menu:seed` — hors périmètre lecture-seule de cette mission, non exécuté).

**R-06 — NON COMMENCÉ.** Aucune trace d'import CSV. Recherche `csv|Csv|CSV` sur `src/` : 0 résultat. La saisie des codes site reste manuelle (`ActivityPlanning::siteCode`/`sites` en JSON libre, `src/Domain/ActivityPlanning/Entity/ActivityPlanning.php:80-90,133-136`).

**R-07 — PARTIEL.** Un filtrage existe mais ne correspond pas exactement au périmètre demandé (« filtré sur l'**entreprise** du prestataire ») :
- `src/Doctrine/Extension/PlanPreventionExtension.php:52-57` — pour `ROLE_PRESTATAIRE`, filtre `createdBy.email = utilisateur courant` (filtrage **par utilisateur individuel**, pas par entreprise). Deux prestataires de la même société ne verront donc pas les plans l'un de l'autre.
- Comparer avec `PermitTravailVoter::hasOwnership` (`src/Security/Voter/PermitTravailVoter.php:263-275`) qui, lui, élargit correctement à `user.entreprise === permit.createdBy.entreprise`. Le même élargissement manque sur `PlanPreventionExtension`.
À faire : étendre la clause `WHERE` de `PlanPreventionExtension` à `createdBy.entreprise = :entreprise` au lieu de `createdBy.email = :email`.

### 3.2 Plan de prévention — Prestataire (R-08 à R-10)

**R-08 — PARTIEL.** Le bloc « risques » existe et est riche, mais le lien au mode opératoire reste faible et hérité de la Planification :
- `src/Domain/PlanPrevention/Entity/RisquePrevention.php:110-112` — `tachePlanifieeId` (string nullable, pas de FK réelle) référence une `TachePlanifiee` **de l'`ActivityPlanning`**, pas une entité interne au plan de prévention.
- `src/Domain/PlanPrevention/Entity/RisquePrevention.php:118-125` — `categoriesRisque` (ManyToMany vers `CategorieRisque`) : bien modélisé, gravité/probabilité/niveauRisque calculé (`calculateNiveauRisque`, L242-247).
- `src/Api/Processor/RisquePreventionProcessor.php` : aucune validation de cohérence entre le risque et un « mode opératoire » (le champ `tachePlanifieeId` n'est ni requis ni vérifié comme existant).
Conclusion : la brique « risques » est FAITE, mais la brique « phases/modes opératoires saisis dans le plan de prévention » (préalable de R-04) est absente — donc R-08 ne peut être clos qu'après R-04.

**R-09 — NON COMMENCÉ.** Aucune notion APN/API n'existe dans le référentiel de sites :
- `src/Domain/Referentiel/Entity/Site.php` — colonnes complètes (zone, typeSite, typePylone…) mais **aucun champ apn/api**.
- `src/Domain/PlanPrevention/Entity/SitePrevention.php` — idem, aucun champ apn/api.
- `src/Domain/Referentiel/Entity/ImportKmzSite.php` — idem.
Aucune alerte ne peut donc être calculée aujourd'hui.

**R-10 — NON COMMENCÉ.** Corollaire de R-09 : `CategorieRisque` n'a qu'un champ `typePermis` générique (`src/Domain/Referentiel/Entity/CategorieRisque.php:67-69`), rien qui permette de taguer un risque comme « APN » ou « API ». `RisquePreventionProcessor` (`src/Api/Processor/RisquePreventionProcessor.php`) et `PlanPreventionSoumettreProcessor` (`src/Api/Processor/PlanPreventionSoumettreProcessor.php:46-57`, qui ne contrôle que les 6 `TypeDocumentPrevention` requis) n'imposent aucune contrainte de risque obligatoire. Bloqué tant que R-27 (colonnes KMZ) et R-09 (champ sur Site) ne sont pas faits.

### 3.3 Plan de prévention — Chef de projet (R-11 à R-14)

**R-11 — NON COMMENCÉ.** Recherche `consult|Consultation` sur `src/` : aucune occurrence liée au plan de prévention (les 5 résultats trouvés concernent `PermitTravailStatsJournalieres`/`MailTestCommand`, sans rapport). `PlanPreventionVoter::checkStatutForHse` (`src/Security/Voter/PlanPreventionVoter.php:235-240`) ne vérifie que le statut `EXAMINE`, aucune trace de consultation des pièces jointes/photos avant Valider/Refuser. Pas d'entité `ConsultationLog`, pas de champ `consultedAt` sur `DocumentPrevention`/`RisquePrevention`.

**R-12 — FAIT (avec réserve).** `src/Api/Processor/PlanPreventionRefuserProcessor.php` :
- L49-51 : commentaire obligatoire (`throw ... 'Commentaire obligatoire en cas de refus'` si vide).
- L61-69 : `DecisionHsePlanPrevention` créée (decision=REFUSE, commentaire, signature, decidedAt).
- L71 : `$plan->setStatut(StatutPlanPrevention::BROUILLON)`.
- L75 : `$this->notificationService->notifierPrestataire($plan, $commentaire)`.
Réserve : le statut retombe à `BROUILLON` (`src/Domain/PlanPrevention/Enum/StatutPlanPrevention.php`), il n'existe pas de valeur distincte type `A_CORRIGER` — un plan « refusé à corriger » est donc indiscernable d'un nouveau brouillon jamais soumis dans l'énumération de statut (l'historique via `DecisionHsePlanPrevention` permet de le déduire, mais ce n'est pas un statut direct). Fonctionnellement le retour est couvert ; à confirmer avec TOA si cette distinction leur importe pour l'affichage front.

**R-13 — PARTIEL, mauvais périmètre.** La notification groupée (pas nominative) existe bien :
- `src/Domain/PlanPrevention/Service/PlanPreventionNotificationService.php:157-165` (`findHseTeamFor`) — boucle sur tous les `ROLE_HSE` d'une entreprise (`notifyHseUsers`, L108-148).
- Mais l'entreprise ciblée est **celle du créateur du plan (le prestataire)** : `$entrepriseId = $plan->getCreatedBy()?->getEntreprise()?->getId()` (L159). Or `precisions.png` précise que le rôle HSE visé est **au sein de TOA**, pas de l'entreprise du prestataire (Etech ou autre).
- `src/Domain/Entreprise/Entity/Entreprise.php` — aucun champ ne permet de distinguer « TOA » (interne) d'une entreprise prestataire cliente ; impossible de corriger le ciblage sans ce flag (ou sans une requête « tous les `ROLE_HSE` sans filtre entreprise »).
- Même schéma exact côté permis : `src/Domain/PermitTravail/Service/PermitTravailNotificationService.php` (`findHseTeamFor`, mêmes lignes ~), donc le même correctif est nécessaire des deux côtés.

**R-14 — FAIT.** `src/Domain/PlanPrevention/Entity/PlanPrevention.php:37-106` et `src/Domain/PermitTravail/Entity/PermitTravail.php:45-134` : la liste complète des opérations `ApiResource` ne contient qu'un seul endpoint de validation par entité (`/plans-prevention/{id}/valider`, `/permits-travail/{id}/valider`), nécessitant la récupération de l'objet (`read: true`) et soumis au contrôle de statut du Voter (`checkStatutForHse`/`checkSoumisStatut`). Aucun raccourci de validation en masse depuis une `GetCollection` n'existe.

### 3.4 Téléversement des pièces justificatives (R-15 à R-18)

**R-15 — PARTIEL.** Les 3 endpoints d'upload (`DocumentUploadProcessor`, `PermitTravailDocumentUploadProcessor`, `SuiviJournalierDocumentUploadProcessor`) acceptent déjà `image/jpeg` en plus de `application/pdf` (`src/Api/Processor/DocumentUploadProcessor.php:22` : `ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg']`), donc une photo prise en JPEG passe déjà. Manque : `image/png` et `image/webp` (formats fréquents de capture caméra web/PWA selon navigateur/OS) ne sont pas dans la liste blanche — à valider avec le comportement réel du composant de capture front avant de considérer ce point clos.

**R-16 — PARTIEL.** `uploadedAt` existe (`DocumentPrevention.php:77-79`, `PermitTravailDocument.php:77-79`, `SuiviJournalierDocument.php:92-94`) et est renseigné à l'écriture serveur (`new \DateTimeImmutable()`, ex. `DocumentUploadProcessor.php:102`). C'est un horodatage **d'upload serveur**, pas une date/heure de **prise de vue** (pas d'extraction EXIF, pas de paramètre `capturedAt` accepté en entrée). Suffisant si le upload est synchrone juste après la prise de photo (ce que R-15 sous-tend), mais aucune garantie ni champ dédié aujourd'hui.

**R-17 — NON COMMENCÉ**, activement empêché par le code : dans les 2 processors principaux, le document existant du même type est **supprimé** avant l'écriture du nouveau :
- `src/Api/Processor/DocumentUploadProcessor.php:82-93` — `foreach ($plan->getDocuments() ... if same type) { delete + remove }`.
- `src/Api/Processor/PermitTravailDocumentUploadProcessor.php:116-126` — logique identique.
Les relations sont déjà `OneToMany` (pas de contrainte d'unicité DB visible), donc la correction est essentiellement applicative : retirer la boucle de suppression, et ajouter une opération `Delete` (absente aujourd'hui sur `DocumentPrevention`/`PermitTravailDocument`, contrairement à `SuiviJournalierDocument` qui a déjà `SuiviJournalierDocumentDeleteProcessor`, cf. `SuiviJournalierDocument.php:50-63`) pour permettre à l'utilisateur de retirer une photo précise dans la galerie.
Note : `SuiviJournalierDocument` (suivi journalier) n'a **pas** cette limitation — plusieurs fichiers par suivi sont déjà possibles côté back pour ce module.

**R-18 — NON COMMENCÉ.** Aucun champ « N/A » nulle part : ni sur `DocumentPrevention`, ni sur `PermitTravailDocument`, ni dans `TypeDocumentPrevention`/`TypeDocumentPermitTravail`. Les contrôles de complétude (`PlanPreventionSoumettreProcessor::findMissingDocumentTypes`, `PermitDocumentRequirementResolver::findMissing`) comparent uniquement présence/absence d'un type, sans notion de non-applicabilité déclarée.

### 3.5 Permis de travail (R-19 à R-23)

**R-19 — PARTIEL.** Côté permis, `soumisAt` existe (`PermitTravail.php:212-214`, renseigné dans `PermitTravailSoumettreProcessor.php:65-66`) mais **pas de `validatedAt`** direct — la date de validation n'est accessible qu'en filtrant la collection `decisionsHse` (`DecisionHsePermitTravail::decidedAt`, `src/Domain/PermitTravail/Entity/DecisionHsePermitTravail.php:48-50`) sur `decision === VALIDE`. Côté plan de prévention, c'est pire : `PlanPrevention.php` n'a **ni `soumisAt` ni `validatedAt`** — `PlanPreventionSoumettreProcessor.php` (L59-65) ne fait que changer le statut, sans horodater la soumission ; la date de décision n'existe que via `DecisionHsePlanPrevention::decidedAt` (`src/Domain/PlanPrevention/Entity/DecisionHsePlanPrevention.php:48-50`). Pour calculer un délai soumission→validation côté front sans repasser par une jointure sur les collections, il manque des accesseurs de convenance (`getSoumisAt()`/`getValidatedAt()`) sur `PlanPrevention`, et un champ persistant `soumisAt`.

**R-20 — NON COMMENCÉ.** La logique de couplage obligatoire (permis Général + permis spécialisé soumis ensemble) n'existe **que pour le processus `NOUVEAU_SITE`** :
- `src/Api/Processor/PermitTravailSoumettreProcessor.php:60-63,88-129` — `checkNouveauSitePair` déclenché uniquement `if ($permit->getProcessus() === ProcessusPermitTravail::NOUVEAU_SITE)`.
Le retour R-20 demande l'inverse : rendre obligatoire l'ajout d'un permis général **hors** processus « Nouveau site » (`MAINTENANCE`, `RENOUVELLEMENT` — `src/Domain/PermitTravail/Enum/ProcessusPermitTravail.php`). Aucune contrainte de ce type n'existe pour ces deux processus aujourd'hui.

**R-21 — N/A back.** Aucun libellé « Clôture permis » n'est exposé par l'API (pas de champ label sur `CloturePerm`, pas d'entrée menu portant ce nom dans `SeedMenuCommand.php`, pas de constante trouvée par recherche `Clôture|PV Environnement`). Le seul point de contact avec « environnement » côté back est l'enum `TypeDocumentPermitTravail::PV_CLOTURE_ENVIRONNEMENT` (`src/Domain/PermitTravail/Enum/TypeDocumentPermitTravail.php:19`), déjà nommé de façon cohérente. Renommage à faire côté front uniquement, sauf si TOA souhaite aussi renommer cette valeur d'enum (à confirmer, mais aucune urgence technique).

**R-22 — NON COMMENCÉ.** La matrice des documents de clôture est figée à 2 documents, sans notion de photos avant/après travaux :
- `src/Domain/PermitTravail/Service/PermitDocumentRequirementResolver.php:30-39` (`CLOTURE_MATRIX`) : `NOUVEAU_SITE => [PV_CLOTURE_ENVIRONNEMENT, PV_FIN_TRAVAUX]`, `AUTRES => [PHOTO_PROPRETE_SITE, PV_FIN_TRAVAUX]`.
- `src/Domain/PermitTravail/Enum/TypeDocumentPermitTravail.php` : aucune valeur `PHOTO_AVANT_TRAVAUX`/`PHOTO_APRES_TRAVAUX`.
- Le contrôle est bien branché à la clôture réelle : `src/Api/Controller/ClotureManuelleProcesoor.php:57-63` appelle `findMissingClotureDocumentTypes($permit)` — donc l'ajout des 2 nouveaux types dans l'enum + la matrice suffira à activer le contrôle, sans retoucher le contrôleur.

**R-23 — NON COMMENCÉ.** Aucune des pièces environnementales listées en §3 du document consolidé (tri des déchets, délimitation site, autorisation CEF/DREDD, registre des déchets, cahier de plaintes, etc.) n'existe dans `TypeDocumentPermitTravail` ni dans `PermitDocumentRequirementResolver::MATRIX`/`CLOTURE_MATRIX`. Comme ces pièces ne doivent être obligatoires que pour les sites APN/API, ce retour est bloqué tant que R-27 (colonnes KMZ) et R-09 (champ Site) ne sont pas livrés.

### 3.6 Tableau de bord (R-24 à R-27)

**R-24 — NON COMMENCÉ.** Les indicateurs liés à la validation du PV sont toujours calculés et exposés :
- `src/Domain/Dashboard/Service/DashboardKpisService.php:104-133` (`tauxCloture`, calculé à partir de `decision_cdp_pv_reception` / `nbPvValides`).
- `src/Domain/Dashboard/Service/DashboardKpisService.php:205-221` (`tempsMoyenValidationPv`).
- Retournés tels quels dans `computeGlobal()` (L261-274) et `InterventionKpisController.php:37,45`.

**R-25 — PARTIEL.** `nbPermisValides` existe (L88-103, L267) mais c'est un compteur de permis **validés HSE**, pas le « nombre de permis » total demandé par R-25 (tous statuts confondus). Aucun `COUNT(*) FROM permit_travail` sans filtre de décision n'a été trouvé dans `computeGlobal()`.

**R-26 — NON COMMENCÉ.** Aucun champ couleur/typeProjet dans le payload dashboard (`computeGlobal`, `computeSites`, `computeEvolution` — `DashboardKpisService.php` en intégralité). `ActivityPlanning::typeIntervention` (`ActivityPlanning.php:129-131`) existe comme candidat de base pour dériver une couleur automatique, mais rien ne l'exploite pour le dashboard aujourd'hui.

**R-27 — NON COMMENCÉ.** `src/Api/Processor/KmzImportProcessor.php::extractExtendedData` (L249-277) ne lit que `fokontany`, `commune`, `district` depuis les `ExtendedData` du KML — aucune lecture de colonnes APN/API. `Site.php` et `ImportKmzSite.php` (référentiel) et `SitePrevention.php` (plan de prévention) n'ont aucune colonne pour stocker ces valeurs. C'est le retour le plus structurant du groupe R-09/R-10/R-23 (cf. plan d'intervention).

### 3.7 Notifications par courriel (R-28)

**R-28 — NON COMMENCÉ.** Aucun lien cliquable vers l'écran de validation dans les templates :
- `templates/email/plan_prevention/a_valider.html.twig:136-146` — texte statique *"Connectez-vous à la plateforme TOA pour valider ou refuser ce plan."*, sans URL.
- `templates/email/plan_prevention/soumis.html.twig`, `refuse.html.twig` — même constat (aucune occurrence de `http`/`url`/`lien` trouvée par grep sur `templates/email/`).
- Côté permis, `src/Domain/PermitTravail/Service/PermitTravailNotificationService.php` construit du HTML inline (`buildValidationHtml`, `buildRefusHtml`, `buildSoumissionHtml`, `buildClotureHtml`) — grep `htmlTemplate|http|url|Url|lien` sur ce fichier (31 Ko) : **0 résultat**. Aucun lien nulle part.
- Aucune variable d'environnement de type `FRONTEND_URL`/`APP_URL` n'existe dans la config (`config/services.yaml`, `config/packages/*.yaml`) pour construire une telle URL — il faudra l'introduire.

---

## 4. Plan d'intervention ordonné

L'ordre respecte la dépendance rappelée dans les retours consolidés : **R-04 → R-15 → R-23/R-27**, en insérant R-27 avant R-23 (R-23 dépend explicitement de R-27/R-09).

### Phase 0 — Corrections indépendantes, à faire en premier (aucune dépendance)
1. **R-03** : dans `src/Api/Processor/ActivityPlanningCreateProcessor.php`, retirer l'appel à `ConflictDetector::hasConflict()` (L43-45) ; supprimer ou neutraliser `src/Domain/ActivityPlanning/Service/ConflictDetector.php` s'il n'est plus utilisé ailleurs.
2. **R-14** : rien à faire (déjà conforme) — à documenter comme tel pour ne pas le re-traiter par erreur.

### Phase 1 — R-04 (structurant, bloque R-08)
3. Créer les entités `PhasePlanPrevention` et `ModeOperatoirePlanPrevention` (ou équivalent) dans `src/Domain/PlanPrevention/Entity/`, avec relation `OneToMany` depuis `PlanPrevention` (cascade `persist`/`remove`), migration Doctrine associée.
4. Exposer ces entités en écriture via `ApiResource` (`Groups(['plan_prevention:write'])`) suivant le même pattern que `RisquePrevention` (`RisquePreventionProcessor` comme référence de câblage `planPreventionId` → entité).
5. Modifier `RisquePrevention` pour référencer le nouveau `ModeOperatoirePlanPrevention` (FK réelle) au lieu de `tachePlanifieeId` (string libre vers `TachePlanifiee` de l'`ActivityPlanning`) — migration de données pour les plans déjà en base si nécessaire (à évaluer selon le volume en prod).
6. Retirer/geler l'édition des `sections`/`taches` côté `ActivityPlanning` (`ActivityPlanning.php:147-155`, `ActivityPlanningUpdateProcessor.php`) — garder les entités en lecture pour l'historique, mais sortir `sections` du groupe `activity_planning:write`.
7. Adapter `PlanPreventionPostLoadListener.php` et `PlanPreventionCreateProcessor.php` (L46-58) pour ne plus copier les sections/tâches de la Planification (comportement devenu obsolète une fois R-04 fait).

### Phase 2 — R-08 (dépend de Phase 1)
8. Une fois R-04 en place, adapter `PermitDocumentRequirementResolver`/formulaires front pour consommer les nouvelles entités `Phase`/`ModeOperatoire` au lieu de `planificationSections`.

### Phase 3 — R-27 (structurant, bloque R-09/R-10/R-23)
9. Ajouter les colonnes `apn` (bool) et `api` (bool) sur `src/Domain/Referentiel/Entity/Site.php` + migration Doctrine.
10. Ajouter les mêmes colonnes sur `src/Domain/PlanPrevention/Entity/SitePrevention.php` + migration.
11. Dans `src/Api/Processor/KmzImportProcessor.php::extractExtendedData` (L249-277), lire 2 nouvelles clés `ExtendedData` (ex. `apn`, `api`, valeurs `0`/`1`) et les propager dans `parsePlacemarks()` puis dans la construction de `SitePrevention` (L64-82) et dans le payload JSON de retour (L86-100).
12. Obtenir de TOA le fichier KMZ de référence mis à jour (prérequis fonctionnel, hors code).

### Phase 4 — R-09 / R-10 (dépendent de Phase 3)
13. **R-09** : exposer un indicateur calculé (`hasApnApiSite: bool` ou liste des sites concernés) sur la réponse de `PlanPrevention` (accesseur `Groups(['plan_prevention:read'])`), calculé à partir de `getSites()` filtrés sur `apn||api`.
14. **R-10** : ajouter un champ de catégorisation APN/API sur `CategorieRisque` (`src/Domain/Referentiel/Entity/CategorieRisque.php`) ou sur `RisquePrevention` directement ; ajouter la validation métier dans `PlanPreventionSoumettreProcessor::process()` (à côté du contrôle `findMissingDocumentTypes`, L46-57) : si au moins un site du plan a `apn||api`, exiger qu'au moins un risque du plan porte la catégorie APN/API correspondante, sinon `UnprocessableEntityHttpException`.

### Phase 5 — R-23 (dépend de Phase 3/4)
15. Étendre `TypeDocumentPermitTravail` avec les nouveaux types environnementaux (par item du tableau §3 de `Retours_Consolides`), et étendre `PermitDocumentRequirementResolver::MATRIX`/`CLOTURE_MATRIX` avec une dimension conditionnelle « site APN/API » (nécessite d'injecter l'info APN/API du `PermitTravail` — via son `codeSite` → `Site`/`SitePrevention` — dans `bucket()`, L139-141).

### Phase 6 — R-15/R-16/R-17/R-18 (upload)
16. **R-17** : retirer la boucle de suppression du document existant du même type dans `DocumentUploadProcessor.php` (L82-93) et `PermitTravailDocumentUploadProcessor.php` (L116-126) ; ajouter une opération `Delete` sur `DocumentPrevention`/`PermitTravailDocument` (suivre le pattern `SuiviJournalierDocumentDeleteProcessor`).
17. **R-16** : accepter un paramètre `capturedAt` optionnel en entrée des 3 processors d'upload, sinon fallback sur l'heure serveur actuelle ; ajouter le champ (déjà nommé `uploadedAt`, à clarifier en `capturedAt` ou ajouter un champ distinct) et l'exposer.
18. **R-18** : ajouter un champ `nonApplicable: bool` (ou une valeur spéciale de type) sur `DocumentPrevention`/`PermitTravailDocument`, et adapter `findMissingDocumentTypes`/`findMissing` pour exclure les lignes marquées N/A du contrôle bloquant.
19. **R-15** : élargir `ALLOWED_MIME_TYPES` (dans les 3 upload processors) à `image/png`, `image/webp` si le composant de capture front les produit (à confirmer avec le front).

### Phase 7 — R-19, R-20, R-22 (permis)
20. **R-19** : ajouter `soumisAt` sur `PlanPrevention` (colonne + set dans `PlanPreventionSoumettreProcessor.php` L59-65) ; ajouter des accesseurs de convenance `getValidatedAt()` sur `PlanPrevention` et `PermitTravail` dérivés de la dernière `DecisionHse*` avec `decision === VALIDE` (ou en persistant directement la date lors de la validation, plus simple pour le front).
21. **R-20** : étendre `checkNouveauSitePair` (ou créer une logique équivalente) dans `PermitTravailSoumettreProcessor.php` pour les processus `MAINTENANCE`/`RENOUVELLEMENT` — exiger qu'un permis `GENERAL` existe et soit soumis/validé avant qu'un permis spécialisé (Hauteur/Électrique) puisse être soumis pour ces processus.
22. **R-22** : ajouter `PHOTO_AVANT_TRAVAUX`/`PHOTO_APRES_TRAVAUX` à `TypeDocumentPermitTravail` ; les ajouter dans `CLOTURE_MATRIX` (`PermitDocumentRequirementResolver.php:30-39`) pour les deux buckets `NOUVEAU_SITE`/`AUTRES` ; ajouter ces valeurs à `CLOTURE_DOCUMENT_TYPES` dans `PermitTravailDocumentUploadProcessor.php:32-36` pour autoriser leur dépôt au moment de la clôture.

### Phase 8 — R-11, R-13 (validation/notification)
23. **R-11** : ajouter un champ `consultedAt`/`consultedBy` sur `DocumentPrevention` (ou une table `ConsultationLog` séparée si plusieurs consultations doivent être tracées) ; endpoint dédié (ex. `POST /plans-prevention/{id}/documents/{docId}/consulter`) ; condition dans `PlanPreventionVoter::checkStatutForHse` (L235-240) pour bloquer `VALIDER_HSE`/`REFUSER_HSE` tant que tous les documents/photos ne sont pas marqués consultés.
24. **R-13** : ajouter un flag `interne`/`isToa` (bool) sur `Entreprise`, seedé à `true` uniquement pour l'entreprise TOA ; modifier `findHseTeamFor()` dans `PlanPreventionNotificationService.php` (L157-165) et `PermitTravailNotificationService.php` (méthode homonyme) pour cibler les `ROLE_HSE` de l'entreprise marquée `isToa = true` au lieu de l'entreprise du créateur du plan/permis.

### Phase 9 — R-24, R-25, R-26 (dashboard)
25. **R-24** : retirer `tauxCloture`, `nbPvValides` (variable interne), `tempsMoyenValidationPv` du payload retourné par `computeGlobal()` (`DashboardKpisService.php:261-274`) et de `InterventionKpisController.php:37,45` ; vérifier aussi `KpiIntervention`/`UpdateKpisHandler.php` si l'indicateur y est également persisté et exposé via `kpiToArray()` (L310-323).
26. **R-25** : ajouter `nbPermisTotal` (`COUNT(*) FROM permit_travail` sans filtre de décision, avec les mêmes filtres site/période) dans `computeGlobal()`.
27. **R-26** : ajouter une fonction de mapping `typeIntervention → couleur` (palette fixe) et l'exposer dans `computeGlobal()`/`computeSites()`, ou l'exposer directement au niveau de `ActivityPlanning`/`PlanPrevention` pour un rendu front cohérent hors dashboard aussi.

### Phase 10 — R-28 (notifications)
28. Ajouter un paramètre `frontend_base_url` (env `FRONTEND_URL`) dans `config/services.yaml` ; construire l'URL de l'écran de validation (`{FRONTEND_URL}/prevention/{id}` ou équivalent front) et l'injecter dans le contexte Twig de `a_valider.html.twig`/`soumis.html.twig`/`refuse.html.twig` (bouton CTA), et dans les méthodes `build*Html()` de `PermitTravailNotificationService.php`.

### Phase 11 — Nettoyage
29. Renommer `src/Api/Controller/ClotureManuelleProcesoor.php` → `ClotureManuelleProcessor.php` (classe + fichier), corriger les références (`security.yaml` non concerné, aucune référence par nom de classe trouvée ailleurs qu'auto-wiring Symfony — vérifier le cache compilé après renommage).

---

## 5. Risques / points d'attention techniques

1. **Deux systèmes de permissions coexistent** sans passerelle commune : le couple `Menu`/`MenuAccess` + `PermissionChecker->isGranted(roles, route, action)` (utilisé par `ActivityPlanningVoter`, `PlanPreventionVoter`) versus le couple `ActionKey`/`RoleAction` + `PermissionChecker->getRoleActions(roles, actionKey)` (utilisé par `PermitTravailVoter`, `ControleJournalierVoter`, etc.). Les deux sont fonctionnellement redondants mais gérés par deux commandes de seed différentes (`SeedMenuCommand`, `SeedRoleActionCommand`) — risque de divergence silencieuse si l'une est mise à jour sans l'autre lors de l'implémentation des retours R-04/R-05/R-08.
2. **`config/packages/workflow.yaml` est vide** (`framework: { workflows: [] }`). Contrairement à ce que suggère `Taches_toa-back.md` (qui parle de « brancher `permit_workflow` »), le projet a en réalité abandonné le composant Symfony Workflow au profit d'un état géré par enum (`StatutPermitTravail`, `StatutPlanPrevention`) + Processors dédiés par transition. C'est cohérent et fonctionnel, mais **la documentation existante (Taches_toa-back.md, Audit_Conformite_TOA.md) est trompeuse sur ce point** et devrait être corrigée pour éviter qu'un développeur tente de réintroduire le composant Workflow inutilement.
3. **Rôles référencés mais absents de `security.yaml`** : `security.yaml:2-7` ne définit que `ROLE_CHEF_PROJET`, `ROLE_HSE`, `ROLE_DIRECTION`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` dans `role_hierarchy`. Or `SeedMenuCommand.php` référence aussi `ROLE_AGENT_TERRAIN` et `ROLE_COLLABORATEUR` (ex. L63, L75), et `PermitTravailVoter`/`CurrentUserExtension` traitent `ROLE_COLLABORATEUR` comme un rôle métier à part entière. Sans entrée dans la hiérarchie, ces rôles ne bénéficient d'aucun héritage implicite — à clarifier avec TOA (point déjà remonté dans l'ancien `Taches_toa-back.md` §10, toujours d'actualité).
4. **Le fichier `src/Api/Controller/ClotureManuelleProcesoor.php` (typo confirmé) est un contrôleur actif et routé** (`#[Route('/api/permits-travail/{permitId}/cloturer', ...)]`), pas du code mort — le renommage (Phase 11) doit être fait avec précaution (vider `var/cache`, vérifier qu'aucune référence par nom de classe ne subsiste ailleurs).
5. **`PlanPreventionCreateProcessor::validateDates`** (L95-103) ne vérifie que `dateFin > dateDebut`, sans contrôle de cohérence avec les dates de la `ActivityPlanning` parente (`planificationId`) — à surveiller si R-04 modifie le flux de création (le plan de prévention pourrait alors avoir des dates incohérentes avec la planification si aucun contrôle croisé n'est ajouté).
6. **Aucun test automatisé n'a pu être exécuté** (mission en lecture seule) — les modifications proposées en §4 (en particulier R-04, R-08, R-20, R-23) touchent des chemins critiques (soumission, clôture) et devraient être couvertes par des tests d'intégration avant mise en production, conformément à `Taches_toa-back.md` §12 (toujours pertinent).
7. **`nbPermisValides` vs `nbPermisTotal`** (Dashboard) : le renommage/ajout proposé en Phase 9 doit être coordonné avec le front car `nbPermisValides` est un nom déjà consommé — ne pas le supprimer par erreur en ajoutant `nbPermisTotal`.
