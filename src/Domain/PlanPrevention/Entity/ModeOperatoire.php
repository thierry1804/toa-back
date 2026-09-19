<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\ModeOperatoireCreateProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * R-04 : remplace TachePlanifiee (intitulé "Tâche") — renommé "Mode opératoire",
 * saisi par le prestataire dans le plan de prévention plutôt que par l'équipe
 * interne au niveau de la planification.
 */
#[ORM\Entity]
#[ORM\Table(name: 'mode_operatoire')]
#[ORM\Index(columns: ['section_id', 'ordre'], name: 'idx_mode_operatoire_section_ordre')]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/sections/{sectionId}/modes-operatoires',
            uriVariables: [
                'sectionId' => new Link(
                    fromClass: SectionPrevention::class,
                    toProperty: 'section',
                ),
            ],
            read: false,
            security: "is_granted('PLAN_PREVENTION_EDIT_OPERATOIRE')",
            processor: ModeOperatoireCreateProcessor::class,
            name: 'mode_operatoire_create',
        ),
        new Patch(
            uriTemplate: '/sections/{sectionId}/modes-operatoires/{id}',
            uriVariables: [
                'sectionId' => new Link(
                    fromClass: SectionPrevention::class,
                    toProperty: 'section',
                ),
                'id' => new Link(fromClass: ModeOperatoire::class),
            ],
            security: "is_granted('PLAN_PREVENTION_EDIT_OPERATOIRE', object.getSection().getPlanPrevention())",
            name: 'mode_operatoire_update',
        ),
        new Delete(
            uriTemplate: '/sections/{sectionId}/modes-operatoires/{id}',
            uriVariables: [
                'sectionId' => new Link(
                    fromClass: SectionPrevention::class,
                    toProperty: 'section',
                ),
                'id' => new Link(fromClass: ModeOperatoire::class),
            ],
            security: "is_granted('PLAN_PREVENTION_EDIT_OPERATOIRE', object.getSection().getPlanPrevention())",
            name: 'mode_operatoire_delete',
        ),
    ],
    normalizationContext: ['groups' => ['mode_operatoire:read']],
    denormalizationContext: ['groups' => ['mode_operatoire:write']],
)]
class ModeOperatoire
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['mode_operatoire:read', 'plan_prevention:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: SectionPrevention::class, inversedBy: 'modesOperatoires')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SectionPrevention $section = null;

    #[ORM\Column]
    #[Groups(['mode_operatoire:read', 'mode_operatoire:write', 'plan_prevention:read'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private int $ordre = 0;

    #[ORM\Column(length: 255)]
    #[Groups(['mode_operatoire:read', 'mode_operatoire:write', 'plan_prevention:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $modeOperatoire = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['mode_operatoire:read', 'mode_operatoire:write', 'plan_prevention:read'])]
    #[Assert\Length(max: 255)]
    private ?string $materiel = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Groups(['mode_operatoire:read', 'mode_operatoire:write', 'plan_prevention:read'])]
    #[Assert\Length(max: 150)]
    private ?string $qui = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getSection(): ?SectionPrevention
    {
        return $this->section;
    }

    public function setSection(?SectionPrevention $section): static
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

    public function getModeOperatoire(): ?string
    {
        return $this->modeOperatoire;
    }

    public function setModeOperatoire(string $modeOperatoire): static
    {
        $this->modeOperatoire = $modeOperatoire;

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
