<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Data transfer object for order creation requests.
 *
 * Contains the items to be ordered as a map of SKU to quantity.
 */
readonly class CreateOrderRequest
{
    /**
     * Constructs a new CreateOrderRequest.
     *
     * @param array<string, int> $items
     *   Map of product SKU to requested quantity.
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Items cannot be empty')]
        #[Assert\Count(min: 1, minMessage: 'At least one item is required')]
        public array $items,
    ) {
    }
}
