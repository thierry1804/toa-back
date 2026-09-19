# Handoff — Retours client TOA du 08/09/2026 (reprise sous Linux)

> **État au 2026-09-19 (fin de session Linux)** : migrations jouées, back et front testés de bout en bout (plan, permis, clôture, R-03/07/09/10/11/13/19/20/22/23/24/27, PDF, emails), mode hors-ligne complet pour la lecture et la validation HSE (voir `Offline*` côté back, `src/offline/` côté front), stratégie de cache PWA revue (réseau d'abord pour l'API). Restent à faire : test sur téléphone (caméra, compression), parcours prestataire hors-ligne, validation client des points de la section 4.

Date : 2026-09-19. Branche de travail : `feature/retours-2026-09-08` dans `toa-back` ET `toa-front` (créée depuis `develop`).

## 0. Avant tout : transférer le travail

Tout est en **working tree, non commité** (back : ~82 fichiers, front : ~55 fichiers, dont les fichiers non suivis). Sous Windows, dernier commit back `aeb8116`, front `1b3556e`. Il faut soit **commiter puis pousser les deux branches**, soit copier les deux dossiers tels quels. Ne pas oublier les fichiers non suivis (`API_CONTRAT_RETOURS.md`, `GAP_ANALYSIS_RETOURS_2026-09-08.md`, ce fichier, les nouveaux composants front `src/components/documents/*`, `src/utils/*`, `src/test/renderHelpers.tsx`, migrations `Version20260915090000` et `Version20260916090000`).

Fichiers hors git à copier à la main depuis `D:\TOA` : `Retours_Consolides_2026-09-08.md`, `Taches_toa-back.md`, `Taches_toa-front.md`, `Audit_Conformite_TOA_develop_2026-08-11.md`, dossier `retours/`.

**Fins de ligne :** le front mélange CRLF et LF (`core.autocrlf=true` sous Windows, pas de `.gitattributes`). Sous Linux, vérifier `git diff --stat` avant de commiter : si des fichiers apparaissent modifiés sans changement réel, c'est un bruit de fins de ligne, ne pas le commiter.

## 1. Back (`toa-back`)

Stack : Symfony 7.4, API Platform, PHP >= 8.3, PostgreSQL 16 (le `docker-compose.yml` fournit `toa_php`, `toa_postgres`, `toa_redis`, `toa_nginx`, `toa_mercure`, `toa_gotenberg`, `toa_minio`, workers notifications/pdf, scheduler).

1. `docker compose up -d`, `composer install` (dans le conteneur `toa_php`).
2. **Sur une copie de la base** : `php bin/console doctrine:migrations:migrate`. Les deux migrations (`Version20260915090000` : schéma phases/modes opératoires, reprise des sections de planification avec remap de `risque_prevention.tache_planifiee_id`, backfill `validated_at`/`captured_at`, `entreprise.interne = TRUE` pour les noms commençant par `toa` ; `Version20260916090000` : `DROP COLUMN IF EXISTS temps_moyen_validation_pv`) ont été jouées avec succès le 2026-09-19 (copie de la base puis base locale) ; `doctrine:schema:validate` OK.
3. Seeds : `php bin/console app:categorie-risque:seed` (catégories racines APN/API avec `typeSite`; libellés à faire valider par TOA). Les seeds menu / role-action n'ont pas changé.
4. Variables : définir `FRONTEND_URL` (défaut `http://localhost:5173`, non déclaré dans `docker-compose.yml`).
5. Tests : `vendor/bin/phpunit` → attendu 99 tests, **1 échec préexistant sans lien** (`EntrepriseNameCoherenceValidatorTest::testHseWithEntrepriseNameIsInvalid`). Aussi `lint:container`, `lint:twig`, `lint:yaml`.
6. Le contrat complet des endpoints/champs/erreurs est dans `API_CONTRAT_RETOURS.md` (à lire avant de tester).

