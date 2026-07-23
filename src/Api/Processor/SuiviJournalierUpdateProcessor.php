<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Intervention\Entity\SuiviJournalier;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class SuiviJournalierUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof SuiviJournalier) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        if ($data->isRealise() === false && empty(trim((string) $data->getMotifNonRealisation()))) {
            throw new UnprocessableEntityHttpException('suivi_journalier.motif_obligatoire');
        }

        $avancement = $data->getAvancementPourcentage();
        if ($avancement === null || $avancement < 0 || $avancement > 100) {
            throw new UnprocessableEntityHttpException('suivi_journalier.avancement_invalide');
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
