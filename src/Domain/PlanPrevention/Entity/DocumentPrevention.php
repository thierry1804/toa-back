<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\AttachmentDeleteProcessor;
use App\Api\Processor\DocumentUploadProcessor;
use App\Domain\PlanPrevention\Enum\TypeDocumentPrevention;
use App\Domain\User\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: '`document_prevention`')]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/plans-prevention/{planPreventionId}/documents',
            uriVariables: [
                'planPreventionId' => new Link(
                    fromClass: PlanPrevention::class,
                    toProperty: 'planPrevention',
                ),
            ],
            security: "is_granted('PLAN_PREVENTION_VIEW')",
            name: 'document_prevention_list',
        ),
        new Post(
            uriTemplate: '/plans-prevention/{planPreventionId}/documents',
            uriVariables: [
                'planPreventionId' => new Link(
                    fromClass: PlanPrevention::class,
                    toProperty: 'planPrevention',
                ),
            ],
            read: false,
            security: "is_granted('PLAN_PREVENTION_EDIT')",
            processor: DocumentUploadProcessor::class,
            deserialize: false,
            name: 'document_prevention_upload',
        ),
        new Delete(
            uriTemplate: '/plans-prevention/{planPreventionId}/documents/{id}',
            uriVariables: [
                'planPreventionId' => new Link(
                    fromClass: PlanPrevention::class,
                    toProperty: 'planPrevention',
                ),
                'id' => new Link(fromClass: DocumentPrevention::class),
            ],
            requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
            security: "is_granted('PLAN_PREVENTION_EDIT', object.getPlanPrevention())",
            processor: AttachmentDeleteProcessor::class,
            name: 'document_prevention_delete',
        ),
    ],
    normalizationContext: ['groups' => ['document_prevention:read']],
    denormalizationContext: ['groups' => ['document_prevention:write']],
)]
class DocumentPrevention
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['document_prevention:read', 'plan_prevention:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: PlanPrevention::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PlanPrevention $planPrevention = null;

    #[ORM\Column(length: 50, enumType: TypeDocumentPrevention::class)]
    #[Groups(['document_prevention:read', 'plan_prevention:read'])]
    private ?TypeDocumentPrevention $type = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['document_prevention:read', 'plan_prevention:read'])]
    private ?string $filePath = null;

    #[ORM\Column(length: 255)]
    #[Groups(['document_prevention:read', 'plan_prevention:read'])]
    private ?string $mimeType = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['document_prevention:read', 'plan_prevention:read'])]
    private ?\DateTimeImmutable $uploadedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['document_prevention:read', 'plan_prevention:read'])]
    private ?\DateTimeImmutable $capturedAt = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['document_prevention:read', 'plan_prevention:read'])]
    private bool $nonApplicable = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['document_prevention:read', 'plan_prevention:read'])]
    private ?\DateTimeImmutable $consultedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $consultedBy = null;

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

    public function getType(): ?TypeDocumentPrevention
    {
        return $this->type;
    }

    public function setType(TypeDocumentPrevention $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getUploadedAt(): ?\DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeImmutable $uploadedAt): static
    {
        $this->uploadedAt = $uploadedAt;

        return $this;
    }

    public function getCapturedAt(): ?\DateTimeImmutable
    {
        return $this->capturedAt;
    }

    public function setCapturedAt(?\DateTimeImmutable $capturedAt): static
    {
        $this->capturedAt = $capturedAt;

        return $this;
    }

    public function isNonApplicable(): bool
    {
        return $this->nonApplicable;
    }

    public function setNonApplicable(bool $nonApplicable): static
    {
        $this->nonApplicable = $nonApplicable;

        return $this;
    }

    public function getConsultedAt(): ?\DateTimeImmutable
    {
        return $this->consultedAt;
    }

    public function getConsultedBy(): ?User
    {
        return $this->consultedBy;
    }

    public function markConsulted(User $user, \DateTimeImmutable $at): static
    {
        if ($this->consultedAt === null) {
            $this->consultedAt = $at;
            $this->consultedBy = $user;
        }

        return $this;
    }

    public function resetConsultation(): static
    {
        $this->consultedAt = null;
        $this->consultedBy = null;

        return $this;
    }
}
