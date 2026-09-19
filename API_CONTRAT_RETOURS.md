# Contrat API — Retours client 08/09/2026 (branche `feature/retours-2026-09-08`)

Tous les chemins sont préfixés par `/api`. Auth JWT inchangée. Erreurs métier = HTTP 422 avec `detail` = clé (ex. `documents_non_consultes: FDS, PLAN_URGENCE`).

## 1. Sites APN / API (R-27, R-09)

- `Site` (référentiel) et `SitePrevention` : nouveaux champs booléens `apn`, `api` (défaut `false`), exposés partout où le site est sérialisé (`site:read`, `plan_prevention:read`, listes/recherche/GeoJSON du référentiel, snapshot offline).
- Import KMZ (référentiel `POST /referentiel/sites/import-kmz` et plan `POST /plans-prevention/{id}/import-kmz`) : lit les `ExtendedData/Data` de nom `APN` / `API` (insensible à la casse), valeurs `0`/`1` (aussi `true`/`false`/`oui`/`non`). Colonne **absente ou cellule vide** = « inconnu » : un **nouveau** site prend `false`, mais un site **déjà existant du référentiel conserve** ses `apn`/`api` (seule une valeur explicite `0`/`1` les modifie).
- Le payload de retour des deux imports contient `apn` et `api` (booléens) par site.

## 2. Plan de prévention

Nouveaux champs en lecture (`GET /plans-prevention`, `GET /plans-prevention/{id}`) :

| champ | type | note |
|---|---|---|
| `soumisAt` | ISO 8601 \| null | posé à `POST /plans-prevention/{id}/soumettre` et `/resoumettre` |
| `validatedAt` | ISO 8601 \| null | posé à `POST /plans-prevention/{id}/valider` (HSE) |
| `hasApnApiSite` | bool (lecture seule) | vrai si un site du plan (SitePrevention ou site du référentiel via `codeSite` / `planificationSites`) a `apn` ou `api` |
| `sitesApnApi` | `[{codeSite, nomSite, apn, api}]` (lecture seule) | sites concernés |
| `tousDocumentsConsultes` | bool (lecture seule) | R-11 |
| `sections` | `Section[]` | phases + modes opératoires **du plan** (R-04/R-08) |

`Section` = même forme que `ActivityPlanning.sections` avant refonte :
```json
{ "id": "uuid", "libelle": "Phase 1", "ordre": 0,
  "taches": [ { "id": "uuid", "ordre": 0, "tache": "TERRASSEMENT DU SITE", "materiel": "…", "qui": "…" } ] }
```
(`taches[]` = modes opératoires ; le nom JSON `tache` est conservé.)

**Écriture** : `sections` est acceptée en JSON dans le corps de `POST /plans-prevention` et `PATCH /plans-prevention/{id}` (Content-Type `application/merge-patch+json` pour PATCH), tableau **complet** = état cible :
- élément avec `id` existant → mis à jour (les ids des modes opératoires sont conservés, donc les risques déjà rattachés restent valides) ;
- élément sans `id` → créé ; élément existant absent du tableau → supprimé ;
- `sections` omis → aucune modification. Modifiable uniquement en statut `BROUILLON`.
- Les `id` des sections/modes créés sont renvoyés dans la réponse.

`RisquePrevention.tachePlanifieeId` (nom JSON inchangé) référence désormais l'`id` d'un mode opératoire (`sections[].taches[].id`) **du même plan** (validé côté back ; 422 `tache_planifiee_introuvable` sinon). Nullable.

`planificationSections` : conservé en lecture seule uniquement pour les plans sans `sections` propres (legacy) ; ne plus l'utiliser pour la saisie.

`ActivityPlanning` : `sections` n'est **plus écrivable** (ignoré en POST/PUT/PATCH) ; reste lisible (historique).

### Règles de soumission (`POST /plans-prevention/{id}/soumettre`, `/resoumettre`)
- Existant : documents requis (`documents_manquants: …`) — les types marqués « non applicable » (voir §4) ne comptent pas comme manquants.
- **R-10 (Option 1)** : le risque exigé dépend du type des sites : site `apn` ⇒ au moins un risque de catégorie `typeSite = APN` ; site `api` ⇒ au moins un risque `typeSite = API` (les deux si le plan couvre les deux). Les catégories génériques « Autre(s) risque(s) … à préciser » ne comptent pas. Sinon 422 `risque_apn_api_obligatoire: APN` / `: API` / `: APN, API` (types manquants).
- `hasApnApiSite` / `sitesApnApi` sont calculés **à la demande** (lecture/sérialisation ou validation), plus au chargement de chaque plan ; la correspondance `codeSite` avec le référentiel est insensible à la casse.
- `CategorieRisque` : nouveau champ `typeSite` (`"APN"` | `"API"` | null), lecture dans les endpoints catégories de risque existants.

