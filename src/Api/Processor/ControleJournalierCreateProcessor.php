<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Intervention\Entity\ControleJournalier;
use App\Domain\Intervention\Enum\StatutIntervention;
use App\Domain\Intervention\Repository\ControleJournalierRepository;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\User\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class ControleJournalierCreateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ControleJournalierRepository $controleRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ControleJournalier) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new UnprocessableEntityHttpException('controle_journalier.user_not_authenticated');
        }

        $intervention = $data->getIntervention();
        if ($intervention === null) {
            throw new UnprocessableEntityHttpException('controle_journalier.intervention_required');
        }

        if ($intervention->getStatut() !== StatutIntervention::EVALUATION_COMPLETE) {
            throw new UnprocessableEntityHttpException('controle_journalier.evaluation_requise');
        }

        $typePermis = $intervention->getPermitTravail()?->getType();
        if ($typePermis !== TypePermitTravail::ELECTRIQUE && $typePermis !== TypePermitTravail::HAUTEUR) {
            throw new UnprocessableEntityHttpException('controle_journalier.type_non_supporte');
        }

        if ($typePermis === TypePermitTravail::HAUTEUR && ($data->getVitesseVent() === null || $data->getVitesseVent() <= 0)) {
            throw new UnprocessableEntityHttpException('controle_journalier.vitesse_vent_requise');
        }

        $date = $data->getDate();
        if ($date === null) {
            throw new UnprocessableEntityHttpException('controle_journalier.date_required');
        }

        $interventionId = (string) $intervention->getId();
        if ($this->controleRepository->existsByInterventionAndDate($interventionId, $date)) {
            throw new UnprocessableEntityHttpException('controle_journalier.doublon_date');
        }

        if (empty(array_filter($data->getIntervenants(), fn($i) => trim((string) $i) !== ''))) {
            throw new UnprocessableEntityHttpException('controle_journalier.intervenants_required');
        }

        if ($data->isConfirmationMesures() !== true) {
            throw new UnprocessableEntityHttpException('controle_journalier.confirmation_mesures_required');
        }

        $data->setCreatedBy($user);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
