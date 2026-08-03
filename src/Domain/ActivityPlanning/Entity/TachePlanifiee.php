<?php

declare(strict_types=1);

namespace App\Domain\ActivityPlanning\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'tache_planifiee')]
#[ORM\Index(columns: ['section_id', 'ordre'], name: 'idx_tache_section_ordre')]
class TachePlanifiee
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['activity_planning:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: SectionPlanifiee::class, inversedBy: 'taches')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SectionPlanifiee $section = null;

    #[ORM\Column]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private int $ordre = 0;

    #[ORM\Column(length: 255)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $tache = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\Length(max: 255)]
    private ?string $materiel = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\Length(max: 150)]
    private ?string $qui = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getSection(): ?SectionPlanifiee
    {
        return $this->section;
    }

    public function setSection(?SectionPlanifiee $section): static
    {
        $this->section = $section;

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
