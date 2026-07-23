<?php

declare(strict_types=1);

namespace App\Domain\Intervention\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\SuiviJournalierDocumentDeleteProcessor;
use App\Api\Processor\SuiviJournalierDocumentUploadProcessor;
use App\Domain\Intervention\Repository\SuiviJournalierDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SuiviJournalierDocumentRepository::class)]
#[ORM\Table(name: '`suivi_journalier_document`')]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/suivis-journaliers/{suiviJournalierId}/documents',
            uriVariables: [
                'suiviJournalierId' => new Link(
                    fromClass: SuiviJournalier::class,
                    toProperty: 'suiviJournalier',
                ),
            ],
            security: "is_granted('SUIVI_JOURNALIER_VIEW', null)",
            name: 'suivi_journalier_document_list',
        ),
        new Post(
            uriTemplate: '/suivis-journaliers/{suiviJournalierId}/documents',
            uriVariables: [
                'suiviJournalierId' => new Link(
                    fromClass: SuiviJournalier::class,
                    toProperty: 'suiviJournalier',
                ),
            ],
            read: false,
            security: "is_granted('SUIVI_JOURNALIER_EDIT')",
            processor: SuiviJournalierDocumentUploadProcessor::class,
            deserialize: false,
            name: 'suivi_journalier_document_upload',
        ),
        new Delete(
            uriTemplate: '/suivis-journaliers/{suiviJournalierId}/documents/{id}',
            uriVariables: [
                'suiviJournalierId' => new Link(
                    fromClass: SuiviJournalier::class,
                    toProperty: 'suiviJournalier',
                ),
                'id' => new Link(fromClass: SuiviJournalierDocument::class),
            ],
            requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
            security: "is_granted('SUIVI_JOURNALIER_DELETE_DOC', object)",
            processor: SuiviJournalierDocumentDeleteProcessor::class,
            name: 'suivi_journalier_document_delete',
        ),
    ],
    normalizationContext: ['groups' => ['suivi_journalier_document:read']],
)]
class SuiviJournalierDocument
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['suivi_journalier_document:read', 'suivi_journalier:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: SuiviJournalier::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SuiviJournalier $suiviJournalier = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['suivi_journalier_document:read', 'suivi_journalier:read'])]
    private ?string $filePath = null;

    #[ORM\Column(length: 100)]
    #[Groups(['suivi_journalier_document:read', 'suivi_journalier:read'])]
    private ?string $mimeType = null;

    #[ORM\Column(length: 255)]
    #[Groups(['suivi_journalier_document:read', 'suivi_journalier:read'])]
    private ?string $nom = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['suivi_journalier_document:read', 'suivi_journalier:read'])]
    private ?\DateTimeImmutable $uploadedAt = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getSuiviJournalier(): ?SuiviJournalier
    {
        return $this->suiviJournalier;
    }

    public function setSuiviJournalier(?SuiviJournalier $suiviJournalier): static
    {
        $this->suiviJournalier = $suiviJournalier;

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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

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
