<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WarehouseStock>
 */
class WarehouseStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WarehouseStock::class);
    }

    /**
     * Find stock for a specific warehouse and product
     */
    public function findByWarehouseAndProduct(Warehouse $warehouse, Product $product): ?WarehouseStock
    {
        return $this->findOneBy([
            'warehouse' => $warehouse,
            'product' => $product,
        ]);
    }

    /**
     * Find all stocks for given products with available quantity > 0
     * Uses pessimistic locking to prevent race conditions
     *
     * @param Product[] $products
     * @return WarehouseStock[]
     */
    public function findAvailableStocksForProducts(array $products, bool $withLock = false): array
    {
        $qb = $this->createQueryBuilder('ws')
            ->join('ws.warehouse', 'w')
            ->join('ws.product', 'p')
            ->where('ws.product IN (:products)')
            ->andWhere('(ws.quantity - ws.reservedQuantity) > 0')
            ->setParameter('products', $products)
            ->orderBy('w.id', 'ASC');

        $query = $qb->getQuery();

        if ($withLock) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        return $query->getResult();
    }
}
