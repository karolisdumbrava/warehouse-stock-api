<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

class InvalidOrderStateException extends LoggableException
{
    public function __construct(
        private readonly int $orderId,
        private readonly string $currentState,
        private readonly string $attemptedAction,
    ) {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, $this->getPublicMessage());
    }

    public function getPublicMessage(): string
    {
        return 'Cannot perform this action on the order';
    }

    public function getLogMessage(): string
    {
        return sprintf(
            'Cannot %s order #%d in state "%s"',
            $this->attemptedAction,
            $this->orderId,
            $this->currentState
        );
    }

    public function getLogContext(): array
    {
        return [
            'order_id' => $this->orderId,
            'current_state' => $this->currentState,
            'attempted_action' => $this->attemptedAction,
        ];
    }
}
