<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Service\PlanPreventionSectionsSynchronizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

final class PlanPreventionUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly RequestStack $requestStack,
        private readonly PlanPreventionSectionsSynchronizer $sectionsSynchronizer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof PlanPrevention) {
            $body = json_decode($this->requestStack->getCurrentRequest()?->getContent() ?? '', true);
            if (is_array($body) && array_key_exists('sections', $body)) {
                $this->sectionsSynchronizer->sync($data, $body['sections']);
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
