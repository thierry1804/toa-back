<?php

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Permit\Entity\Permit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PermitUpdateProcessor implements ProcessorInterface
{
    // Statuses that mark the beginning of real work
    private const IN_PROGRESS_STATUS = 'en_cours';

    // Statuses that mark the end of the permit lifecycle
    private const TERMINAL_STATUSES = ['cloture', 'refuse', 'expire'];

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Permit) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $existing = $this->entityManager->getRepository(Permit::class)->find($uriVariables['id'] ?? $data->getId());
        if (!$existing) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $previousStatus = $existing->getStatus();
        $newStatus = $data->getStatus();

        // Copy all writable fields from $data to $existing
        $this->applyWritableFields($data, $existing);

        // actualStartDate and actualEndDate are NEVER accepted from the request —
        // always restore the DB values, then auto-set based on transition.
        $existing->setActualStartDate($this->entityManager->getUnitOfWork()->getOriginalEntityData($existing)['actualStartDate'] ?? null);
        $existing->setActualEndDate($this->entityManager->getUnitOfWork()->getOriginalEntityData($existing)['actualEndDate'] ?? null);

        // Auto-set actual dates on status transitions
        $this->applyActualDates($existing, $previousStatus, $newStatus);

        return $this->persistProcessor->process($existing, $operation, $uriVariables, $context);
    }

    private function applyWritableFields(Permit $source, Permit $target): void
    {
        // Sync all fields that are in permit:write group
        if ($source->getPlanPreventionId() !== null) {
            $target->setPlanPreventionId($source->getPlanPreventionId());
        }
        if ($source->getPlanPreventionReference() !== null) {
            $target->setPlanPreventionReference($source->getPlanPreventionReference());
        }
        if ($source->getCodeSite() !== null) {
            $target->setCodeSite($source->getCodeSite());
        }
        if ($source->getNombreIntervenants() !== null) {
            $target->setNombreIntervenants($source->getNombreIntervenants());
        }
        if ($source->getDateDebut() !== null) {
            $target->setDateDebut($source->getDateDebut());
        }
        if ($source->getDateFin() !== null) {
            $target->setDateFin($source->getDateFin());
        }
        if ($source->getStatus() !== null) {
            $target->setStatus($source->getStatus());
        }
        if ($source->getDemandeurNom() !== null) {
            $target->setDemandeurNom($source->getDemandeurNom());
        }
        if ($source->getDemandeurDate() !== null) {
            $target->setDemandeurDate($source->getDemandeurDate());
        }
        if ($source->getSuperviseurNom() !== null) {
            $target->setSuperviseurNom($source->getSuperviseurNom());
        }
        if ($source->getSuperviseurDate() !== null) {
            $target->setSuperviseurDate($source->getSuperviseurDate());
        }
        if ($source->getCreerPar() !== null) {
            $target->setCreerPar($source->getCreerPar());
        }
    }

    private function applyActualDates(Permit $permit, ?string $previousStatus, ?string $newStatus): void
    {
        if ($previousStatus === $newStatus) {
            return;
        }

        $now = new \DateTime();

        if ($newStatus === self::IN_PROGRESS_STATUS && $permit->getActualStartDate() === null) {
            $permit->setActualStartDate($now);
        }

        if (in_array($newStatus, self::TERMINAL_STATUSES, true) && $permit->getActualEndDate() === null) {
            $permit->setActualEndDate($now);
        }
    }
}
