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

readonly class StockAllocationService implements StockAllocationServiceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WarehouseStockRepository $warehouseStockRepository,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function allocateOrder(Order $order): AllocationResult
    {
        $this->entityManager->beginTransaction();

        try {
            $result = $this->doAllocate($order);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $result;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    private function doAllocate(Order $order): AllocationResult
    {
        $orderLines = $order->orderLines->toArray();

        if (empty($orderLines)) {
            return new AllocationResult(true, 0);
        }

        // Get all products
        $products = array_map(static fn (OrderLine $line) => $line->product, $orderLines);

        // Find all available stock (with pessimistic lock to prevent race conditions)
        $availableStocks = $this->warehouseStockRepository->findAvailableStocksForProducts(
            $products,
            withLock: true
        );

        // Group stocks by warehouse for easier processing
        $stocksByWarehouse = $this->groupStocksByWarehouse($availableStocks);

        // Track which warehouses we use
        $usedWarehouseIds = [];

        // Keep allocating until all lines are fulfilled or no more stock
        while ($this->hasUnfulfilledLines($orderLines) && !empty($stocksByWarehouse)) {
            // Find the best warehouse (covers most unfulfilled lines)
            $bestWarehouseId = $this->findBestWarehouse($orderLines, $stocksByWarehouse);

            if ($bestWarehouseId === null) {
                break;
            }

            // Allocate from this warehouse
            $this->allocateFromWarehouse(
                $orderLines,
                $stocksByWarehouse[$bestWarehouseId]
            );

            $usedWarehouseIds[$bestWarehouseId] = true;

            // Remove warehouse if it has no more useful stock
            if (!$this->warehouseCanHelp($orderLines, $stocksByWarehouse[$bestWarehouseId])) {
                unset($stocksByWarehouse[$bestWarehouseId]);
            }
        }

        // Update order status based on allocation result
        $order->updateStatusFromReservations();

        // Build result
        return $this->buildResult($orderLines, count($usedWarehouseIds));
    }

    /**
     * @param WarehouseStock[] $stocks
     * @return array<int, WarehouseStock[]> Warehouse ID => stocks
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
     * @param OrderLine[] $orderLines
     */
    private function hasUnfulfilledLines(array $orderLines): bool
    {
        foreach ($orderLines as $line) {
            if (!$line->isFullyReserved()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param OrderLine[] $orderLines
     * @param array<int, WarehouseStock[]> $stocksByWarehouse
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
     * Score = how many order lines this warehouse can contribute to
     * Higher is better
     *
     * @param OrderLine[] $orderLines
     * @param WarehouseStock[] $stocks
     */
    private function scoreWarehouse(array $orderLines, array $stocks): int
    {
        $score = 0;

        // Index stocks by product ID for fast lookup
        $stockByProduct = [];
        foreach ($stocks as $stock) {
            $stockByProduct[$stock->product->id] = $stock;
        }

        foreach ($orderLines as $line) {
            if ($line->isFullyReserved()) {
                continue; // Already done
            }

            $productId = $line->product->id;

            if (isset($stockByProduct[$productId])) {
                $available = $stockByProduct[$productId]->getAvailableQuantity();

                if ($available > 0) {
                    $score++; // This warehouse can help with this line
                }
            }
        }

        return $score;
    }

    /**
     * @param OrderLine[] $orderLines
     * @param WarehouseStock[] $stocks
     */
    private function allocateFromWarehouse(array $orderLines, array $stocks): void
    {
        // Index stocks by product ID
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
                continue; // This warehouse doesn't have this product
            }

            $stock = $stockByProduct[$productId];
            $available = $stock->getAvailableQuantity();
            $needed = $line->getMissingQuantity();

            if ($available <= 0) {
                continue;
            }

            // Take what we can (minimum of available and needed)
            $toReserve = min($available, $needed);

            // Reserve in the warehouse stock
            $stock->reserve($toReserve);

            // Create reservation record
            $reservation = new Reservation($line, $stock, $toReserve);
            $line->addReservation($reservation);

            $this->entityManager->persist($reservation);
        }
    }

    /**
     * @param OrderLine[] $orderLines
     * @param WarehouseStock[] $stocks
     */
    private function warehouseCanHelp(array $orderLines, array $stocks): bool
    {
        return $this->scoreWarehouse($orderLines, $stocks) > 0;
    }

    /**
     * @param OrderLine[] $orderLines
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
