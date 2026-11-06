<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PermitTravail\Entity\PermitTravailDocument;
use App\Domain\Referentiel\Entity\DocumentPermisRegle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class DocumentPermisRegleProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private readonly ProcessorInterface $removeProcessor,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof DocumentPermisRegle) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $previous = $context['previous_data'] ?? null;
        $previousCode = $previous instanceof DocumentPermisRegle ? $previous->getCode() : null;
        if ($operation instanceof Delete || ($previousCode !== null && $previousCode !== $data->getCode())) {
            $code = $operation instanceof Delete ? (string) $data->getCode() : (string) $previousCode;
            if ($this->codeUtilise($code)) {
                throw new UnprocessableEntityHttpException('document_permis.utilise');
            }
        }

        if ($operation instanceof Delete) {
            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function codeUtilise(string $code): bool
    {
        $count = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(PermitTravailDocument::class, 'd')
            ->andWhere('d.type = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
