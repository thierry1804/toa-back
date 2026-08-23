<?php

declare(strict_types=1);

namespace App\Domain\Intervention\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\ControleJournalierCreateProcessor;
use App\Api\Processor\ControleJournalierUpdateProcessor;
use App\Domain\Intervention\Repository\ControleJournalierRepository;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\User\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ControleJournalierRepository::class)]
#[ORM\Table(name: '`controle_journalier`')]
#[ORM\UniqueConstraint(name: 'uq_controle_intervention_date', columns: ['intervention_id', 'date'])]
#[ORM\Index(columns: ['intervention_id', 'date'], name: 'idx_controle_intervention_date')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/interventions/{interventionId}/controles-journaliers',
            uriVariables: [
                'interventionId' => new Link(
                    fromClass: Intervention::class,
                    toProperty: 'intervention',
                ),
            ],
            security: "is_granted('CONTROLE_JOURNALIER_VIEW', null)",
            name: 'controle_journalier_list',
        ),
        new Post(
            uriTemplate: '/controles-journaliers',
            security: "is_granted('CONTROLE_JOURNALIER_CREATE')",
            processor: ControleJournalierCreateProcessor::class,
            denormalizationContext: ['groups' => ['controle_journalier:write', 'controle_journalier:create']],
            name: 'controle_journalier_create',
        ),
        new Get(
            uriTemplate: '/controles-journaliers/{id}',
            requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
            security: "is_granted('CONTROLE_JOURNALIER_VIEW', object)",
        ),
        new Patch(
            uriTemplate: '/controles-journaliers/{id}',
            requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
            security: "is_granted('CONTROLE_JOURNALIER_EDIT', object)",
            processor: ControleJournalierUpdateProcessor::class,
            denormalizationContext: ['groups' => ['controle_journalier:cloture']],
            name: 'controle_journalier_update',
        ),
    ],
    normalizationContext: ['groups' => ['controle_journalier:read']],
    denormalizationContext: ['groups' => ['controle_journalier:write']],
)]
class ControleJournalier
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['controle_journalier:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Intervention::class, inversedBy: 'controlesJournaliers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['controle_journalier:write', 'controle_journalier:create'])]
    private ?Intervention $intervention = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    #[Groups(['controle_journalier:read', 'controle_journalier:create'])]
    #[Assert\NotNull(message: 'controle_journalier.date_required')]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['controle_journalier:read', 'controle_journalier:create'])]
    #[Assert\Count(min: 1, minMessage: 'controle_journalier.intervenants_required')]
    private array $intervenants = [];

    #[ORM\Column]
    #[Groups(['controle_journalier:read', 'controle_journalier:create'])]
    #[Assert\NotNull(message: 'controle_journalier.confirmation_mesures_required')]
    private ?bool $confirmationMesures = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['controle_journalier:read', 'controle_journalier:create'])]
    private ?int $vitesseVent = null;

    #[ORM\Column(length: 150)]
    #[Groups(['controle_journalier:read', 'controle_journalier:create'])]
    #[Assert\NotBlank(message: 'controle_journalier.signature_demandeur_required')]
    private ?string $signatureDemandeur = null;

    #[ORM\Column(length: 150)]
    #[Groups(['controle_journalier:read', 'controle_journalier:create'])]
    #[Assert\NotBlank(message: 'controle_journalier.signature_intervenant_required')]
    private ?string $signatureIntervenant = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Groups(['controle_journalier:read', 'controle_journalier:cloture'])]
    private ?string $signatureClotureDemandeur = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Groups(['controle_journalier:read', 'controle_journalier:cloture'])]
    private ?string $signatureClotureIntervenant = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['controle_journalier:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['controle_journalier:read'])]
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

    #[Groups(['controle_journalier:read'])]
    public function getTypePermis(): ?TypePermitTravail
    {
        return $this->intervention?->getPermitTravail()?->getType();
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getIntervenants(): array
    {
        return $this->intervenants;
    }

    public function setIntervenants(array $intervenants): static
    {
        $this->intervenants = $intervenants;

        return $this;
    }

    public function isConfirmationMesures(): ?bool
    {
        return $this->confirmationMesures;
    }

    public function setConfirmationMesures(bool $confirmationMesures): static
    {
        $this->confirmationMesures = $confirmationMesures;

        return $this;
    }

    public function getVitesseVent(): ?int
    {
        return $this->vitesseVent;
    }

    public function setVitesseVent(?int $vitesseVent): static
    {
        $this->vitesseVent = $vitesseVent;

        return $this;
    }

    public function getSignatureDemandeur(): ?string
    {
        return $this->signatureDemandeur;
    }

    public function setSignatureDemandeur(string $signatureDemandeur): static
    {
        $this->signatureDemandeur = $signatureDemandeur;

        return $this;
    }

    public function getSignatureIntervenant(): ?string
    {
        return $this->signatureIntervenant;
    }

    public function setSignatureIntervenant(string $signatureIntervenant): static
    {
        $this->signatureIntervenant = $signatureIntervenant;

        return $this;
    }

    public function getSignatureClotureDemandeur(): ?string
    {
        return $this->signatureClotureDemandeur;
    }

    public function setSignatureClotureDemandeur(?string $signatureClotureDemandeur): static
    {
        $this->signatureClotureDemandeur = $signatureClotureDemandeur;

        return $this;
    }

    public function getSignatureClotureIntervenant(): ?string
    {
        return $this->signatureClotureIntervenant;
    }

    public function setSignatureClotureIntervenant(?string $signatureClotureIntervenant): static
    {
        $this->signatureClotureIntervenant = $signatureClotureIntervenant;

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
