<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Service;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PermitTravailLog;
use App\Domain\PermitTravail\Enum\ActionPermitTravailLog;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class PermitTravailLogService
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function buildLog(
        PermitTravail $permit,
        ActionPermitTravailLog $action,
        User $user,
        array $metadata = [],
    ): PermitTravailLog {
        $log = new PermitTravailLog();
        $log->setPermitTravail($permit);
        $log->setAction($action);
        $log->setDeclenchePar($user);
        $log->setCodeSite($permit->getCodeSite() ?? '');
        $log->setTypePermis($permit->getType() ?? TypePermitTravail::GENERAL);
        $log->setMetadata($metadata ?: null);

        return $log;
    }

    public function log(
        PermitTravail $permit,
        ActionPermitTravailLog $action,
        User $user,
        array $metadata = [],
    ): void {
        $log = $this->buildLog($permit, $action, $user, $metadata);
        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
