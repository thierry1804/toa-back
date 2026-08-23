<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Intervention\Entity\Take5Record;
use App\Domain\Intervention\Enum\StatutIntervention;
use App\Domain\User\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class Take5RecordCreateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Take5Record) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new UnprocessableEntityHttpException('take5_record.user_not_authenticated');
        }

        $intervention = $data->getIntervention();
        if ($intervention === null) {
            throw new UnprocessableEntityHttpException('take5_record.intervention_required');
        }

        if ($intervention->getStatut() !== StatutIntervention::EVALUATION_COMPLETE) {
            throw new UnprocessableEntityHttpException('take5_record.evaluation_requise');
        }

        if (empty(array_filter($data->getEquipe(), fn($m) => trim((string) $m) !== ''))) {
            throw new UnprocessableEntityHttpException('take5_record.equipe_required');
        }

        foreach (['etape1Arreter', 'etape2Observer', 'etape3Analyser', 'etape4Controler', 'etape5Proceder'] as $getter) {
            $etape = $data->{'get' . $getter}();
            if (empty($etape['complete'])) {
                throw new UnprocessableEntityHttpException('take5_record.etapes_incompletes');
            }
        }

        $etape5 = $data->getEtape5Proceder();
        if (empty($etape5['securiteConfirmee']) || empty($etape5['autorisationProceder'])) {
            throw new UnprocessableEntityHttpException('take5_record.autorisation_requise');
        }

        $data->setCreatedBy($user);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
