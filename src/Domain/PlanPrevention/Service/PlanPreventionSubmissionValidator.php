<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Service;

use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\TypeDocumentPrevention;
use App\Domain\Referentiel\Entity\CategorieRisque;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PlanPreventionSubmissionValidator
{
    public const REQUIRED_DOCUMENT_TYPES = [
        TypeDocumentPrevention::PLAN_URGENCE,
        TypeDocumentPrevention::FDS,
        TypeDocumentPrevention::LISTE_INTERVENANTS,
        TypeDocumentPrevention::ATTESTATION_HSE,
        TypeDocumentPrevention::FICHE_CONFORMITE,
        TypeDocumentPrevention::LISTE_VEHICULES,
    ];

    /**
     * R-09 : types des photos jointes ligne par ligne aux listes installations/
     * matériels et équipements/outillages (onglet « Installations & Équipements »),
     * obligatoires uniquement pour le processus « Nouveau site » (cf.
     * assertRowPhotos). Le marquage « non applicable » (R-18) n'est pas accepté
     * pour ces 2 types tant que le plan est « Nouveau site » — cf.
     * PlanPreventionDocumentNonApplicableController.
     *
     * @var TypeDocumentPrevention[]
     */
    public const REQUIRED_DOCUMENT_TYPES_NOUVEAU_SITE = [
        TypeDocumentPrevention::PHOTOS_INSTALLATIONS,
        TypeDocumentPrevention::PHOTOS_EQUIPEMENTS,
    ];

    public const TYPE_INTERVENTION_NOUVEAU_SITE = 'Nouveau site';

    public function assertSubmittable(PlanPrevention $plan): void
    {
        $missingTypes = $this->findMissingDocumentTypes($plan, self::REQUIRED_DOCUMENT_TYPES);
        if ($missingTypes !== []) {
            throw new UnprocessableEntityHttpException(sprintf(
                'documents_manquants: %s',
                implode(', ', array_map(static fn (TypeDocumentPrevention $t) => $t->value, $missingTypes)),
            ));
        }

        if ($plan->getTypeIntervention() === self::TYPE_INTERVENTION_NOUVEAU_SITE) {
            $this->assertRowPhotos($plan);
        }

        $missingRiskTypes = array_diff($this->requiredRiskTypes($plan), $this->presentRiskTypes($plan));
        if ($missingRiskTypes !== []) {
            throw new UnprocessableEntityHttpException(
                sprintf('risque_apn_api_obligatoire: %s', implode(', ', $missingRiskTypes)),
            );
        }
    }

    /**
     * R-09 : pour « Nouveau site », chaque ligne des deux listes porte sa photo
     * (photoDocumentId → document du plan du type attendu, fichier réel).
     * Message : photos_lignes_manquantes: installations:1,3; equipements:2
     * (numéros de ligne à partir de 1).
     */
    private function assertRowPhotos(PlanPrevention $plan): void
    {
        $missing = [];
        foreach ([
            'installations' => [$plan->getInstallations(), TypeDocumentPrevention::PHOTOS_INSTALLATIONS],
            'equipements'   => [$plan->getEquipements(), TypeDocumentPrevention::PHOTOS_EQUIPEMENTS],
        ] as $list => [$rows, $type]) {
            $photoIds = $this->photoDocumentIds($plan, $type);
            $lines = [];
            foreach (array_values($rows) as $index => $row) {
                $photoId = is_array($row) ? (string) ($row['photoDocumentId'] ?? '') : '';
                if ($photoId === '' || !in_array($photoId, $photoIds, true)) {
                    $lines[] = $index + 1;
                }
            }
            if ($lines !== []) {
                $missing[] = sprintf('%s:%s', $list, implode(',', $lines));
            }
        }

        if ($missing !== []) {
            throw new UnprocessableEntityHttpException('photos_lignes_manquantes: ' . implode('; ', $missing));
        }
    }

    /** @return list<string> */
    private function photoDocumentIds(PlanPrevention $plan, TypeDocumentPrevention $type): array
    {
        $ids = [];
        foreach ($plan->getDocuments() as $document) {
            if ($document->getType() === $type && !$document->isNonApplicable() && $document->getId() !== null) {
                $ids[] = (string) $document->getId();
            }
        }

        return $ids;
    }

    /** @return list<string> Types d'aire protégée (APN, API) portés par les sites du plan. */
    private function requiredRiskTypes(PlanPrevention $plan): array
    {
        $types = [];
        foreach ($plan->getSitesApnApi() as $site) {
            if ($site['apn']) {
                $types['APN'] = 'APN';
            }
            if ($site['api']) {
                $types['API'] = 'API';
            }
        }

        return array_values($types);
    }

    /** @return list<string> Types APN/API couverts par un risque précis (hors « Autre(s) risque(s) … à préciser »). */
    private function presentRiskTypes(PlanPrevention $plan): array
    {
        $types = [];
        foreach ($plan->getRisques() as $risque) {
            foreach ($risque->getCategoriesRisque() as $categorie) {
                if ($this->isGenericCategory($categorie)) {
                    continue;
                }
                $type = $this->resolveTypeSite($categorie);
                if ($type !== null) {
                    $types[$type] = $type;
                }
            }
        }

        return array_values($types);
    }

    private function isGenericCategory(CategorieRisque $categorie): bool
    {
        return preg_match('/^\s*autre\(s\)/iu', (string) $categorie->getNom()) === 1;
    }

    private function resolveTypeSite(CategorieRisque $categorie): ?string
    {
        for ($current = $categorie; $current instanceof CategorieRisque; $current = $current->getParent()) {
            if (in_array($current->getTypeSite(), ['APN', 'API'], true)) {
                return $current->getTypeSite();
            }
        }

        return null;
    }

    /**
     * Types requis pour lesquels aucun fichier n'est présent et qui ne sont pas
     * déclarés « non applicables ».
     *
     * @param TypeDocumentPrevention[] $required
     * @return TypeDocumentPrevention[]
     */
    public function findMissingDocumentTypes(PlanPrevention $plan, array $required): array
    {
        $satisfied = [];
        foreach ($plan->getDocuments() as $document) {
            $satisfied[] = $document->getType();
        }

        return array_values(array_filter(
            $required,
            static fn (TypeDocumentPrevention $type) => !in_array($type, $satisfied, true),
        ));
    }

    public function hasApnApiRisk(PlanPrevention $plan): bool
    {
        return $this->presentRiskTypes($plan) !== [];
    }
}
