<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Service;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\ProcessusPermitTravail;
use App\Domain\Referentiel\Entity\DocumentPermisRegle;
use App\Domain\Referentiel\Repository\DocumentPermisRegleRepository;
use App\Domain\Referentiel\Repository\SiteRepository;
use App\Domain\Referentiel\Service\DocumentPermisCatalogue;

/**
 * Liste des documents d'un permis, lue dans le référentiel.
 * Tant que la table est vide, la liste de départ du catalogue s'applique.
 */
final class PermitDocumentRequirementResolver
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly ?DocumentPermisRegleRepository $regles = null,
    ) {
    }

    /** @return list<array{code: string, libelle: string, obligatoire: bool, ordre: int}> */
    public function soumission(PermitTravail $permit): array
    {
        return $this->lignes($permit, DocumentPermisCatalogue::MOMENT_SOUMISSION);
    }

    /** @return list<array{code: string, libelle: string, obligatoire: bool, ordre: int}> */
    public function cloture(PermitTravail $permit): array
    {
        return $this->lignes($permit, DocumentPermisCatalogue::MOMENT_CLOTURE);
    }

    /** @return array{code: string, libelle: string, obligatoire: bool, ordre: int}|null */
    public function dangers(PermitTravail $permit): ?array
    {
        return $this->lignes($permit, DocumentPermisCatalogue::MOMENT_DANGERS)[0] ?? null;
    }

    /** @return string[] codes obligatoires */
    public function getRequiredDocumentTypes(PermitTravail $permit): array
    {
        return $this->codesObligatoires($this->soumission($permit));
    }

    /** @return string[] codes obligatoires */
    public function getRequiredClotureDocumentTypes(PermitTravail $permit): array
    {
        return $this->codesObligatoires($this->cloture($permit));
    }

    /** @return string[] */
    public function findMissingDocumentTypes(PermitTravail $permit): array
    {
        return $this->findMissing($permit, $this->getRequiredDocumentTypes($permit));
    }

    /** @return string[] */
    public function findMissingClotureDocumentTypes(PermitTravail $permit): array
    {
        return $this->findMissing($permit, $this->getRequiredClotureDocumentTypes($permit));
    }

    public function findRegle(PermitTravail $permit, string $code): ?DocumentPermisRegle
    {
        foreach ($this->correspondantes($permit) as $regle) {
            if ($regle->getCode() === $code) {
                return $regle;
            }
        }

        return null;
    }

    public function isClotureCode(PermitTravail $permit, string $code): bool
    {
        foreach ($this->correspondantes($permit, DocumentPermisCatalogue::MOMENT_CLOTURE) as $regle) {
            if ($regle->getCode() === $code) {
                return true;
            }
        }

        return false;
    }

    public function isApnApiSite(PermitTravail $permit): bool
    {
        $codeSite = $permit->getCodeSite();
        if ($codeSite === null || $codeSite === '') {
            return false;
        }

        $site = $this->siteRepository->findByCodeSite($codeSite);

        return $site !== null && ($site->isApn() || $site->isApi());
    }

    /** @return list<array{code: string, libelle: string, obligatoire: bool, ordre: int}> */
    private function lignes(PermitTravail $permit, string $moment): array
    {
        $lignes = [];
        foreach ($this->correspondantes($permit, $moment) as $regle) {
            $lignes[] = $regle->toLigne();
        }

        return $lignes;
    }

    /** @return DocumentPermisRegle[] */
    private function correspondantes(PermitTravail $permit, ?string $moment = null): array
    {
        $bucket = $permit->getPlanProcess() === 'Nouveau site'
            ? DocumentPermisCatalogue::NOUVEAU_SITE
            : DocumentPermisCatalogue::AUTRES;
        $demande = $permit->getProcessus() === ProcessusPermitTravail::RENOUVELLEMENT
            ? DocumentPermisCatalogue::RENOUVELLEMENT
            : DocumentPermisCatalogue::PREMIERE;
        $type = $permit->getType()?->value ?? '';
        $apn = $this->isApnApiSite($permit);

        $matched = [];
        foreach ($this->source() as $regle) {
            if (!$regle->isActif()) {
                continue;
            }
            if ($moment !== null && $regle->getMoment() !== $moment) {
                continue;
            }
            if (!in_array($regle->getProcessus(), [$bucket, DocumentPermisCatalogue::TOUS], true)) {
                continue;
            }
            if (!in_array($regle->getTypePermis(), [$type, DocumentPermisCatalogue::TOUS], true)) {
                continue;
            }
            if (!in_array($regle->getDemande(), [$demande, DocumentPermisCatalogue::TOUTES], true)) {
                continue;
            }
            if ($regle->isApnApiUniquement() && !$apn) {
                continue;
            }
            $matched[] = $regle;
        }

        usort($matched, static fn (DocumentPermisRegle $a, DocumentPermisRegle $b): int => $a->getOrdre() <=> $b->getOrdre());

        return $matched;
    }

    /** @return DocumentPermisRegle[] */
    private function source(): array
    {
        if ($this->regles !== null && $this->regles->countAll() > 0) {
            return $this->regles->findActives();
        }

        $built = [];
        foreach (DocumentPermisCatalogue::definitions() as $row) {
            $built[] = (new DocumentPermisRegle())
                ->setCode($row['code'])
                ->setLibelle($row['libelle'])
                ->setMoment($row['moment'])
                ->setProcessus($row['processus'])
                ->setTypePermis($row['typePermis'])
                ->setDemande($row['demande'])
                ->setApnApiUniquement($row['apnApiUniquement'])
                ->setObligatoire($row['obligatoire'])
                ->setOrdre($row['ordre'])
                ->setActif(true);
        }

        return $built;
    }

    /**
     * @param list<array{code: string, obligatoire: bool}> $lignes
     * @return string[]
     */
    private function codesObligatoires(array $lignes): array
    {
        $codes = [];
        foreach ($lignes as $ligne) {
            if ($ligne['obligatoire']) {
                $codes[] = $ligne['code'];
            }
        }

        return $codes;
    }

    /**
     * Une case N/A ne remplace pas un document obligatoire.
     *
     * @param string[] $required
     * @return string[]
     */
    private function findMissing(PermitTravail $permit, array $required): array
    {
        $present = [];
        foreach ($permit->getDocuments() as $document) {
            if ($document->isNonApplicable() || $document->getFilePath() === null || $document->getFilePath() === '') {
                continue;
            }
            $present[] = $document->getType();
        }

        return array_values(array_filter(
            $required,
            static fn (string $code): bool => !in_array($code, $present, true),
        ));
    }
}
