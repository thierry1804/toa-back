<?php

namespace App;

use App\Domain\PermitTravail\Message\CheckPermitsExpirationMessage;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true) // ensure only last missed task is run
            // R-27 : rappel envoyé dans les 24 h précédant l'échéance (exécution horaire).
            ->add(
                RecurringMessage::every('1 hour', new CheckPermitsExpirationMessage()),
            )
            // R-28 : clôture automatique des permis Hauteur/Électrique expirés, sans renouvellement.
            ->add(
                RecurringMessage::every('1 hour', new RunCommandMessage('app:permit-travail:expire')),
            )
        ;
    }
}
