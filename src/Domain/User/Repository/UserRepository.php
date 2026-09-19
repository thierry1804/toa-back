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

    /**
     * Same as findByRole() but scoped to a single entreprise — used to notify
     * only the HSE members attached to the prestataire concerned by a dossier,
     * instead of every ROLE_HSE user.
     *
     * @return User[]
     */
    public function findByRoleAndEntreprise(string $role, string|\Stringable $entrepriseId): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT id FROM "user" WHERE roles::text LIKE :role AND entreprise_id = :entrepriseId';
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('role', '%"' . $role . '"%');
        $stmt->bindValue('entrepriseId', (string) $entrepriseId);
        $ids = array_column($stmt->executeQuery()->fetchAllAssociative(), 'id');

        if (empty($ids)) {
            return [];
        }

        return $this->findBy(['id' => $ids], ['name' => 'ASC', 'firstname' => 'ASC']);
    }

    /**
     * Utilisateurs portant le rôle donné et rattachés à une entreprise interne (TOA).
     *
     * @return User[]
     */
    public function findByRoleInInternalEntreprises(string $role): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT u.id FROM "user" u JOIN entreprise e ON e.id = u.entreprise_id WHERE e.interne = TRUE AND u.roles::text LIKE :role';
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
