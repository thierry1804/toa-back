<?php

declare(strict_types=1);

namespace App\Domain\Offline\EventSubscriber;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Offline\Entity\TombstoneRecord;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\Referentiel\Entity\CategorieRisque;
use App\Domain\Referentiel\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

/**
 * Trace toute suppression des entités couvertes par le snapshot hors-ligne, pour
 * permettre à `GET /api/offline/snapshot` de renvoyer des `tombstones` lors d'une
 * sync incrémentale (`since`). Générique : couvre aussi les modules qui n'ont pas
 * encore de endpoint de suppression exposé aujourd'hui (PlanPrevention, Intervention,
 * Site) — si un tel endpoint est ajouté plus tard, les tombstones fonctionnent sans
 * modification.
 *
 * Buffer en preRemove (l'id/l'identité de l'entité doit être capturé avant suppression
 * effective) puis persistance en postFlush : on ne persiste/flush jamais à l'intérieur
 * du cycle de flush en cours (cf. PermitTravailLogSubscriber, même convention).
 */
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class TombstoneRecorderSubscriber
{
    private const ENTITY_TYPE_MAP = [
        PlanPrevention::class  => 'plan_prevention',
        PermitTravail::class   => 'permit_travail',
        Intervention::class    => 'intervention',
        ActivityPlanning::class => 'activity_planning',
        Site::class             => 'site',
        CategorieRisque::class  => 'categorie_risque',
    ];

    /** @var list<array{type: string, id: string}> */
    private array $pending = [];

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        $class  = $entity::class;

        if (!isset(self::ENTITY_TYPE_MAP[$class])) {
            return;
        }

        if (!method_exists($entity, 'getId')) {
            return;
        }

        $id = $entity->getId();
        if ($id === null) {
            return;
        }

        $this->pending[] = ['type' => self::ENTITY_TYPE_MAP[$class], 'id' => (string) $id];
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (empty($this->pending)) {
            return;
        }

        $pending = $this->pending;
        $this->pending = [];

        $em  = $args->getObjectManager();
        $now = new \DateTimeImmutable();

        foreach ($pending as $entry) {
            $em->persist(new TombstoneRecord($entry['type'], $entry['id'], $now));
        }

        try {
            $em->flush();
        } catch (\Throwable) {
            // Le tracking des tombstones ne doit jamais casser la suppression elle-même.
        }
    }
}