### Scénarios testés de bout en bout le 2026-09-19 (HTTP + base)
- PATCH `sections` d'un plan (réconciliation par id, déplacement d'un mode opératoire vers une nouvelle phase — le re-parentage réel Doctrine n'a été testé qu'avec un `UnitOfWork` simulé).
- Gardes 422 : `risque_apn_api_obligatoire`, `documents_non_consultes`, `permit_travail.general_requis`, `captured_at_*`.
- Upload multi-fichiers par type, DELETE de document, N/A.
- Clôture permis : photos avant/après + pièces `ENV_*` (Général sur site APN/API uniquement).
- Notification HSE : reçue seulement par les HSE des entreprises `interne = true`, repli avec log `warning`.
- Emails avec liens `{FRONTEND_URL}/prevention/{id}` et `/permits-travail/{id}`.
- Import KMZ avec colonnes `APN`/`API` (0/1) ; réimport sans colonnes = flags conservés.

### Bugs / dette connus
Corrigés le 2026-09-19 : (1) `sections` renvoyé par `PlanPreventionBySiteController` ; (2) N+1 de `GET /plans-prevention` (résolution groupée dans `ApnApiSiteResolver`). Restant : (3) contrôle « dates du plan ⊂ dates de la planification » non ajouté.

## 2. Front (`toa-front`)

React 19, Vite, TanStack Query, Zustand, i18next, Axios.

1. `npm ci`, puis `npx tsc --noEmit -p tsconfig.app.json`, `npm run build`, `npx vitest run` (attendu 197 tests OK), eslint.
2. `npm run dev` et tester dans le navigateur (fait le 2026-09-19 dans le navigateur, sauf la caméra sur téléphone) : login (logos ISO, titre « Gestion de plan de prévention et permis »), planification sans bloc sections, wizard plan de prévention (phases/modes opératoires, alerte APN/API multi-sites, blocage R-10), prise de photo sur mobile/devtools sur les 3 modules (plan, permis, suivi journalier), galerie multi-photos + N/A + suppression, clôture permis (2 photos + `ENV_*`), consultation des pièces avant Examiner/Valider/Refuser, liste des plans sans boutons de validation, dashboard (compteur permis, couleurs Gantt).
3. Décisions déjà prises : fichier importé envoie `capturedAt` (refusé au-delà de 30 jours, message dédié fr/mg) ; `?? false` conservé sur les droits de création ; `KmzImportButton` supprimé (`parseKmzFile` n'a plus de consommateur en production).

## 3. Récapitulatif des retours (R-xx) et décisions

- R-01/R-02 logos ISO + titre (déjà sur `develop` avant l'implémentation), R-03 suppression contrôle de conflit site/date (confirmé client), R-04/R-08 sections/phases/modes opératoires déplacés vers le plan de prévention, R-05 fail-open corrigé, R-06 import CSV déjà présent, R-07 filtre par entreprise (back + voter), R-09/R-10 alerte + obligation d'un risque du bon type APN/API (Option 1), R-11 consultation obligatoire (remise à zéro à l'Examiner), R-12 refus HSE existant, R-13 HSE de TOA (`Entreprise.interne`), R-14 actions retirées de la liste, R-15/R-16/R-17/R-18 photo directe + horodatage + multi-fichiers + N/A, R-19 heures de soumission et validation, R-20 permis Général obligatoire hors Nouveau site, R-21 « PV Environnement », R-22 photos avant/après clôture, R-23 pièces `ENV_*` conditionnées APN/API, R-24/R-25 dashboard, R-26 palette Gantt par type de projet, R-27 colonnes APN/API du KMZ, R-28 liens dans les emails.
- Sources détaillées : `Retours_Consolides_2026-09-08.md` (hors git), gap analysis des deux repos, `API_CONTRAT_RETOURS.md`.

## 4. À fournir / valider côté TOA
- KMZ de référence avec `ExtendedData` `APN` / `API` (0/1) ; sans réimport tous les sites ont `apn = api = false`.
- Libellés des catégories de risque APN/API du seed.
- `FRONTEND_URL` de production ; flag `Entreprise.interne` de TOA.
- Question client ouverte R-12 : le chef de projet peut-il refuser à l'étape Examiner ? R-16 : incrustation visible de l'horodatage dans l'image ?
- Charge R-10 + R-23 (2 j.h annoncés, 1 j.h facturé) à revalider vu le multi-site et l'obligation renforcée.
