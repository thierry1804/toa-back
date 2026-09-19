<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\PermitTravailDocumentDeleteProcessor;
use App\Api\Processor\PermitTravailDocumentUploadProcessor;
use App\Domain\PermitTravail\Enum\TypeDocumentPermitTravail;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: '`permit_travail_document`')]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/permits-travail/{permitTravailId}/documents',
            uriVariables: [
                'permitTravailId' => new Link(
                    fromClass: PermitTravail::class,
                    toProperty: 'permitTravail',
                ),
            ],
            security: "is_granted('PERMIT_TRAVAIL_VIEW')",
            name: 'permit_travail_document_list',
        ),
        new Post(
            uriTemplate: '/permits-travail/{permitTravailId}/documents',
            uriVariables: [
                'permitTravailId' => new Link(
                    fromClass: PermitTravail::class,
                    toProperty: 'permitTravail',
                ),
            ],
            read: false,
            security: "is_granted('PERMIT_TRAVAIL_EDIT')",
            processor: PermitTravailDocumentUploadProcessor::class,
            deserialize: false,
            name: 'permit_travail_document_upload',
        ),
        new Delete(
            uriTemplate: '/permits-travail/{permitTravailId}/documents/{id}',
            uriVariables: [
                'permitTravailId' => new Link(
                    fromClass: PermitTravail::class,
                    toProperty: 'permitTravail',
                ),
                'id' => new Link(fromClass: PermitTravailDocument::class),
            ],
            requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
            security: "is_granted('PERMIT_TRAVAIL_EDIT')",
            processor: PermitTravailDocumentDeleteProcessor::class,
            name: 'permit_travail_document_delete',
        ),
    ],
    normalizationContext: ['groups' => ['permit_travail_document:read']],
    denormalizationContext: ['groups' => ['permit_travail_document:write']],
)]
class PermitTravailDocument
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['permit_travail_document:read', 'permit_travail:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: PermitTravail::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PermitTravail $permitTravail = null;

    #[ORM\Column(length: 100, enumType: TypeDocumentPermitTravail::class)]
    #[Groups(['permit_travail_document:read', 'permit_travail:read'])]
    private ?TypeDocumentPermitTravail $type = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['permit_travail_document:read', 'permit_travail:read'])]
    private ?string $filePath = null;

    #[ORM\Column(length: 255)]
    #[Groups(['permit_travail_document:read', 'permit_travail:read'])]
    private ?string $mimeType = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['permit_travail_document:read', 'permit_travail:read'])]
    private ?\DateTimeImmutable $uploadedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['permit_travail_document:read', 'permit_travail:read'])]
    private ?\DateTimeImmutable $capturedAt = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['permit_travail_document:read', 'permit_travail:read'])]
    private bool $nonApplicable = false;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPermitTravail(): ?PermitTravail
    {
        return $this->permitTravail;
    }

    public function setPermitTravail(?PermitTravail $permitTravail): static
    {
        $this->permitTravail = $permitTravail;

        return $this;
    }

    public function getType(): ?TypeDocumentPermitTravail
    {
        return $this->type;
    }

    public function setType(TypeDocumentPermitTravail $type): static
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
}