## 3. Permis de travail

- `PermitTravail` : `soumisAt` (existant) + `validatedAt` (ISO|null, posé à `/valider`).
- **R-20** : pour `processus` ≠ `NOUVEAU_SITE`, la soumission d'un permis `ELECTRIQUE`/`HAUTEUR` exige qu'un permis `GENERAL` du même couple plan/site ait été soumis (statut ≠ `BROUILLON`/`REJETE`/`REFUSE_HSE`), sinon 422 `permit_travail.general_requis`.
- **R-22** : nouveaux types de document `PHOTO_AVANT_TRAVAUX`, `PHOTO_APRES_TRAVAUX`, exigés à la clôture (`POST /permits-travail/{permitId}/cloturer`) pour tous les processus, en plus des documents de clôture existants. Déposables via l'upload document quand le permis est `VALIDE_HSE`/`EN_COURS`.
- **R-23** : nouveaux types de document environnementaux (ci-dessous), **obligatoires uniquement si le site du permis (`codeSite` → `Site`) est `apn` ou `api`** :
  - Permis général (avant travaux), Nouveau site : `ENV_TRI_DECHETS`, `ENV_DELIMITATION_SITE`, `ENV_ACCES_EXISTANT`, `ENV_AUTORISATION_CEF_DREDD`, `ENV_FICHE_TOOLBOX`, `ENV_INVENTAIRE_ESPECES`.
  - Permis général, autres processus : `ENV_MATERIELS_DEVERSEMENT`, `ENV_MOYENS_URGENCE_POLLUTION`, `ENV_PROPRETE_AVANT`.
  - Fin de travaux, Nouveau site : `ENV_PHOTO_GENERATEUR_SUPERSILENT`, `ENV_LUTTE_EROSION`, `ENV_REGISTRE_DECHETS`, `ENV_PROPRETE_SITE`, `ENV_REGISTRE_PLAINTES`.
  - Fin de travaux, autres processus : `ENV_QUANTITE_DECHETS`, `ENV_ENLEVEMENT_DECHETS`, `ENV_PROPRETE_APRES`.
  - Les types « permis général » sont exigés à la soumission (et à la resoumission) d'un permis de type `GENERAL` uniquement ; les types « fin de travaux » à la clôture du permis `GENERAL` uniquement (les permis `ELECTRIQUE`/`HAUTEUR` ne portent pas de pièce environnementale). Site APN/API = `Site` du référentiel dont le `codeSite` est celui du permis, avec `apn` ou `api` à `true`.
  - Les types `ENV_*` « fin de travaux » et `PHOTO_AVANT_TRAVAUX`/`PHOTO_APRES_TRAVAUX` sont déposables sur un permis `VALIDE_HSE`/`EN_COURS` (comme les autres documents de clôture) ; les types `ENV_*` « permis général » uniquement en `BROUILLON`.
  - Nouvel endpoint de lecture : `GET /permits-travail/{id}/documents-requis` → `{ "soumission": [types], "cloture": [types], "apnApi": bool }` — listes complètes des types requis (les N/A ne sont **pas** retirés : c'est au front de croiser avec `documents[].nonApplicable`).
  - Types de document toujours **multiples** : plusieurs fichiers d'un même type comptent pour un seul type satisfait.

## 4. Documents : photos, multi-fichiers, N/A, horodatage (R-15/16/17/18)

Concerne `DocumentPrevention` (`/plans-prevention/{planPreventionId}/documents`) et `PermitTravailDocument` (`/permits-travail/{permitTravailId}/documents`) ; `SuiviJournalierDocument` (`/suivis-journaliers/{suiviJournalierId}/documents`) reçoit `capturedAt` uniquement (pas de lignes typées, donc pas de N/A).

- Upload `POST …/documents` multipart : `file` (jpeg, png, webp, pdf ; 10 Mo), `type` (sauf suivi), `capturedAt` **optionnel** (chaîne ISO 8601 **avec fuseau**, ex. `2026-09-15T10:00:00+03:00` ou `…Z`, date de prise de vue ; défaut = heure serveur). Stocké converti au fuseau serveur. 422 : `captured_at_invalid` (tableau, format libre, sans fuseau…), `captured_at_in_future` (> maintenant + 5 min), `captured_at_too_old` (> 30 jours dans le passé).
- **Plusieurs documents par type** : un nouvel upload n'écrase plus l'existant.
- Réponse/serialisation d'un document : `id`, `type`, `filePath`, `mimeType`, `uploadedAt`, **`capturedAt`** (ISO), **`nonApplicable`** (bool), et pour le plan de prévention **`consultedAt`** (ISO|null).
- **DELETE** `…/documents/{id}` (UUID) → 204 ; supprime le fichier. Droits = ceux de l'upload (édition), plan en `BROUILLON` / permis en `BROUILLON` (ou documents de clôture sur permis `VALIDE_HSE`/`EN_COURS`).
- **N/A** : `POST …/documents/non-applicable` (JSON `{ "type": "FDS", "nonApplicable": true }`)
  - `true` : supprime les fichiers existants de ce type et crée un document « marqueur » (`nonApplicable: true`, `filePath: ""`, `mimeType: ""`) ; 201 avec ce document.
  - `false` : supprime le marqueur ; 204.
  - Un upload réel d'un type marqué N/A retire le marqueur.
  - Les contrôles bloquants (soumission, clôture) ignorent les types N/A.
- Les PDF générés (plan, permis, PV) listent toutes les photos d'un type.

## 5. Consultation des pièces (R-11)

- `POST /plans-prevention/{id}/documents/{docId}/consulter` (corps vide) → `{ "documentId", "consultedAt", "tousDocumentsConsultes" }`. Enregistre `consultedAt`/`consultedBy` (première consultation conservée). Seuls `ROLE_CHEF_PROJET`, `ROLE_HSE`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` enregistrent ; les autres rôles reçoivent l'état courant sans écriture.
- `tousDocumentsConsultes` = tous les documents non-N/A ont `consultedAt` (consultation enregistrée une seule fois par document). Les consultations sont remises à zéro à chaque `soumettre`/`resoumettre` **et à chaque `examiner` réussi** : le chef de projet consulte avant d'examiner, puis le HSE doit **re-consulter** chaque pièce avant `valider`/`refuser`. Un document déposé après coup (`consultedAt: null`) bloque à nouveau `tousDocumentsConsultes`.
- `POST /plans-prevention/{id}/examiner`, `/valider`, `/refuser` → 422 `documents_non_consultes: <types>` tant que `tousDocumentsConsultes` est faux.

## 6. Notifications (R-13, R-28)

- `Entreprise.interne` (bool, défaut `false`, lecture/écriture admin) : `true` uniquement pour TOA (migration : entreprise dont le nom commence par `toa`, insensible à la casse — **TOA doit vérifier le flag**). La notification « à valider » de plan et de permis part à **tous les `ROLE_HSE` des entreprises `interne = true`** ; si aucune n'a de HSE, **repli** sur les HSE de l'entreprise du créateur du plan/permis, avec un log `warning`.
- Emails : variable d'environnement `FRONTEND_URL` (ex. `https://app.toa.mg`) ; liens `{FRONTEND_URL}/prevention/{id}` (plans) et `{FRONTEND_URL}/permits-travail/{id}` (permis) dans tous les emails plan/permis.

## 7. Dashboard (R-24, R-25)

`GET /dashboard/kpis` (computeGlobal) : **ajout** `nbPermisTotal` (int, tous permis créés sur la période/site). **Retrait** de `tempsMoyenValidationPv` (global et `kpis` de `parSite`/`evolution`) ; le champ, son calcul (`UpdateKpisHandler`, agrégat du repository) et la colonne `kpi_intervention.temps_moyen_validation_pv` sont supprimés (migration `Version20260916090000`, aucun consommateur dans toa-front). **Conservés** : `tauxCloture`, `nbPermisValides`, `nbPermisClotures`, `nbPlansValides`, etc. `GET /interventions/kpis` inchangé. R-26 (couleurs par type de projet) : front uniquement.

## 8. Planification (R-03, R-07)

- `POST /activity_plannings` et mises à jour : plus de 409 `conflict_with_active_interventions` (plusieurs planifications même site/même date autorisées).
- `GET /plans-prevention` pour `ROLE_PRESTATAIRE` : filtré sur l'**entreprise** de l'utilisateur (tous les plans créés par des utilisateurs de la même entreprise). `PlanPreventionVoter` est aligné : un collègue de la même entreprise peut voir, modifier, soumettre et importer un KMZ sur le plan (plus de 403 sur un plan listé).

## Écarts au contrat initial (décidés à l'implémentation)

- `SuiviJournalierDocument` : `capturedAt` uniquement (pas de `nonApplicable`, ce module n'a pas de lignes de documents typées).
- `planificationSections` reste calculé pour tous les plans rattachés à une planification (lecture seule, héritage) : le front doit lire `sections`. Les plans existants ont été migrés (voir ci-dessous).
- Permis : les pièces `ENV_*` ne sont exigées que sur le permis `GENERAL` (voir §3).
- `DELETE …/documents/{id}` d'un permis : mêmes droits que l'upload (`PERMIT_TRAVAIL_EDIT` + garde statut/propriété), pas de nouveau droit de rôle → aucune modification des seeds `SeedMenuCommand` / `SeedRoleActionCommand`.
- `GET /interventions/kpis` conserve `delaiMoyenValidationCdp` (délai de validation CDP du PV) : non retiré, seul `tempsMoyenValidationPv` du dashboard global l'a été.

## Points ouverts / non vérifié

- **Migrations** : `Version20260915090000` (schéma + reprise des sections de planification dans `phase_plan_prevention` / `mode_operatoire_plan_prevention` avec remap de `risque_prevention.tache_planifiee_id`, backfill `validated_at` et `captured_at`, `entreprise.interne = TRUE` pour `LOWER(TRIM(nom)) LIKE 'toa%'`) et `Version20260916090000` (`DROP COLUMN IF EXISTS temps_moyen_validation_pv`) ont été jouées le 2026-09-19 sur une copie de la base puis sur la base locale (PG 16) ; `doctrine:schema:validate` OK. Tests en base réelle : PG ≥ 13 requis (`gen_random_uuid()`).
- **Testé de bout en bout (HTTP + base, 2026-09-19)** : cycle complet plan (sections/PATCH avec déplacement de mode opératoire, risques rattachés, documents multiples, N/A, suppression, `capturedAt` et ses 422, R-10, consultation R-11, examen, validation), permis (R-20, R-23, clôture avec photos avant/après), R-03, R-07, R-13 (destinataires et repli), R-24, import KMZ APN/API, génération PDF, emails avec liens `FRONTEND_URL`, snapshot hors-ligne (`/offline/snapshot/extras`), rejeu d'une action refusée. PHPUnit : 100 tests, seul échec préexistant sans lien : `EntrepriseNameCoherenceValidatorTest::testHseWithEntrepriseNameIsInvalid`.
- Corrigés à cette occasion : `sections` absent de `GET /plans-prevention/by-site` ; N+1 de `ApnApiSiteResolver` (résolution groupée, 2 requêtes quel que soit le nombre de plans) ; `PlanPrevention` non sérialisable (le loader paresseux APN/API empêchait l'envoi des emails de plan par Messenger) ; `typeSite` absent des catégories du snapshot hors-ligne ; le HSE d'une entreprise `interne` contourne désormais le filtre par entreprise (`EntrepriseScopeExtension`), sinon il ne voit pas les plans des prestataires dont il reçoit la notification.
- `soumis_at` des plans **historiques** reste `NULL` (aucune source fiable) ; seuls les plans soumis/resoumis après déploiement sont horodatés. `validated_at` est reconstruit depuis les décisions HSE.
- Catégories de risque APN/API : le seed `app:categorie-risque:seed` crée deux catégories racines (« Risques liés à une aire protégée nationale (APN) » / « …internationale (API) ») avec sous-catégories génériques ; **libellés à faire valider par TOA**. Sans catégorie portant `typeSite`, un plan sur site APN/API ne peut pas être soumis (R-10). Le flag est aussi modifiable via `PATCH /referentiel/categories-risque/{id}` (`typeSite`: `APN`|`API`|null).
- Données à fournir par TOA : KMZ de référence avec `ExtendedData` `APN` / `API` (0/1) ; sans réimport, tous les sites existants ont `apn = api = false` (aucune obligation R-09/R-10/R-23).
- `FRONTEND_URL` : défaut `http://localhost:5173` (paramètre `env(FRONTEND_URL)` dans `config/services.yaml`) — à définir en production (`docker-compose.yml` ne la déclare pas). Sans valeur, les emails partent sans lien ; les mails de planning (`PlanningMailer`) ne sont pas concernés.
- `Entreprise.interne` : si aucune entreprise interne n'a de HSE, repli sur les HSE de l'entreprise du créateur (log `warning`) ; vérifier le flag en production pour que les mails aillent bien aux HSE de TOA.
- Contrôle de cohérence dates du plan ⊂ dates de la planification (`validateDates`) : non ajouté (risque de régression sur les flux existants).
- Renommage `ClotureManuelleProcesoor.php` → `ClotureManuelleProcessor.php` : aucune référence par nom de classe ailleurs ; routes vérifiées (`debug:router`).
- Environnement local : PHP >= 8.3 requis (`vendor`) ; l'image Docker `toa_php` fournit 8.3 avec `ext-redis` (à reconstruire si l'image est ancienne). Variables à fournir hors git dans `.env` : `APP_SECRET`, `DEFAULT_URI`, `FRONTEND_URL`, `MAILER_DSN`, `MAILER_FROM_ADDRESS`, `MAILER_FROM_NAME`, `GOTENBERG_URL`, `MERCURE_PUBLIC_URL`.
