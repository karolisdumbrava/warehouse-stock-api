<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

class InvalidQuantityException extends LoggableException
{
    public function __construct(
        private readonly string $sku,
    ) {
        parent::__construct(Response::HTTP_BAD_REQUEST, $this->getPublicMessage());
    }

    public function getPublicMessage(): string
    {
        return 'Invalid quantity specified';
    }

    public function getLogMessage(): string
    {
        return sprintf('Invalid quantity for SKU "%s" - must be positive', $this->sku);
    }

    public function getLogContext(): array
    {
        return [
            'sku' => $this->sku,
        ];
    }
}
