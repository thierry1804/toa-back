<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PermitTravailGroupe;
use App\Domain\PermitTravail\Enum\ProcessusPermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\PermitTravail\Repository\PermitTravailGroupeRepository;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PermitTravailCreateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly PermitTravailGroupeRepository $groupeRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof PermitTravail) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new UnprocessableEntityHttpException('user_not_authenticated');
        }

        // RG2: planPrevention must be VALIDE_HSE unless processus is MAINTENANCE
        if ($data->getProcessus() !== ProcessusPermitTravail::MAINTENANCE) {
            $plan = $data->getPlanPrevention();
            if ($plan === null) {
                throw new UnprocessableEntityHttpException('permit_travail.plan_prevention_required');
            }
            if ($plan->getStatut() !== StatutPlanPrevention::VALIDE_HSE) {
                throw new UnprocessableEntityHttpException('permit_travail.plan_prevention_not_valide_hse');
            }
        }

        // NOUVEAU_SITE: valider la paire GENERAL + ELECTRIQUE|HAUTEUR avant de
        // persister quoi que ce soit — sinon une demande bloquée laisserait un
        // permis orphelin en BROUILLON sans groupe associé.
        $isNouveauSite = $data->getProcessus() === ProcessusPermitTravail::NOUVEAU_SITE;
        $groupe = $isNouveauSite ? $this->validateNouveauSiteGroupe($data) : null;

        $data->setReference($this->generateReference());
        $data->setStatut(StatutPermitTravail::BROUILLON);
        $data->setCreatedBy($user);

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($isNouveauSite) {
            $this->attachNouveauSiteGroupe($data, $user, $groupe);
        }

        return $result;
    }

    private function generateReference(): string
    {
        $year = (int) date('Y');

        $result = $this->entityManager->getConnection()->fetchOne(
            "SELECT MAX(CAST(SUBSTRING(reference FROM '\d{4}/PTW-TOA-(\d{4})') AS INTEGER))
             FROM permit_travail
             WHERE reference LIKE :pattern",
            ['pattern' => $year . '/PTW-TOA-%'],
        );

        $next = (($result === null || $result === false) ? 0 : (int) $result) + 1;

        return sprintf('%d/PTW-TOA-%04d', $year, $next);
    }

    private function validateNouveauSiteGroupe(PermitTravail $permit): ?PermitTravailGroupe
    {
        $groupe = $this->groupeRepository->findByCodeSiteAndPlan(
            $permit->getCodeSite(),
            $permit->getPlanPrevention(),
        );

        if ($permit->getType() === TypePermitTravail::GENERAL) {
            if ($groupe !== null) {
                throw new UnprocessableEntityHttpException('permit_travail.general_deja_existant');
            }

            return null;
        }

        // ELECTRIQUE/HAUTEUR : le permis Général doit obligatoirement avoir
        // été demandé au préalable sur ce couple site/plan.
        if ($groupe === null || $groupe->getPermitGeneral() === null) {
            throw new UnprocessableEntityHttpException('permit_travail.general_required_first');
        }

        if ($groupe->getPermitSpecialise() !== null) {
            throw new UnprocessableEntityHttpException('permit_travail.groupe_complet');
        }

        return $groupe;
    }

    private function attachNouveauSiteGroupe(PermitTravail $permit, User $user, ?PermitTravailGroupe $groupe): void
    {
        if ($groupe === null) {
            $groupe = new PermitTravailGroupe();
            $groupe->setCodeSite($permit->getCodeSite());
            $groupe->setPlanPrevention($permit->getPlanPrevention());
            $groupe->setPermitGeneral($permit);
            $groupe->setCreatedBy($user);
            $this->entityManager->persist($groupe);
        } else {
            $groupe->setPermitSpecialise($permit);
        }

        $this->entityManager->flush();
        $permit->setGroupeTransient($groupe);
    }
}
