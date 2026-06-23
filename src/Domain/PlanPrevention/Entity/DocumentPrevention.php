<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\DocumentUploadProcessor;
use App\Domain\PlanPrevention\Enum\TypeDocumentPrevention;
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
}
