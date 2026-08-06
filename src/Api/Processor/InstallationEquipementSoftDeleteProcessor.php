<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Referentiel\Entity\InstallationEquipement;
use Doctrine\ORM\EntityManagerInterface;

final class InstallationEquipementSoftDeleteProcessor implements ProcessorInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof InstallationEquipement) {
            return null;
        }

        $data->softDelete();
        $this->em->flush();

        return null;
    }
}
