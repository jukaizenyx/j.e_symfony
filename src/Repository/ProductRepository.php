<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return list<Product>
     */
    public function findForManagement(?User $owner = null, ?string $search = null): array
    {
        $query = $this->createQueryBuilder('product')
            ->leftJoin('product.createdBy', 'owner')
            ->addSelect('owner')
            ->orderBy('product.id', 'DESC');

        if ($owner !== null) {
            $query->andWhere('product.createdBy = :owner')->setParameter('owner', $owner);
        }
        if ($search !== null && trim($search) !== '') {
            $query->andWhere('product.name LIKE :search OR product.description LIKE :search')
                ->setParameter('search', '%'.trim($search).'%');
        }

        return $query->getQuery()->getResult();
    }
}
