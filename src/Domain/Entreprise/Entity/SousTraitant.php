<?php

declare(strict_types=1);

namespace App\Domain\Entreprise\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\SousTraitantWriteProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'sous_traitant')]
#[ORM\UniqueConstraint(name: 'uniq_sous_traitant_entreprise_nom', columns: ['entreprise_id', 'nom'])]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/sous-traitants', security: "is_granted('SOUS_TRAITANT_VIEW')"),
        new Post(uriTemplate: '/sous-traitants', security: "is_granted('SOUS_TRAITANT_CREATE')", processor: SousTraitantWriteProcessor::class),
        new Get(uriTemplate: '/sous-traitants/{id}', security: "is_granted('SOUS_TRAITANT_VIEW', object)"),
        new Patch(uriTemplate: '/sous-traitants/{id}', security: "is_granted('SOUS_TRAITANT_EDIT', object)", processor: SousTraitantWriteProcessor::class),
        new Delete(uriTemplate: '/sous-traitants/{id}', security: "is_granted('SOUS_TRAITANT_DELETE', object)"),
    ],
    normalizationContext: ['groups' => ['sous_traitant:read']],
    denormalizationContext: ['groups' => ['sous_traitant:write']],
)]
class SousTraitant
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['sous_traitant:read', 'user:read', 'activity_planning:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['sous_traitant:read', 'sous_traitant:write', 'user:read', 'activity_planning:read'])]
    #[Assert\NotBlank(message: 'sous_traitant_nom_required')]
    #[Assert\Length(max: 255)]
    private ?string $nom = null;

    #[ORM\ManyToOne(targetEntity: Entreprise::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['sous_traitant:read', 'sous_traitant:write'])]
    private ?Entreprise $entreprise = null;

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getEntreprise(): ?Entreprise
    {
        return $this->entreprise;
    }

    public function setEntreprise(?Entreprise $entreprise): static
    {
        $this->entreprise = $entreprise;

        return $this;
    }
}
