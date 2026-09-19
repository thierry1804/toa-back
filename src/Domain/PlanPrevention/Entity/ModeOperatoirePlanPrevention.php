<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'mode_operatoire_plan_prevention')]
#[ORM\Index(columns: ['phase_id', 'ordre'], name: 'idx_mode_pp_phase_ordre')]
class ModeOperatoirePlanPrevention
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['plan_prevention:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: PhasePlanPrevention::class, inversedBy: 'modesOperatoires')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PhasePlanPrevention $phase = null;

    #[ORM\Column]
    #[Groups(['plan_prevention:read'])]
    private int $ordre = 0;

    #[ORM\Column(length: 255)]
    #[Groups(['plan_prevention:read'])]
    private ?string $tache = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['plan_prevention:read'])]
    private ?string $materiel = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Groups(['plan_prevention:read'])]
    private ?string $qui = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPhase(): ?PhasePlanPrevention
    {
        return $this->phase;
    }

    public function setPhase(?PhasePlanPrevention $phase): static
    {
        $this->phase = $phase;

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function getTache(): ?string
    {
        return $this->tache;
    }

    public function setTache(string $tache): static
    {
        $this->tache = $tache;

        return $this;
    }

    public function getMateriel(): ?string
    {
        return $this->materiel;
    }

    public function setMateriel(?string $materiel): static
    {
        $this->materiel = $materiel;

        return $this;
    }

    public function getQui(): ?string
    {
        return $this->qui;
    }

    public function setQui(?string $qui): static
    {
        $this->qui = $qui;

        return $this;
    }
}
