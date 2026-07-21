<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Intervention\Entity\EvaluationRisque;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class EvaluationRisqueUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof EvaluationRisque) {
            $data->setEstReevalue(true);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
