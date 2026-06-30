<?php

namespace App\Tests\Unit\Domain\User\Validator;

use App\Domain\User\Entity\User;
use App\Domain\User\Validator\EntrepriseNameCoherence;
use App\Domain\User\Validator\EntrepriseNameCoherenceValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class EntrepriseNameCoherenceValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): EntrepriseNameCoherenceValidator
    {
        return new EntrepriseNameCoherenceValidator();
    }

    private function buildUser(array $roles, ?string $entrepriseName): User
    {
        $user = new User();
        $user->setRoles($roles);
        $user->setEntrepriseName($entrepriseName);

        return $user;
    }

    public function testPrestataireWithEntrepriseNameIsValid(): void
    {
        $user = $this->buildUser(['ROLE_PRESTATAIRE'], 'Acme Corp');
        $this->validator->validate($user, new EntrepriseNameCoherence());
        $this->assertNoViolation();
    }

    public function testPrestataireWithNullEntrepriseNameIsValid(): void
    {
        $user = $this->buildUser(['ROLE_PRESTATAIRE'], null);
        $this->validator->validate($user, new EntrepriseNameCoherence());
        $this->assertNoViolation();
    }

    public function testAnyRoleWithNullEntrepriseNameIsValid(): void
    {
        foreach (['ROLE_CHEF_PROJET', 'ROLE_HSE', 'ROLE_DIRECTION', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN', 'ROLE_COLLABORATEUR'] as $role) {
            $this->setUp();
            $user = $this->buildUser([$role], null);
            $this->validator->validate($user, new EntrepriseNameCoherence());
            $this->assertNoViolation();
        }
    }

    public function testChefProjetWithEntrepriseNameIsInvalid(): void
    {
        $user = $this->buildUser(['ROLE_CHEF_PROJET'], 'Acme Corp');
        $this->validator->validate($user, new EntrepriseNameCoherence());
        $this->buildViolation('entreprise_name_not_allowed_for_role')
            ->atPath('property.path.entrepriseName')
            ->assertRaised();
    }

    public function testAdminWithEntrepriseNameIsInvalid(): void
    {
        $user = $this->buildUser(['ROLE_ADMIN'], 'Acme Corp');
        $this->validator->validate($user, new EntrepriseNameCoherence());
        $this->buildViolation('entreprise_name_not_allowed_for_role')
            ->atPath('property.path.entrepriseName')
            ->assertRaised();
    }

    public function testHseWithEntrepriseNameIsInvalid(): void
    {
        $user = $this->buildUser(['ROLE_HSE'], 'Acme Corp');
        $this->validator->validate($user, new EntrepriseNameCoherence());
        $this->buildViolation('entreprise_name_not_allowed_for_role')
            ->atPath('property.path.entrepriseName')
            ->assertRaised();
    }

    public function testCollaborateurWithEntrepriseNameIsInvalid(): void
    {
        $user = $this->buildUser(['ROLE_COLLABORATEUR'], 'Acme Corp');
        $this->validator->validate($user, new EntrepriseNameCoherence());
        $this->buildViolation('entreprise_name_not_allowed_for_role')
            ->atPath('property.path.entrepriseName')
            ->assertRaised();
    }
}
