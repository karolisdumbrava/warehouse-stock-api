<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

class WarehouseNotFoundException extends LoggableException
{
    public function __construct(
        private readonly int $warehouseId,
    ) {
        parent::__construct(Response::HTTP_NOT_FOUND, $this->getPublicMessage());
    }

    public function getPublicMessage(): string
    {
        return 'Warehouse not found';
    }

    public function getLogMessage(): string
    {
        return sprintf('Warehouse #%d not found', $this->warehouseId);
    }

    public function getLogContext(): array
    {
        return [
            'warehouse_id' => $this->warehouseId,
        ];
    }
}
