<?php

declare(strict_types=1);

namespace App\DTO;

readonly class AllocationResult
{
    /**
     * @param array<string, int> $missingItems SKU => missing quantity
     */
    public function __construct(
        public bool  $fullyAllocated,
        public int   $warehousesUsed,
        public array $missingItems = [],
    ) {
    }
}
