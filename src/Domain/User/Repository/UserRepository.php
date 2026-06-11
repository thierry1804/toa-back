<?php

namespace App\Domain\User\Repository;

use App\Domain\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** @return User[] */
    public function findByRole(string $role): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT id FROM "user" WHERE roles::text LIKE :role';
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('role', '%"' . $role . '"%');
        $ids = array_column($stmt->executeQuery()->fetchAllAssociative(), 'id');

        if (empty($ids)) {
            return [];
        }

        return $this->findBy(['id' => $ids], ['name' => 'ASC', 'firstname' => 'ASC']);
    }

    /** @return User[] */
    public function findByEmailExcludingId(array $criteria): array
    {
        $email = $criteria['email'] ?? null;
        $entity = $criteria['entity'] ?? null;

        if (!$email) {
            return [];
        }

        $qb = $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->setParameter('email', $email);

        if ($entity instanceof User && $entity->getId() !== null) {
            $qb->andWhere('u.id != :excludeId')
                ->setParameter('excludeId', $entity->getId());
        }

        $result = $qb->getQuery()->getResult();

        file_put_contents('/tmp/unique_debug.log', sprintf(
            "[%s] email=%s entity=%s id=%d count=%d\n",
            date('c'), $email, $entity ? $entity::class : 'null', $entity?->getId() ?? 0, count($result)
        ), FILE_APPEND);

        return $result;
    }
}
