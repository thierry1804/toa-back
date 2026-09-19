<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Entity;

use App\Domain\PermitTravail\Enum\ActionPermitTravailLog;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\PermitTravail\Repository\PermitTravailLogRepository;
use App\Domain\User\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PermitTravailLogRepository::class)]
#[ORM\Table(name: 'permit_travail_log')]
#[ORM\Index(columns: ['permit_travail_id', 'created_at'], name: 'idx_pt_log_permit_date')]
#[ORM\Index(columns: ['code_site', 'type_permis'], name: 'idx_pt_log_site_type')]
#[ORM\Index(columns: ['action', 'created_at'], name: 'idx_pt_log_action')]
class PermitTravailLog
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['permit_travail_log:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: PermitTravail::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PermitTravail $permitTravail = null;

    #[ORM\Column(length: 50, enumType: ActionPermitTravailLog::class)]
    #[Groups(['permit_travail_log:read'])]
    private ActionPermitTravailLog $action;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['permit_travail_log:read'])]
    private ?User $declenchePar = null;

    #[ORM\Column(length: 100)]
    #[Groups(['permit_travail_log:read'])]
    private string $codeSite = '';

    #[ORM\Column(length: 50, enumType: TypePermitTravail::class)]
    #[Groups(['permit_travail_log:read'])]
    private TypePermitTravail $typePermis;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['permit_travail_log:read'])]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['permit_travail_log:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPermitTravail(): ?PermitTravail
    {
        return $this->permitTravail;
    }

    public function setPermitTravail(PermitTravail $permitTravail): static
    {
        $this->permitTravail = $permitTravail;

        return $this;
    }

    public function getAction(): ActionPermitTravailLog
    {
        return $this->action;
    }

    public function setAction(ActionPermitTravailLog $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getDeclenchePar(): ?User
    {
        return $this->declenchePar;
    }

    public function setDeclenchePar(?User $declenchePar): static
    {
        $this->declenchePar = $declenchePar;

        return $this;
    }

    public function getCodeSite(): string
    {
        return $this->codeSite;
    }

    public function setCodeSite(string $codeSite): static
    {
        $this->codeSite = $codeSite;

        return $this;
    }

    public function getTypePermis(): TypePermitTravail
    {
        return $this->typePermis;
    }

    public function setTypePermis(TypePermitTravail $typePermis): static
    {
        $this->typePermis = $typePermis;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
