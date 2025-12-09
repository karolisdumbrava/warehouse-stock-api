<?php 

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    /** Initial state. */
    case PENDING = 'pending';

    /** Fully reserved. */
    case RESERVED = 'reserved';

    /** Some items reserved, waiting for more stock */
    case PARTIALLY_RESERVED = 'partially_reserved';

    /** Order has been shipped to customer */
    case SHIPPED = 'shipped';

    /** Order was canceled, reservations released */
    case CANCELED = 'canceled';

    /**
     * Check if order can be modified in this state.
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::PENDING, self::RESERVED, self::PARTIALLY_RESERVED => true,
            self::SHIPPED, self::CANCELED => false,
        };
    }
}
