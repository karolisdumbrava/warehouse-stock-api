<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data transfer object representing the result of a stock allocation.
 *
 * Contains information about whether the allocation was fully successful,
 * how many warehouses were used, and which items could not be fulfilled.
 */
readonly class AllocationResult
{
    /**
     * Constructs a new AllocationResult.
     *
     * @param bool $fullyAllocated
     *   TRUE if all requested items were fully reserved.
     * @param int $warehousesUsed
     *   Number of distinct warehouses used for the allocation.
     * @param array<string, int> $missingItems
     *   Map of SKU to missing quantity for items that could not be fulfilled.
     */
    public function __construct(
        public bool $fullyAllocated,
        public int $warehousesUsed,
        public array $missingItems = [],
    ) {
    }
}
