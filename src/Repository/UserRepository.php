<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        return $this->createQueryBuilder('user')
            ->where('user.username = :identifier OR user.email = :identifier')
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<User>
     */
    public function findForManagement(string $search = '', string $role = '', string $status = ''): array
    {
        $query = $this->createQueryBuilder('user')->orderBy('user.id', 'DESC');

        if (trim($search) !== '') {
            $query->andWhere('user.username LIKE :search OR user.email LIKE :search OR user.firstName LIKE :search OR user.lastName LIKE :search')
                ->setParameter('search', '%'.trim($search).'%');
        }
        if ($role === 'admin') {
            $query->andWhere('user.isAdmin = true');
        } elseif ($role === 'staff') {
            $query->andWhere('user.isStaff = true AND user.isAdmin = false');
        } elseif ($role === 'customer') {
            $query->andWhere('user.isAdmin = false AND user.isStaff = false');
        }
        if ($status === 'active') {
            $query->andWhere('user.isActive = true');
        } elseif ($status === 'disabled') {
            $query->andWhere('user.isActive = false');
        }

        return $query->getQuery()->getResult();
    }

    public function countActiveAdministrators(?User $exclude = null): int
    {
        $query = $this->createQueryBuilder('user')
            ->select('COUNT(user.id)')
            ->where('user.isAdmin = true')
            ->andWhere('user.isActive = true');
        if ($exclude !== null) {
            $query->andWhere('user.id != :exclude')->setParameter('exclude', $exclude->getId());
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }
}
