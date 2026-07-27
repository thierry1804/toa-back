<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\PermitTravailCreateProcessor;
use App\Api\Processor\PermitTravailRefuserProcessor;
use App\Api\Processor\PermitTravailResoumettreProcessor;
use App\Api\Processor\PermitTravailSoumettreProcessor;
use App\Api\Processor\PermitTravailValiderProcessor;
use App\Api\Processor\PvRefuserProcessor;
use App\Api\Processor\PvValiderProcessor;
use App\Domain\PermitTravail\Enum\ProcessusPermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\PermitTravail\Repository\PermitTravailRepository;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PermitTravailRepository::class)]
#[ORM\Table(name: '`permit_travail`')]
#[ORM\HasLifecycleCallbacks]
#[ApiFilter(SearchFilter::class, properties: ['statut' => 'exact', 'codeSite' => 'exact', 'typePermis' => 'exact'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/permits-travail',
            security: "is_granted('PERMIT_TRAVAIL_VIEW')",
        ),
        new Post(
            uriTemplate: '/permits-travail',
            security: "is_granted('PERMIT_TRAVAIL_CREATE')",
            processor: PermitTravailCreateProcessor::class,
        ),
        new Get(
            uriTemplate: '/permits-travail/{id}',
            requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
            security: "is_granted('PERMIT_TRAVAIL_VIEW', object)",
        ),
        new Patch(
            uriTemplate: '/permits-travail/{id}',
            requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
            security: "is_granted('PERMIT_TRAVAIL_EDIT', object)",
        ),
        new Post(
            uriTemplate: '/permits-travail/{id}/soumettre',
            read: true,
            deserialize: false,
            security: "is_granted('PERMIT_TRAVAIL_SUBMIT', object)",
            processor: PermitTravailSoumettreProcessor::class,
            name: 'permit_travail_soumettre',
        ),
        new Post(
            uriTemplate: '/permits-travail/{id}/valider',
            read: true,
            deserialize: false,
            security: "is_granted('PERMIT_TRAVAIL_VALIDER_HSE', object)",
            processor: PermitTravailValiderProcessor::class,
            name: 'permit_travail_valider',
        ),
        new Post(
            uriTemplate: '/permits-travail/{id}/refuser',
            read: true,
            deserialize: false,
            security: "is_granted('PERMIT_TRAVAIL_REFUSER_HSE', object)",
            processor: PermitTravailRefuserProcessor::class,
            name: 'permit_travail_refuser',
        ),
        new Post(
            uriTemplate: '/permits-travail/{id}/resoumettre',
            read: true,
            deserialize: false,
            security: "is_granted('PERMIT_TRAVAIL_RESOUMETTRE', object)",
            processor: PermitTravailResoumettreProcessor::class,
            name: 'permit_travail_resoumettre',
        ),
        new Post(
            uriTemplate: '/permits-travail/{id}/pv/valider',
            read: true,
            deserialize: false,
            security: "is_granted('PERMIT_TRAVAIL_PV_VALIDER', object)",
            processor: PvValiderProcessor::class,
            name: 'permit_travail_pv_valider',
        ),
        new Post(
            uriTemplate: '/permits-travail/{id}/pv/refuser',
            read: true,
            deserialize: false,
            security: "is_granted('PERMIT_TRAVAIL_PV_REFUSER', object)",
            processor: PvRefuserProcessor::class,
            name: 'permit_travail_pv_refuser',
        ),
    ],
    normalizationContext: ['groups' => ['permit_travail:read']],
    denormalizationContext: ['groups' => ['permit_travail:write']],
)]
class PermitTravail
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['permit_travail:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['permit_travail:read', 'intervention:read'])]
    private ?string $reference = null;

    #[ORM\Column(length: 100)]
    #[Groups(['permit_travail:read', 'permit_travail:write', 'intervention:read'])]
    #[Assert\NotBlank(message: 'code_site_required')]
    #[Assert\NotNull(message: 'code_site_required')]
    private ?string $codeSite = null;

    #[ORM\ManyToOne(targetEntity: PlanPrevention::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['permit_travail:read', 'permit_travail:write'])]
    private ?PlanPrevention $planPrevention = null;

    #[SerializedName('typePermis')]
    #[ORM\Column(length: 50, enumType: TypePermitTravail::class)]
    #[Groups(['permit_travail:read', 'permit_travail:write'])]
    #[Assert\NotNull(message: 'type_required')]
    private ?TypePermitTravail $type = null;

    #[ORM\Column(length: 50, enumType: ProcessusPermitTravail::class)]
    #[Groups(['permit_travail:read', 'permit_travail:write'])]
    #[Assert\NotNull(message: 'processus_required')]
    private ?ProcessusPermitTravail $processus = null;

    #[ORM\Column(length: 50, enumType: StatutPermitTravail::class)]
    #[Groups(['permit_travail:read'])]
    private StatutPermitTravail $statut = StatutPermitTravail::BROUILLON;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['permit_travail:read', 'permit_travail:write', 'intervention:read'])]
    private ?string $descriptionTravaux = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['permit_travail:read', 'permit_travail:write', 'intervention:read'])]
    private ?\DateTimeImmutable $dateDebutPrevue = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['permit_travail:read', 'permit_travail:write', 'intervention:read'])]
    private ?\DateTimeImmutable $dateFinPrevue = null;

    #[ORM\Column]
    #[Groups(['permit_travail:read', 'permit_travail:write'])]
    private bool $engagementAccepte = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['permit_travail:read'])]
    private ?\DateTimeImmutable $engagementAccepteAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['permit_travail:read'])]
    private ?\DateTimeImmutable $soumisAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['permit_travail:read', 'intervention:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['permit_travail:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(
        targetEntity: PermitTravailDocument::class,
        mappedBy: 'permitTravail',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
        fetch: 'EXTRA_LAZY',
    )]
    #[Groups(['permit_travail:read'])]
    private Collection $documents;

    #[ORM\OneToMany(
        targetEntity: DecisionHsePermitTravail::class,
        mappedBy: 'permitTravail',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
        fetch: 'EXTRA_LAZY',
    )]
    #[Groups(['permit_travail:read'])]
    private Collection $decisionsHse;

    #[ORM\OneToMany(
        targetEntity: VersionPermitTravail::class,
        mappedBy: 'permitTravail',
        cascade: ['remove'],
        fetch: 'EXTRA_LAZY',
        orphanRemoval: false,
    )]
    #[ORM\OrderBy(['numeroVersion' => 'ASC'])]
    #[Groups(['permit_travail:read'])]
    private Collection $versions;

    #[ORM\OneToOne(targetEntity: Intervention::class, mappedBy: 'permitTravail', fetch: 'EXTRA_LAZY')]
    private ?Intervention $intervention = null;

    #[ORM\OneToOne(mappedBy: 'permitGeneral', targetEntity: PermitTravailGroupe::class)]
    private ?PermitTravailGroupe $groupeAsGeneral = null;

    #[ORM\OneToOne(mappedBy: 'permitSpecialise', targetEntity: PermitTravailGroupe::class)]
    private ?PermitTravailGroupe $groupeAsSpecialise = null;

    private ?PermitTravailGroupe $groupeTransient = null;

    public function __construct()
    {
        $this->documents    = new ArrayCollection();
        $this->decisionsHse = new ArrayCollection();
        $this->versions     = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getCodeSite(): ?string
    {
        return $this->codeSite;
    }

    public function setCodeSite(string $codeSite): static
    {
        $this->codeSite = $codeSite;

        return $this;
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

    public function getType(): ?TypePermitTravail
    {
        return $this->type;
    }

    public function setType(TypePermitTravail $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getProcessus(): ?ProcessusPermitTravail
    {
        return $this->processus;
    }

    public function setProcessus(ProcessusPermitTravail $processus): static
    {
        $this->processus = $processus;

        return $this;
    }

    public function getStatut(): StatutPermitTravail
    {
        return $this->statut;
    }

    public function setStatut(StatutPermitTravail $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDescriptionTravaux(): ?string
    {
        return $this->descriptionTravaux;
    }

    public function setDescriptionTravaux(?string $descriptionTravaux): static
    {
        $this->descriptionTravaux = $descriptionTravaux;

        return $this;
    }

    public function getDateDebutPrevue(): ?\DateTimeImmutable
    {
        return $this->dateDebutPrevue;
    }

    public function setDateDebutPrevue(?\DateTimeImmutable $dateDebutPrevue): static
    {
        $this->dateDebutPrevue = $dateDebutPrevue;

        return $this;
    }

    public function getDateFinPrevue(): ?\DateTimeImmutable
    {
        return $this->dateFinPrevue;
    }

    public function setDateFinPrevue(?\DateTimeImmutable $dateFinPrevue): static
    {
        $this->dateFinPrevue = $dateFinPrevue;

        return $this;
    }

    public function isEngagementAccepte(): bool
    {
        return $this->engagementAccepte;
    }

    public function setEngagementAccepte(bool $engagementAccepte): static
    {
        $this->engagementAccepte = $engagementAccepte;

        return $this;
    }

    public function getEngagementAccepteAt(): ?\DateTimeImmutable
    {
        return $this->engagementAccepteAt;
    }

    public function setEngagementAccepteAt(?\DateTimeImmutable $engagementAccepteAt): static
    {
        $this->engagementAccepteAt = $engagementAccepteAt;

        return $this;
    }

    public function getSoumisAt(): ?\DateTimeImmutable
    {
        return $this->soumisAt;
    }

    public function setSoumisAt(?\DateTimeImmutable $soumisAt): static
    {
        $this->soumisAt = $soumisAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(PermitTravailDocument $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setPermitTravail($this);
        }

        return $this;
    }

    public function getDecisionsHse(): Collection
    {
        return $this->decisionsHse;
    }

    public function addDecisionHse(DecisionHsePermitTravail $decision): static
    {
        if (!$this->decisionsHse->contains($decision)) {
            $this->decisionsHse->add($decision);
            $decision->setPermitTravail($this);
        }

        return $this;
    }

    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function addVersion(VersionPermitTravail $version): static
    {
        if (!$this->versions->contains($version)) {
            $this->versions->add($version);
            $version->setPermitTravail($this);
        }

        return $this;
    }

    #[Groups(['permit_travail:read'])]
    public function getPlanPreventionId(): ?string
    {
        return $this->planPrevention?->getId()?->toRfc4122();
    }

    #[Groups(['permit_travail:read'])]
    public function getInterventionId(): ?string
    {
        return $this->intervention?->getId()?->toRfc4122();
    }

    #[Groups(['permit_travail:read'])]
    public function getGroupeId(): ?string
    {
        $groupe = $this->groupeAsGeneral ?? $this->groupeAsSpecialise ?? $this->groupeTransient;
        return $groupe?->getId()?->toRfc4122();
    }

    #[Groups(['permit_travail:read'])]
    public function getPermitsGroupe(): array
    {
        $groupe = $this->groupeAsGeneral ?? $this->groupeAsSpecialise ?? $this->groupeTransient;
        if ($groupe === null) {
            return [];
        }

        $myId = $this->id?->toRfc4122();
        $result = [];

        foreach ([$groupe->getPermitGeneral(), $groupe->getPermitSpecialise()] as $companion) {
            if ($companion === null) {
                continue;
            }
            if ($companion->getId()?->toRfc4122() === $myId) {
                continue;
            }
            $result[] = [
                'id'        => $companion->getId()?->toRfc4122(),
                'reference' => $companion->getReference(),
                'typePermis' => $companion->getType()?->value,
                'statut'    => $companion->getStatut()->value,
                'documents' => [],
            ];
        }

        return $result;
    }

    public function setGroupeTransient(?PermitTravailGroupe $groupe): void
    {
        $this->groupeTransient = $groupe;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }
}
