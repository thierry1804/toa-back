<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ActivityPlanning\Service;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\ActivityPlanning\Service\PlanningEntrepriseResolver;
use App\Domain\Entreprise\Entity\Entreprise;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class PlanningEntrepriseResolverTest extends TestCase
{
    public function testResolvesByProviderEmailCaseInsensitive(): void
    {
        $entreprise = $this->buildEntreprise();
        $resolver = $this->resolverWith([$this->buildPrestataire('Julien', 'Bouvier', 'bouvier@test.mg', $entreprise)]);

        $planning = $this->buildPlanning('Autre Nom', 'BOUVIER@test.mg ');

        $this->assertSame($entreprise, $resolver->resolve($planning));
    }

    public function testFallsBackToUniqueFullNameWhenEmailUnknown(): void
    {
        $entreprise = $this->buildEntreprise();
        $resolver = $this->resolverWith([$this->buildPrestataire('Julien', 'Bouvier', 'bouvier@test.mg', $entreprise)]);

        $planning = $this->buildPlanning('julien  bouvier', 'quelquun@ailleurs.mg');

        $this->assertSame($entreprise, $resolver->resolve($planning));
    }

    public function testAmbiguousNameIsNotResolved(): void
    {
        $resolver = $this->resolverWith([
            $this->buildPrestataire('Julien', 'Bouvier', 'a@test.mg', $this->buildEntreprise()),
            $this->buildPrestataire('Julien', 'Bouvier', 'b@test.mg', $this->buildEntreprise()),
        ]);

        $this->assertNull($resolver->resolve($this->buildPlanning('Julien Bouvier', '')));
    }

    public function testPrestataireWithoutEntrepriseIsIgnored(): void
    {
        $resolver = $this->resolverWith([$this->buildPrestataire('Sarah', 'R', 'sarah@test.mg', null)]);

        $this->assertNull($resolver->resolve($this->buildPlanning('Sarah R', 'sarah@test.mg')));
    }

    public function testApplySetsEntrepriseAndReportsChange(): void
    {
        $entreprise = $this->buildEntreprise();
        $resolver = $this->resolverWith([$this->buildPrestataire('Julien', 'Bouvier', 'bouvier@test.mg', $entreprise)]);
        $planning = $this->buildPlanning('Julien Bouvier', 'bouvier@test.mg');

        $this->assertTrue($resolver->apply($planning));
        $this->assertSame($entreprise, $planning->getEntreprise());
        $this->assertFalse($resolver->apply($planning));
    }

    /** @param User[] $prestataires */
    private function resolverWith(array $prestataires): PlanningEntrepriseResolver
    {
        $repository = $this->createStub(UserRepository::class);
        $repository->method('findByRole')->willReturn($prestataires);

        return new PlanningEntrepriseResolver($repository);
    }

    private function buildPlanning(string $provider, string $email): ActivityPlanning
    {
        $planning = new ActivityPlanning();
        $planning->setProvider($provider);
        $planning->setProviderEmail($email);

        return $planning;
    }

    private function buildPrestataire(string $firstname, string $name, string $email, ?Entreprise $entreprise): User
    {
        $user = new User();
        $user->setFirstname($firstname);
        $user->setName($name);
        $user->setEmail($email);
        $user->setRoles(['ROLE_PRESTATAIRE']);
        $user->setEntreprise($entreprise);

        return $user;
    }

    private function buildEntreprise(): Entreprise
    {
        $entreprise = new Entreprise();
        $entreprise->setNom('Entreprise ' . uniqid());

        $reflection = new \ReflectionProperty(Entreprise::class, 'id');
        $reflection->setValue($entreprise, Uuid::v7());

        return $entreprise;
    }
}
