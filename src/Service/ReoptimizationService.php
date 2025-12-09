<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Repository\OrderRepository;

readonly class ReoptimizationService
{
    public function __construct(
        private OrderRepository        $orderRepository,
        private StockAllocationService $allocationService,
    ) {}

    /**
     * Try to improve allocations for all partially reserved orders.
     *
     * @return Order[] Orders that were improved
     */
    public function reoptimizePartialOrders(): array
    {
        $improvedOrders = [];
        $partialOrders = $this->orderRepository->findPartiallyReserved();

        foreach ($partialOrders as $order) {
            if ($this->tryImproveOrder($order)) {
                $improvedOrders[] = $order;
            }
        }

        return $improvedOrders;
    }

    /**
     * Reoptimize orders that need specific products.
     *
     * @param int[] $productIds Products that were freed up
     * @return Order[] Orders that were improved
     */
    public function reoptimizeForProducts(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $improvedOrders = [];
        $partialOrders = $this->orderRepository->findPartiallyReserved();

        foreach ($partialOrders as $order) {
            if (!$this->orderNeedsProducts($order, $productIds)) {
                continue;
            }

            if ($this->tryImproveOrder($order)) {
                $improvedOrders[] = $order;
            }
        }

        return $improvedOrders;
    }

    /**
     * @throws \Exception
     */
    private function tryImproveOrder(Order $order): bool
    {
        $previousStatus = $order->getStatus();

        $result = $this->allocationService->allocateOrder($order);

        $newStatus = $order->getStatus();

        return $newStatus !== $previousStatus || $result->warehousesUsed > 0;
    }

    /**
     * @param int[] $productIds
     */
    private function orderNeedsProducts(Order $order, array $productIds): bool
    {
        foreach ($order->orderLines as $line) {
            if ($line->isFullyReserved()) {
                continue;
            }

            if (in_array($line->product->id, $productIds, true)) {
                return true;
            }
        }

        return false;
    }
}
