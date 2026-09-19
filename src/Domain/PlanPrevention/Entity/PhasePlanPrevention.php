<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'phase_plan_prevention')]
#[ORM\Index(columns: ['plan_prevention_id', 'ordre'], name: 'idx_phase_pp_plan_ordre')]
class PhasePlanPrevention
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['plan_prevention:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: PlanPrevention::class, inversedBy: 'sections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PlanPrevention $planPrevention = null;

    #[ORM\Column(length: 150)]
    #[Groups(['plan_prevention:read'])]
    private ?string $libelle = null;

    #[ORM\Column]
    #[Groups(['plan_prevention:read'])]
    private int $ordre = 0;

    /** @var Collection<int, ModeOperatoirePlanPrevention> */
    #[ORM\OneToMany(
        targetEntity: ModeOperatoirePlanPrevention::class,
        mappedBy: 'phase',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    #[SerializedName('taches')]
    #[Groups(['plan_prevention:read'])]
    private Collection $modesOperatoires;

    public function __construct()
    {
        $this->modesOperatoires = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPlanPrevention(): ?PlanPrevention
    {
        return $this->planPrevention;
    }

    public function setPlanPrevention(?PlanPrevention $planPrevention): static
    {
        $this->planPrevention = $planPrevention;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

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

    /** @return Collection<int, ModeOperatoirePlanPrevention> */
    public function getModesOperatoires(): Collection
    {
        return $this->modesOperatoires;
    }

    public function addModeOperatoire(ModeOperatoirePlanPrevention $mode): static
    {
        if (!$this->modesOperatoires->contains($mode)) {
            $this->modesOperatoires->add($mode);
            $mode->setPhase($this);
        }

        return $this;
    }

    public function removeModeOperatoire(ModeOperatoirePlanPrevention $mode): static
    {
        $this->modesOperatoires->removeElement($mode);

        return $this;
    }
}
