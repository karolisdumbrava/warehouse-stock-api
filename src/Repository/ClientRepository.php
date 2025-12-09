<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for Client entities.
 *
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    /**
     * Constructs a new ClientRepository.
     *
     * @param ManagerRegistry $registry
     *   The Doctrine registry.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Finds an active client by their API key.
     *
     * @param string $apiKey
     *   The API key to search for.
     *
     * @return Client|null
     *   The client entity if found and active, NULL otherwise.
     */
    public function findByApiKey(string $apiKey): ?Client
    {
        return $this->findOneBy([
            'apiKey' => $apiKey,
            'active' => true,
        ]);
    }
}
