<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Base exception for API errors with separate public and log messages.
 *
 * This allows detailed logging while returning safe, generic messages to users.
 */
abstract class LoggableException extends HttpException
{
    /**
     * Get context data for logging (not shown to user).
     */
    public function getLogContext(): array
    {
        return [];
    }

    /**
     * Get the message shown to users.
     */
    abstract public function getPublicMessage(): string;

    /**
     * Get the detailed message for logs.
     */
    abstract public function getLogMessage(): string;
}
