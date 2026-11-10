<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * @return list<Order>
     */
    public function findForManagement(string $search = '', string $status = ''): array
    {
        $query = $this->createQueryBuilder('orders')->orderBy('orders.createdAt', 'DESC');
        if (trim($search) !== '') {
            $query->andWhere('orders.fullName LIKE :search OR orders.email LIKE :search')
                ->setParameter('search', '%'.trim($search).'%');
        }
        if (in_array($status, ['pending', 'approved', 'cancelled', 'completed'], true)) {
            $query->andWhere('orders.status = :status')->setParameter('status', $status);
        }

        return $query->getQuery()->getResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('orders')
            ->select('COUNT(orders.id)')
            ->where('orders.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
