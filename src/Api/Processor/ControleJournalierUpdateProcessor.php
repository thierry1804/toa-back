<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Intervention\Entity\ControleJournalier;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ControleJournalierUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ControleJournalier) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        if (
            trim((string) $data->getSignatureClotureDemandeur()) === ''
            || trim((string) $data->getSignatureClotureIntervenant()) === ''
        ) {
            throw new UnprocessableEntityHttpException('controle_journalier.signature_cloture_required');
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
