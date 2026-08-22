<?php

namespace App\Domain\Entreprise\Validator;

use App\Domain\Entreprise\Entity\Entreprise;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class UniqueEntrepriseNomValidator extends ConstraintValidator
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueEntrepriseNom) {
            throw new UnexpectedTypeException($constraint, UniqueEntrepriseNom::class);
        }

        if (!$value instanceof Entreprise) {
            throw new UnexpectedValueException($value, Entreprise::class);
        }

        $nom = $value->getNom();
        if (!$nom) {
            return;
        }

        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from(Entreprise::class, 'e')
            ->where('e.nom = :nom')
            ->setParameter('nom', $nom);

        if ($value->getId() !== null) {
            $qb->andWhere('e.id != :currentId')
                ->setParameter('currentId', $value->getId());
        }

        if (count($qb->getQuery()->getResult()) > 0) {
            $this->context->buildViolation($constraint->message)
                ->atPath('nom')
                ->addViolation();
        }
    }
}
