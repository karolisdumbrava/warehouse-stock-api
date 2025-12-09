<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when an order cannot be found or doesn't belong to the client.
 */
class OrderNotFoundException extends LoggableException
{
    public function __construct(
        private readonly int $orderId,
        private readonly int $clientId,
    ) {
        parent::__construct(Response::HTTP_NOT_FOUND, $this->getPublicMessage());
    }

    public function getPublicMessage(): string
    {
        return 'Order not found';
    }

    public function getLogMessage(): string
    {
        return sprintf(
            'Order #%d not found or does not belong to client #%d',
            $this->orderId,
            $this->clientId
        );
    }

    public function getLogContext(): array
    {
        return [
            'order_id' => $this->orderId,
            'client_id' => $this->clientId,
        ];
    }
}
