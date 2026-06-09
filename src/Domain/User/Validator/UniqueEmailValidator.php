<?php

namespace App\Domain\User\Validator;

use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class UniqueEmailValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueEmail) {
            throw new UnexpectedTypeException($constraint, UniqueEmail::class);
        }

        if (!$value instanceof User) {
            throw new UnexpectedValueException($value, User::class);
        }

        $email = $value->getEmail();

        if (!$email) {
            return;
        }

        $currentId = $value->getId();

        if ($currentId === null) {
            $request = $this->requestStack->getCurrentRequest();
            if ($request !== null && preg_match('#/api/users/(\d+)#', $request->getPathInfo(), $matches)) {
                $currentId = (int) $matches[1];
            }
        }

        $qb = $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.email = :email')
            ->setParameter('email', $email);

        if ($currentId !== null) {
            $qb->andWhere('u.id != :currentId')
                ->setParameter('currentId', $currentId);
        }

        $existing = $qb->getQuery()->getResult();

        if (count($existing) > 0) {
            $this->context->buildViolation($constraint->message)
                ->atPath('email')
                ->addViolation();
        }
    }
}
