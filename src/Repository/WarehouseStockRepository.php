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
 * Repository for WarehouseStock entities.
 *
 * @extends ServiceEntityRepository<WarehouseStock>
 */
class WarehouseStockRepository extends ServiceEntityRepository
{
    /**
     * Constructs a new WarehouseStockRepository.
     *
     * @param ManagerRegistry $registry
     *   The Doctrine registry.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WarehouseStock::class);
    }

    /**
     * Finds stock for a specific warehouse and product combination.
     *
     * @param Warehouse $warehouse
     *   The warehouse to search in.
     * @param Product $product
     *   The product to find stock for.
     *
     * @return WarehouseStock|null
     *   The stock entity if found, NULL otherwise.
     */
    public function findByWarehouseAndProduct(Warehouse $warehouse, Product $product): ?WarehouseStock
    {
        return $this->findOneBy([
            'warehouse' => $warehouse,
            'product' => $product,
        ]);
    }

    /**
     * Finds all stocks with available quantity for the given products.
     *
     * Returns stocks where (quantity - reservedQuantity) > 0.
     * Optionally uses pessimistic write locking to prevent race conditions
     * during concurrent allocation operations.
     *
     * @param Product[] $products
     *   Array of products to find stock for.
     * @param bool $withLock
     *   Whether to use pessimistic write locking.
     *
     * @return WarehouseStock[]
     *   Array of warehouse stocks with available quantity.
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
