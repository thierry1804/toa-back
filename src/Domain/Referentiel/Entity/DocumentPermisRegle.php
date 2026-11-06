<?php

declare(strict_types=1);

namespace App\Domain\Referentiel\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\DocumentPermisRegleProcessor;
use App\Domain\Referentiel\Repository\DocumentPermisRegleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentPermisRegleRepository::class)]
#[ORM\Table(name: 'document_permis_regle')]
#[ORM\UniqueConstraint(name: 'uniq_document_permis_regle', columns: ['code', 'moment', 'processus', 'type_permis', 'demande', 'apn_api_uniquement'])]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/referentiel/documents-permis', security: "is_granted('DOCUMENT_PERMIS_VIEW')"),
        new Post(uriTemplate: '/referentiel/documents-permis', security: "is_granted('DOCUMENT_PERMIS_CREATE')", processor: DocumentPermisRegleProcessor::class),
        new Get(uriTemplate: '/referentiel/documents-permis/{id}', security: "is_granted('DOCUMENT_PERMIS_VIEW', object)"),
        new Patch(uriTemplate: '/referentiel/documents-permis/{id}', security: "is_granted('DOCUMENT_PERMIS_EDIT', object)", processor: DocumentPermisRegleProcessor::class),
        new Delete(uriTemplate: '/referentiel/documents-permis/{id}', security: "is_granted('DOCUMENT_PERMIS_DELETE', object)", processor: DocumentPermisRegleProcessor::class),
    ],
    normalizationContext: ['groups' => ['document_permis:read']],
    denormalizationContext: ['groups' => ['document_permis:write']],
    paginationItemsPerPage: 200,
)]
class DocumentPermisRegle
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['document_permis:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['document_permis:read', 'document_permis:write'])]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Z0-9_]+$/', message: 'document_permis.code_invalide')]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    #[Groups(['document_permis:read', 'document_permis:write'])]
    #[Assert\NotBlank]
    private ?string $libelle = null;

    #[ORM\Column(length: 20)]
    #[Groups(['document_permis:read', 'document_permis:write'])]
    #[Assert\Choice(choices: ['SOUMISSION', 'CLOTURE', 'DANGERS'])]
    private ?string $moment = null;

    #[ORM\Column(length: 20)]
    #[Groups(['document_permis:read', 'document_permis:write'])]
    #[Assert\Choice(choices: ['NOUVEAU_SITE', 'AUTRES', 'TOUS'])]
    private ?string $processus = null;

    #[ORM\Column(name: 'type_permis', length: 20)]
    #[Groups(['document_permis:read', 'document_permis:write'])]
    #[Assert\Choice(choices: ['GENERAL', 'HAUTEUR', 'ELECTRIQUE', 'TOUS'])]
    private ?string $typePermis = null;

    #[ORM\Column(length: 20)]
    #[Groups(['document_permis:read', 'document_permis:write'])]
    #[Assert\Choice(choices: ['PREMIERE', 'RENOUVELLEMENT', 'TOUTES'])]
    private ?string $demande = null;

    #[ORM\Column(name: 'apn_api_uniquement', options: ['default' => false])]
    #[Groups(['document_permis:read', 'document_permis:write'])]
    private bool $apnApiUniquement = false;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['document_permis:read', 'document_permis:write'])]
    private bool $obligatoire = true;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['document_permis:read', 'document_permis:write'])]
    private int $ordre = 0;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['document_permis:read', 'document_permis:write'])]
    private bool $actif = true;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

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

    public function getMoment(): ?string
    {
        return $this->moment;
    }

    public function setMoment(string $moment): static
    {
        $this->moment = $moment;

        return $this;
    }

    public function getProcessus(): ?string
    {
        return $this->processus;
    }

    public function setProcessus(string $processus): static
    {
        $this->processus = $processus;

        return $this;
    }

    public function getTypePermis(): ?string
    {
        return $this->typePermis;
    }

    public function setTypePermis(string $typePermis): static
    {
        $this->typePermis = $typePermis;

        return $this;
    }

    public function getDemande(): ?string
    {
        return $this->demande;
    }

    public function setDemande(string $demande): static
    {
        $this->demande = $demande;

        return $this;
    }

    public function isApnApiUniquement(): bool
    {
        return $this->apnApiUniquement;
    }

    public function setApnApiUniquement(bool $apnApiUniquement): static
    {
        $this->apnApiUniquement = $apnApiUniquement;

        return $this;
    }

    public function isObligatoire(): bool
    {
        return $this->obligatoire;
    }

    public function setObligatoire(bool $obligatoire): static
    {
        $this->obligatoire = $obligatoire;

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

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    /** @return array{code: string, libelle: string, obligatoire: bool, ordre: int} */
    public function toLigne(): array
    {
        return [
            'code' => (string) $this->code,
            'libelle' => (string) $this->libelle,
            'obligatoire' => $this->obligatoire,
            'ordre' => $this->ordre,
        ];
    }
}
