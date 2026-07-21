<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Intervention\Enum\StatutIntervention;
use App\Domain\Intervention\Repository\EvaluationRisqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class EvaluationRisqueValiderProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly EvaluationRisqueRepository $evaluationRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $intervention = $data instanceof Intervention
            ? $data
            : $this->entityManager->find(Intervention::class, $uriVariables['id'] ?? null);

        if (!$intervention instanceof Intervention) {
            throw new UnprocessableEntityHttpException('intervention.not_found');
        }

        $nonReevalues = $this->evaluationRepository->findNonReevaluesByIntervention(
            (string) $intervention->getId(),
        );

        if (!empty($nonReevalues)) {
            $descriptions = array_map(
                static fn($e) => $e->getDescription(),
                $nonReevalues,
            );

            throw new UnprocessableEntityHttpException(
                sprintf('intervention.risques_non_reevalues: %s', implode(', ', $descriptions)),
            );
        }

        $intervention->setStatut(StatutIntervention::EVALUATION_COMPLETE);

        return $this->persistProcessor->process($intervention, $operation, $uriVariables, $context);
    }
}
