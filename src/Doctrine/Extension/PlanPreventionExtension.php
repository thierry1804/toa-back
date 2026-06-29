<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\User\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class PlanPreventionExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly Security $security) {}

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== PlanPrevention::class) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        if ($this->security->isGranted('ROLE_HSE')
            || $this->security->isGranted('ROLE_ADMIN')
            || $this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $root = $queryBuilder->getRootAliases()[0];

        if ($this->security->isGranted('ROLE_CHEF_PROJET')) {
            $queryBuilder
                ->join(sprintf('%s.chefProjet', $root), 'pp_chef')
                ->andWhere('pp_chef.email = :pp_chef_email')
                ->setParameter('pp_chef_email', $user->getUserIdentifier());

            return;
        }

        if ($this->security->isGranted('ROLE_PRESTATAIRE')) {
            $queryBuilder
                ->join(sprintf('%s.createdBy', $root), 'pp_user')
                ->andWhere('pp_user.email = :pp_current_user')
                ->setParameter('pp_current_user', $user->getUserIdentifier());
        }
    }
}
