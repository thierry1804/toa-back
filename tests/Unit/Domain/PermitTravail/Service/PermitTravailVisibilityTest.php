<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PermitTravail\Service;

use App\Doctrine\Extension\CurrentUserExtension;
use App\Domain\Entreprise\Entity\Entreprise;
use App\Domain\PermitTravail\Entity\PermitTravailLog;
use App\Domain\PermitTravail\Service\PermitTravailVisibility;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class PermitTravailVisibilityTest extends TestCase
{
    public function testLogsAreRestrictedToVisiblePermitsForAPrestataireSideUser(): void
    {
        $entreprise = new Entreprise();
        $user = new User();
        $user->setEmail('cp@test.mg');
        $user->setRoles(['ROLE_CHEF_PROJET_PRESTATAIRE']);
        $user->setEntreprise($entreprise);

        [$visibility, $em] = $this->visibilityFor($user, isHse: false);
        $logs = (new QueryBuilder($em))->select('l')->from(PermitTravailLog::class, 'l');

        $visibility->restrictLogs($logs, 'l');

        $this->assertStringContainsString('l.permitTravail IN(SELECT vis_p.id', $logs->getDQL());
        $this->assertStringContainsString('cue_creator.entreprise = :current_entreprise', $logs->getDQL());
        // Valeur recopiée sans type figé : Doctrine convertit l'entité en identifiant.
        $parameter = $logs->getParameter('current_entreprise');
        $this->assertSame($entreprise, $parameter?->getValue());
        $this->assertFalse($parameter->typeWasSpecified());
    }

    public function testLogsAreNotRestrictedForHse(): void
    {
        $user = new User();
        $user->setEmail('hse@toa.mg');
        $user->setRoles(['ROLE_HSE']);

        [$visibility, $em] = $this->visibilityFor($user, isHse: true);
        $logs = (new QueryBuilder($em))->select('l')->from(PermitTravailLog::class, 'l');

        $visibility->restrictLogs($logs, 'l');

        $this->assertNull($logs->getDQLPart('where'));
    }

    /** @return array{PermitTravailVisibility, EntityManagerInterface} */
    private function visibilityFor(User $user, bool $isHse): array
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturnCallback(
            fn (string $role) => $isHse && in_array($role, $user->getRoles(), true),
        );

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getExpressionBuilder')->willReturn(new Expr());
        $em->method('createQueryBuilder')->willReturnCallback(fn () => new QueryBuilder($em));

        return [new PermitTravailVisibility(new CurrentUserExtension($security), $em), $em];
    }
}
