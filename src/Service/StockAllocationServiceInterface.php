<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\AllocationResult;
use App\Entity\Order;

interface StockAllocationServiceInterface
{
    public function allocateOrder(Order $order): AllocationResult;
}
