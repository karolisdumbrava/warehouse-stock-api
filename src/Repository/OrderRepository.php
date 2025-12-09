<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Order;
use App\Enum\OrderStatus;
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
     * Find orders that are partially reserved (candidates for reoptimization)
     *
     * @return Order[]
     */
    public function findPartiallyReserved(): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->setParameter('status', OrderStatus::PARTIALLY_RESERVED)
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find an order that belongs to a specific client.
     *
     * @param int $id
     *   Order id.
     * @param Client $client
     *   Client entity.
     *
     * @return Order|null Returns order.
     */
    public function findByIdAndClient(int $id, Client $client): ?Order
    {
        return $this->findOneBy([
            'id' => $id,
            'client' => $client,
        ]);
    }
}
