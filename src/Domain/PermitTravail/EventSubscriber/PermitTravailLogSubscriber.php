<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\EventSubscriber;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PermitTravailLog;
use App\Domain\PermitTravail\Enum\ActionPermitTravailLog;
use App\Domain\PermitTravail\Service\PermitTravailLogService;
use App\Domain\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsDoctrineListener(event: Events::postLoad)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
#[AsEventListener(event: KernelEvents::TERMINATE, method: 'onKernelTerminate')]
class PermitTravailLogSubscriber
{
    /**
     * Keyed by permitId to deduplicate multiple loads of the same entity in one request.
     * @var array<string, array{userId: int|null, codeSite: string, typePermis: string}>
     */
    private array $pendingConsultes = [];

    /** @var PermitTravailLog[] */
    private array $pendingLogs = [];

    public function __construct(
        private readonly PermitTravailLogService $logService,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly ManagerRegistry $registry,
    ) {}

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof PermitTravail) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $request = $this->requestStack->getMainRequest();
        if ($request === null || $request->getMethod() !== 'GET') {
            return;
        }

        // Only log single-permit GET, not collection — check path matches /api/permits-travail/{uuid}
        $pathInfo = $request->getPathInfo();
        if (!preg_match('#^/api/permits-travail/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$#', $pathInfo)) {
            return;
        }

        // Accumulate raw data only — never persist/flush inside a Doctrine postLoad
        // (flush() inside postLoad closes the EM on any DB exception, breaking subsequent queries)
        $this->pendingConsultes[(string) $entity->getId()] = [
            'userId'     => $user->getId(),
            'codeSite'   => $entity->getCodeSite(),
            'typePermis' => $entity->getType()?->value ?? '',
        ];
    }

    public function onKernelTerminate(): void
    {
        if (empty($this->pendingConsultes)) {
            return;
        }

        $consultes = $this->pendingConsultes;
        $this->pendingConsultes = [];

        try {
            // Reset the manager: gets a fresh EM regardless of whether the previous one was closed
            $em = $this->registry->resetManager();

            foreach ($consultes as $permitId => $data) {
                $permit = $em->find(PermitTravail::class, $permitId);
                if ($permit === null) {
                    continue;
                }
                $user = $data['userId'] !== null ? $em->find(User::class, $data['userId']) : null;
                $log  = $this->logService->buildLog($permit, ActionPermitTravailLog::CONSULTE, $user instanceof User ? $user : null);
                $em->persist($log);
            }

            $em->flush();
        } catch (\Throwable) {
            // Logging must never affect the response
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof PermitTravail) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $uow       = $args->getObjectManager()->getUnitOfWork();
        $changeSet = $uow->getEntityChangeSet($entity);
        if (!isset($changeSet['statut'])) {
            return;
        }

        [$oldStatut, $newStatut] = $changeSet['statut'];

        // Doctrine's UoW may store raw string snapshots for newly-persisted entities
        // instead of the PHP-backed enum — handle both cases.
        $ancienStatut  = $oldStatut instanceof \BackedEnum ? $oldStatut->value : (string) $oldStatut;
        $nouveauStatut = $newStatut instanceof \BackedEnum ? $newStatut->value : (string) $newStatut;

        $this->pendingLogs[] = $this->logService->buildLog(
            $entity,
            ActionPermitTravailLog::STATUT_CHANGE,
            $user,
            [
                'ancienStatut'   => $ancienStatut,
                'nouveauStatut'  => $nouveauStatut,
            ],
        );
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (empty($this->pendingLogs)) {
            return;
        }

        $em   = $args->getObjectManager();
        $logs = $this->pendingLogs;
        $this->pendingLogs = [];

        foreach ($logs as $log) {
            $em->persist($log);
        }

        try {
            $em->flush();
        } catch (\Throwable) {
            // Do not propagate log failures
        }
    }
}
