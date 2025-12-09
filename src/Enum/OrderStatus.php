<?php 

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case RESERVED = 'reserved';
    case PARTIALLY_RESERVED = 'partially_reserved';
    case SHIPPED = 'shipped';
    case CANCELED = 'canceled';

    public function isEditable(): bool
    {
        return match ($this) {
            self::PENDING, self::RESERVED, self::PARTIALLY_RESERVED => true,
            self::SHIPPED, self::CANCELED => false,
        };
    }
}
