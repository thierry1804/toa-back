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

        $data->setReference($this->generateReference());
        $data->setStatut(StatutPermitTravail::BROUILLON);
        $data->setCreatedBy($user);

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        // NOUVEAU_SITE: create or link groupe (GENERAL + ELECTRIQUE|HAUTEUR pair)
        if ($data->getProcessus() === ProcessusPermitTravail::NOUVEAU_SITE) {
            $this->handleNouveauSiteGroupe($data, $user);
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

    private function handleNouveauSiteGroupe(PermitTravail $permit, User $user): void
    {
        $type = $permit->getType();

        $groupe = $this->groupeRepository->findByCodeSiteAndPlan(
            $permit->getCodeSite(),
            $permit->getPlanPrevention(),
        );

        if ($groupe === null) {
            if ($type === TypePermitTravail::GENERAL) {
                // User explicitly creating GENERAL first
                $groupe = new PermitTravailGroupe();
                $groupe->setCodeSite($permit->getCodeSite());
                $groupe->setPlanPrevention($permit->getPlanPrevention());
                $groupe->setPermitGeneral($permit);
                $groupe->setCreatedBy($user);
                $this->entityManager->persist($groupe);
            } else {
                // User creating ELECTRIQUE/HAUTEUR first — auto-create GENERAL companion
                $generalPermit = new PermitTravail();
                $generalPermit->setType(TypePermitTravail::GENERAL);
                $generalPermit->setProcessus($permit->getProcessus());
                $generalPermit->setCodeSite($permit->getCodeSite());
                $generalPermit->setPlanPrevention($permit->getPlanPrevention());
                $generalPermit->setDescriptionTravaux($permit->getDescriptionTravaux());
                $generalPermit->setDateDebutPrevue($permit->getDateDebutPrevue());
                $generalPermit->setDateFinPrevue($permit->getDateFinPrevue());
                $generalPermit->setReference($this->generateReference());
                $generalPermit->setStatut(StatutPermitTravail::BROUILLON);
                $generalPermit->setCreatedBy($user);
                $this->entityManager->persist($generalPermit);
                $this->entityManager->flush();

                $groupe = new PermitTravailGroupe();
                $groupe->setCodeSite($permit->getCodeSite());
                $groupe->setPlanPrevention($permit->getPlanPrevention());
                $groupe->setPermitGeneral($generalPermit);
                $groupe->setPermitSpecialise($permit);
                $groupe->setCreatedBy($user);
                $this->entityManager->persist($groupe);
            }
        } elseif ($groupe->getPermitSpecialise() === null) {
            // Second permit — must be ELECTRIQUE or HAUTEUR
            if (!in_array($type, [TypePermitTravail::ELECTRIQUE, TypePermitTravail::HAUTEUR], true)) {
                throw new UnprocessableEntityHttpException('permit_travail.nouveau_site_specialise_required');
            }

            $groupe->setPermitSpecialise($permit);
        } else {
            throw new UnprocessableEntityHttpException('permit_travail.groupe_complet');
        }

        $this->entityManager->flush();
        $permit->setGroupeTransient($groupe);
    }
}
