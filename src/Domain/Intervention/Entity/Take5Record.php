<?php

declare(strict_types=1);

namespace App\Domain\Intervention\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\Take5RecordCreateProcessor;
use App\Domain\User\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: '`take5_record`')]
#[ORM\Index(columns: ['intervention_id'], name: 'idx_take5_intervention')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/interventions/{interventionId}/take5-records',
            uriVariables: [
                'interventionId' => new Link(
                    fromClass: Intervention::class,
                    toProperty: 'intervention',
                ),
            ],
            security: "is_granted('TAKE5_RECORD_VIEW', null)",
            name: 'take5_record_list',
        ),
        new Post(
            uriTemplate: '/take5-records',
            security: "is_granted('TAKE5_RECORD_CREATE')",
            processor: Take5RecordCreateProcessor::class,
            denormalizationContext: ['groups' => ['take5_record:write', 'take5_record:create']],
            name: 'take5_record_create',
        ),
        new Get(
            uriTemplate: '/take5-records/{id}',
            requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
            security: "is_granted('TAKE5_RECORD_VIEW', object)",
        ),
    ],
    normalizationContext: ['groups' => ['take5_record:read']],
    denormalizationContext: ['groups' => ['take5_record:write']],
)]
class Take5Record
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['take5_record:read', 'intervention:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Intervention::class, inversedBy: 'take5Records')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['take5_record:write', 'take5_record:create'])]
    private ?Intervention $intervention = null;

    #[ORM\Column(length: 150)]
    #[Groups(['take5_record:read', 'take5_record:write', 'intervention:read'])]
    #[Assert\NotBlank(message: 'take5_record.responsable_nom_required')]
    #[Assert\Length(max: 150, maxMessage: 'take5_record.responsable_nom_too_long')]
    private ?string $responsableNom = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['take5_record:read', 'take5_record:write', 'intervention:read'])]
    #[Assert\Count(min: 1, minMessage: 'take5_record.equipe_required')]
    private array $equipe = [];

    #[ORM\Column(length: 255)]
    #[Groups(['take5_record:read', 'take5_record:write', 'intervention:read'])]
    #[Assert\NotBlank(message: 'take5_record.localisation_required')]
    private ?string $localisation = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['take5_record:read', 'take5_record:write', 'intervention:read'])]
    #[Assert\NotBlank(message: 'take5_record.tache_description_required')]
    private ?string $tacheDescription = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['take5_record:read', 'take5_record:write', 'intervention:read'])]
    private array $etape1Arreter = ['complete' => false, 'observations' => ''];

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['take5_record:read', 'take5_record:write', 'intervention:read'])]
    private array $etape2Observer = ['complete' => false, 'dangersIdentifies' => [], 'autresDangers' => ''];

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['take5_record:read', 'take5_record:write', 'intervention:read'])]
    private array $etape3Analyser = ['complete' => false, 'risquesEvalues' => []];

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['take5_record:read', 'take5_record:write', 'intervention:read'])]
    private array $etape4Controler = ['complete' => false, 'mesuresControle' => []];

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['take5_record:read', 'take5_record:write', 'intervention:read'])]
    private array $etape5Proceder = ['complete' => false, 'securiteConfirmee' => false, 'autorisationProceder' => false];

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['take5_record:read', 'intervention:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['take5_record:read', 'intervention:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getIntervention(): ?Intervention
    {
        return $this->intervention;
    }

    public function setIntervention(?Intervention $intervention): static
    {
        $this->intervention = $intervention;

        return $this;
    }

    public function getResponsableNom(): ?string
    {
        return $this->responsableNom;
    }

    public function setResponsableNom(string $responsableNom): static
    {
        $this->responsableNom = $responsableNom;

        return $this;
    }

    public function getEquipe(): array
    {
        return $this->equipe;
    }

    public function setEquipe(array $equipe): static
    {
        $this->equipe = $equipe;

        return $this;
    }

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(string $localisation): static
    {
        $this->localisation = $localisation;

        return $this;
    }

    public function getTacheDescription(): ?string
    {
        return $this->tacheDescription;
    }

    public function setTacheDescription(string $tacheDescription): static
    {
        $this->tacheDescription = $tacheDescription;

        return $this;
    }

    public function getEtape1Arreter(): array
    {
        return $this->etape1Arreter;
    }

    public function setEtape1Arreter(array $etape1Arreter): static
    {
        $this->etape1Arreter = $etape1Arreter;

        return $this;
    }

    public function getEtape2Observer(): array
    {
        return $this->etape2Observer;
    }

    public function setEtape2Observer(array $etape2Observer): static
    {
        $this->etape2Observer = $etape2Observer;

        return $this;
    }

    public function getEtape3Analyser(): array
    {
        return $this->etape3Analyser;
    }

    public function setEtape3Analyser(array $etape3Analyser): static
    {
        $this->etape3Analyser = $etape3Analyser;

        return $this;
    }

    public function getEtape4Controler(): array
    {
        return $this->etape4Controler;
    }

    public function setEtape4Controler(array $etape4Controler): static
    {
        $this->etape4Controler = $etape4Controler;

        return $this;
    }

    public function getEtape5Proceder(): array
    {
        return $this->etape5Proceder;
    }

    public function setEtape5Proceder(array $etape5Proceder): static
    {
        $this->etape5Proceder = $etape5Proceder;

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

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }
}
