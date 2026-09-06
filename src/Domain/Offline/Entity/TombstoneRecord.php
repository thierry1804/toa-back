<?php

declare(strict_types=1);

namespace App\Domain\Offline\Entity;

use App\Domain\Offline\Repository\TombstoneRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace la suppression d'une entité métier (entityType + entityId) pour permettre
 * au snapshot hors-ligne de renvoyer des `tombstones` lors d'une sync incrémentale
 * (`since`). Écrit par TombstoneRecorderSubscriber sur postRemove Doctrine.
 */
#[ORM\Entity(repositoryClass: TombstoneRecordRepository::class)]
#[ORM\Table(name: '`tombstone_record`')]
#[ORM\Index(columns: ['entity_type', 'deleted_at'], name: 'idx_tombstone_type_deleted_at')]
class TombstoneRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $entityType;

    #[ORM\Column(length: 36)]
    private string $entityId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $deletedAt;

    public function __construct(string $entityType, string $entityId, \DateTimeImmutable $deletedAt)
    {
        $this->entityType = $entityType;
        $this->entityId   = $entityId;
        $this->deletedAt  = $deletedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getDeletedAt(): \DateTimeImmutable
    {
        return $this->deletedAt;
    }
}
