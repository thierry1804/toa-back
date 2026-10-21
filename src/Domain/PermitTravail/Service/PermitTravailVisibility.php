<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Service;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use App\Doctrine\Extension\CurrentUserExtension;
use App\Domain\PermitTravail\Entity\PermitTravail;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Périmètre de visibilité des permis hors des collections API Platform
 * (tableaux de bord de suivi) : même règle que la liste des permis
 * (CurrentUserExtension) — entreprise pour les profils prestataire, borne
 * R-35 pour le chef de projet prestataire, processus pour le chef de projet
 * TOA (R-32), aucune restriction pour HSE/admin.
 */
class PermitTravailVisibility
{
    public function __construct(
        private readonly CurrentUserExtension $extension,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** Restreint une requête dont la racine est un PermitTravail. */
    public function restrictPermits(QueryBuilder $qb): void
    {
        $this->extension->applyToCollection($qb, new QueryNameGenerator(), PermitTravail::class);
    }

    /** Restreint une requête de journaux (PermitTravailLog) aux permis visibles. */
    public function restrictLogs(QueryBuilder $qb, string $logAlias): void
    {
        $visible = $this->entityManager->createQueryBuilder()
            ->select('vis_p.id')
            ->from(PermitTravail::class, 'vis_p');
        $this->restrictPermits($visible);

        if ($visible->getDQLPart('where') === null) {
            return;
        }

        $qb->andWhere($qb->expr()->in(sprintf('%s.permitTravail', $logAlias), $visible->getDQL()));
        // Valeur seule : Doctrine en redéduit le type (une entité devient son identifiant).
        foreach ($visible->getParameters() as $parameter) {
            $qb->setParameter($parameter->getName(), $parameter->getValue());
        }
    }
}
