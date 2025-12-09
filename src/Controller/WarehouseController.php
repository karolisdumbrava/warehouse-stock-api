<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\WarehouseNotFoundException;
use App\Repository\WarehouseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/warehouses')]
class WarehouseController extends AbstractController
{
    public function __construct(
        private readonly WarehouseRepository $warehouseRepository,
    ) {
    }

    #[Route('', name: 'warehouse_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $warehouses = $this->warehouseRepository->findAll();

        $data = array_map(static fn ($warehouse) => [
            'id' => $warehouse->id,
            'name' => $warehouse->getName(),
            'location' => $warehouse->getLocation(),
        ], $warehouses);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'warehouse_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $warehouse = $this->warehouseRepository->find($id);

        if ($warehouse === null) {
            throw new WarehouseNotFoundException($id);
        }

        $stocks = [];
        foreach ($warehouse->stocks as $stock) {
            $stocks[] = [
                'product_sku' => $stock->product->sku,
                'product_name' => $stock->product->getName(),
                'quantity' => $stock->getQuantity(),
                'reserved' => $stock->getReservedQuantity(),
                'available' => $stock->getAvailableQuantity(),
            ];
        }

        return $this->json([
            'id' => $warehouse->id,
            'name' => $warehouse->getName(),
            'location' => $warehouse->getLocation(),
            'stocks' => $stocks,
        ]);
    }
}
