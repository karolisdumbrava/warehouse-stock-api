<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Exception;

/**
 * Attempts to improve allocations for partially reserved orders.
 *
 * When stock becomes available (e.g., after order cancellation or inventory
 * replenishment), this service finds orders that need those products and
 * attempts to allocate additional stock to them.
 */
readonly class ReoptimizationService
{
    /**
     * Constructs a new ReoptimizationService.
     *
     * @param OrderRepository $orderRepository
     *   Repository for querying orders.
     * @param StockAllocationService $allocationService
     *   Service for allocating stock to orders.
     */
    public function __construct(
        private OrderRepository $orderRepository,
        private StockAllocationService $allocationService,
    ) {
    }

    /**
     * Attempts to improve allocations for all partially reserved orders.
     *
     * Iterates through all orders with PARTIALLY_RESERVED status and
     * attempts to allocate additional stock to each one.
     *
     * @return Order[]
     *   Array of orders that were improved.
     * @throws Exception
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
     * Reoptimizes orders that need specific products.
     *
     * Only attempts reoptimization for orders that have unfulfilled
     * lines for the given products, improving efficiency when stock
     * for specific products becomes available.
     *
     * @param int[] $productIds
     *   Array of product IDs that were freed up.
     *
     * @return Order[]
     *   Array of orders that were improved.
     * @throws Exception
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
     * Attempts to allocate more stock to an order.
     *
     * @param Order $order
     *   The order to improve.
     *
     * @return bool
     *   TRUE if any improvement was made, FALSE otherwise.
     *
     * @throws Exception
     *   When allocation transaction fails.
     */
    private function tryImproveOrder(Order $order): bool
    {
        $previousStatus = $order->getStatus();

        $result = $this->allocationService->allocateOrder($order);

        $newStatus = $order->getStatus();

        return $newStatus !== $previousStatus || $result->warehousesUsed > 0;
    }

    /**
     * Checks if an order has unfulfilled lines for any of the given products.
     *
     * @param Order $order
     *   The order to check.
     * @param int[] $productIds
     *   Array of product IDs to check against.
     *
     * @return bool
     *   TRUE if the order needs any of the products, FALSE otherwise.
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
