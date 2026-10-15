<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Repository;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PermitTravail>
 */
class PermitTravailRepository extends ServiceEntityRepository
{
    private const STATUTS_ACTIFS = [
        StatutPermitTravail::VALIDE,
        StatutPermitTravail::VALIDE_HSE,
        StatutPermitTravail::EN_COURS,
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PermitTravail::class);
    }

    /**
     * R-27 : permis Hauteur/Électrique actifs dont l'échéance tombe dans [$now, $seuil], sans
     * renouvellement déjà déposé, et pour lesquels aucun rappel n'a encore été envoyé.
     *
     * @return PermitTravail[]
     */
    public function findExpirantSansNotification(\DateTimeImmutable $now, \DateTimeImmutable $seuil): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.statut IN (:statuts)')
            ->andWhere('p.type IN (:types)')
            ->andWhere('p.dateFinPrevue IS NOT NULL')
            ->andWhere('p.dateFinPrevue BETWEEN :now AND :seuil')
            ->andWhere('p.notificationExpirationEnvoyeeAt IS NULL')
            ->andWhere(sprintf(
                'NOT EXISTS (SELECT 1 FROM %s r WHERE r.permisOrigine = p AND r.statut IN (:renouvellements))',
                PermitTravail::class,
            ))
            ->setParameter('statuts', self::STATUTS_ACTIFS)
            ->setParameter('types', [TypePermitTravail::HAUTEUR->value, TypePermitTravail::ELECTRIQUE->value])
            ->setParameter('renouvellements', [
                StatutPermitTravail::SOUMIS->value,
                StatutPermitTravail::VALIDE_HSE->value,
                StatutPermitTravail::EN_COURS->value,
                StatutPermitTravail::VALIDE->value,
            ])
            ->setParameter('now', $now)
            ->setParameter('seuil', $seuil)
            ->getQuery()
            ->getResult();
    }
}
