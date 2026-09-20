<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User;

use App\Domain\User\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class UserPasswordPolicyTest extends TestCase
{
    private function passwordViolations(?string $plainPassword): array
    {
        $user = (new User())->setPlainPassword($plainPassword);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $messages = [];
        foreach ($validator->validateProperty($user, 'plainPassword', ['Default']) as $violation) {
            $messages[] = $violation->getMessage();
        }

        return $messages;
    }

    public function testTooShortPasswordIsRejected(): void
    {
        $this->assertSame(['password_too_short'], $this->passwordViolations('abc1234'));
    }

    public function testEightCharactersPasswordIsAccepted(): void
    {
        $this->assertSame([], $this->passwordViolations('abcd1234'));
    }

    public function testOmittedPasswordIsIgnoredOnUpdate(): void
    {
        $this->assertSame([], $this->passwordViolations(null));
    }
}
