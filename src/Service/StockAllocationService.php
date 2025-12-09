<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\AllocationResult;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\Reservation;
use App\Entity\WarehouseStock;
use App\Repository\WarehouseStockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

/**
 * Allocates warehouse stock to orders.
 *
 * Uses a greedy algorithm to minimize the number of warehouses used:
 * 1. Find all warehouses with available stock for ordered products
 * 2. Score each warehouse by how many order lines it can fulfill
 * 3. Allocate from the best-scoring warehouse
 * 4. Repeat until all lines are fulfilled or no more stock available
 */
readonly class StockAllocationService
{
    /**
     * Constructs a new StockAllocationService.
     *
     * @param EntityManagerInterface $entityManager
     *   The entity manager for database operations.
     * @param WarehouseStockRepository $warehouseStockRepository
     *   Repository for querying warehouse stock.
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WarehouseStockRepository $warehouseStockRepository,
    ) {
    }

    /**
     * Allocates stock for an order from available warehouses.
     *
     * Attempts to reserve stock from the minimum number of warehouses
     * necessary to fulfill the order. Uses pessimistic locking to prevent
     * race conditions during concurrent allocations.
     *
     * @param Order $order
     *   The order to allocate stock for.
     *
     * @return AllocationResult
     *   Result containing allocation status and any missing items.
     *
     * @throws Exception
     *   When database transaction fails.
     */
    public function allocateOrder(Order $order): AllocationResult
    {
        $this->entityManager->beginTransaction();

        try {
            $result = $this->doAllocate($order);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $result;
        } catch (Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Performs the actual allocation logic.
     *
     * @param Order $order
     *   The order to allocate.
     *
     * @return AllocationResult
     *   The allocation result.
     */
    private function doAllocate(Order $order): AllocationResult
    {
        $orderLines = $order->orderLines->toArray();

        if (empty($orderLines)) {
            return new AllocationResult(true, 0);
        }

        $products = array_map(static fn (OrderLine $line) => $line->product, $orderLines);

        $availableStocks = $this->warehouseStockRepository->findAvailableStocksForProducts(
            $products,
            withLock: true
        );

        $stocksByWarehouse = $this->groupStocksByWarehouse($availableStocks);
        $usedWarehouseIds = [];

        while ($this->hasUnfulfilledLines($orderLines) && !empty($stocksByWarehouse)) {
            $bestWarehouseId = $this->findBestWarehouse($orderLines, $stocksByWarehouse);

            if ($bestWarehouseId === null) {
                break;
            }

            $this->allocateFromWarehouse($orderLines, $stocksByWarehouse[$bestWarehouseId]);
            $usedWarehouseIds[$bestWarehouseId] = true;

            if (!$this->warehouseCanHelp($orderLines, $stocksByWarehouse[$bestWarehouseId])) {
                unset($stocksByWarehouse[$bestWarehouseId]);
            }
        }

        $order->updateStatusFromReservations();

        return $this->buildResult($orderLines, count($usedWarehouseIds));
    }

    /**
     * Groups warehouse stocks by warehouse ID for efficient lookup.
     *
     * @param WarehouseStock[] $stocks
     *   Array of warehouse stock entities.
     *
     * @return array<int, WarehouseStock[]>
     *   Stocks indexed by warehouse ID.
     */
    private function groupStocksByWarehouse(array $stocks): array
    {
        $grouped = [];

        foreach ($stocks as $stock) {
            $warehouseId = $stock->warehouse->id;
            $grouped[$warehouseId][] = $stock;
        }

        return $grouped;
    }

    /**
     * Checks if any order lines still need stock allocation.
     *
     * @param OrderLine[] $orderLines
     *   The order lines to check.
     *
     * @return bool
     *   TRUE if any lines are not fully reserved, FALSE otherwise.
     */
    private function hasUnfulfilledLines(array $orderLines): bool
    {
        return array_any($orderLines, fn ($line) => !$line->isFullyReserved());
    }

    /**
     * Finds the warehouse that can fulfill the most order lines.
     *
     * @param OrderLine[] $orderLines
     *   The order lines to fulfill.
     * @param array<int, WarehouseStock[]> $stocksByWarehouse
     *   Available stocks grouped by warehouse ID.
     *
     * @return int|null
     *   The best warehouse ID, or NULL if no warehouse can help.
     */
    private function findBestWarehouse(array $orderLines, array $stocksByWarehouse): ?int
    {
        $bestWarehouseId = null;
        $bestScore = 0;

        foreach ($stocksByWarehouse as $warehouseId => $stocks) {
            $score = $this->scoreWarehouse($orderLines, $stocks);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestWarehouseId = $warehouseId;
            }
        }

        return $bestWarehouseId;
    }

    /**
     * Scores a warehouse by how many unfulfilled lines it can contribute to.
     *
     * Higher scores indicate the warehouse can help with more order lines.
     *
     * @param OrderLine[] $orderLines
     *   The order lines to evaluate against.
     * @param WarehouseStock[] $stocks
     *   The warehouse's available stocks.
     *
     * @return int
     *   The warehouse score (number of lines it can help fulfill).
     */
    private function scoreWarehouse(array $orderLines, array $stocks): int
    {
        $score = 0;

        $stockByProduct = [];
        foreach ($stocks as $stock) {
            $stockByProduct[$stock->product->id] = $stock;
        }

        foreach ($orderLines as $line) {
            if ($line->isFullyReserved()) {
                continue;
            }

            $productId = $line->product->id;

            if (isset($stockByProduct[$productId])) {
                $available = $stockByProduct[$productId]->getAvailableQuantity();

                if ($available > 0) {
                    $score++;
                }
            }
        }

        return $score;
    }

    /**
     * Allocates as much stock as possible from a single warehouse.
     *
     * Creates reservations for each order line that can be fulfilled
     * from the warehouse's available stock.
     *
     * @param OrderLine[] $orderLines
     *   The order lines to allocate stock for.
     * @param WarehouseStock[] $stocks
     *   The warehouse's available stocks.
     */
    private function allocateFromWarehouse(array $orderLines, array $stocks): void
    {
        $stockByProduct = [];
        foreach ($stocks as $stock) {
            $stockByProduct[$stock->product->id] = $stock;
        }

        foreach ($orderLines as $line) {
            if ($line->isFullyReserved()) {
                continue;
            }

            $productId = $line->product->id;

            if (!isset($stockByProduct[$productId])) {
                continue;
            }

            $stock = $stockByProduct[$productId];
            $available = $stock->getAvailableQuantity();
            $needed = $line->getMissingQuantity();

            if ($available <= 0) {
                continue;
            }

            $toReserve = min($available, $needed);

            $stock->reserve($toReserve);

            $reservation = new Reservation($line, $stock, $toReserve);
            $line->addReservation($reservation);

            $this->entityManager->persist($reservation);
        }
    }

    /**
     * Checks if a warehouse can still contribute to unfulfilled order lines.
     *
     * @param OrderLine[] $orderLines
     *   The order lines to check against.
     * @param WarehouseStock[] $stocks
     *   The warehouse's stocks.
     *
     * @return bool
     *   TRUE if the warehouse can still help, FALSE otherwise.
     */
    private function warehouseCanHelp(array $orderLines, array $stocks): bool
    {
        return $this->scoreWarehouse($orderLines, $stocks) > 0;
    }

    /**
     * Builds the allocation result from the current state of order lines.
     *
     * @param OrderLine[] $orderLines
     *   The order lines after allocation.
     * @param int $warehousesUsed
     *   Number of warehouses used in the allocation.
     *
     * @return AllocationResult
     *   The allocation result with status and missing items.
     */
    private function buildResult(array $orderLines, int $warehousesUsed): AllocationResult
    {
        $missingItems = [];
        $fullyAllocated = true;

        foreach ($orderLines as $line) {
            $missing = $line->getMissingQuantity();

            if ($missing > 0) {
                $fullyAllocated = false;
                $missingItems[$line->product->sku] = $missing;
            }
        }

        return new AllocationResult($fullyAllocated, $warehousesUsed, $missingItems);
    }
}
