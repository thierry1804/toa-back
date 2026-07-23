<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Intervention\Entity\SuiviJournalier;
use App\Domain\Intervention\Enum\StatutIntervention;
use App\Domain\Intervention\Repository\SuiviJournalierRepository;
use App\Domain\User\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class SuiviJournalierCreateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly SuiviJournalierRepository $suiviRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof SuiviJournalier) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new UnprocessableEntityHttpException('suivi_journalier.user_not_authenticated');
        }

        $intervention = $data->getIntervention();
        if ($intervention === null) {
            throw new UnprocessableEntityHttpException('suivi_journalier.intervention_required');
        }

        if ($intervention->getStatut() !== StatutIntervention::EVALUATION_COMPLETE) {
            throw new UnprocessableEntityHttpException('suivi_journalier.evaluation_requise');
        }

        $date = $data->getDate();
        if ($date === null) {
            throw new UnprocessableEntityHttpException('suivi_journalier.date_required');
        }

        $interventionId = (string) $intervention->getId();
        if ($this->suiviRepository->existsByInterventionAndDate($interventionId, $date)) {
            throw new UnprocessableEntityHttpException('suivi_journalier.doublon_date');
        }

        if ($data->isRealise() === false && empty(trim((string) $data->getMotifNonRealisation()))) {
            throw new UnprocessableEntityHttpException('suivi_journalier.motif_obligatoire');
        }

        $avancement = $data->getAvancementPourcentage();
        if ($avancement === null || $avancement < 0 || $avancement > 100) {
            throw new UnprocessableEntityHttpException('suivi_journalier.avancement_invalide');
        }

        $data->setCreatedBy($user);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
