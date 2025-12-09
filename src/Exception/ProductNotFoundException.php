<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

class ProductNotFoundException extends LoggableException
{
    public function __construct(
        private readonly string $sku,
    ) {
        parent::__construct(Response::HTTP_NOT_FOUND, $this->getPublicMessage());
    }

    public function getPublicMessage(): string
    {
        return 'Product not found';
    }

    public function getLogMessage(): string
    {
        return sprintf('Product with SKU "%s" not found', $this->sku);
    }

    public function getLogContext(): array
    {
        return [
            'sku' => $this->sku,
        ];
    }
}
