<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateOrderRequest
{
    /**
     * @param array<string, int> $items SKU => quantity
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Items cannot be empty')]
        #[Assert\Count(min: 1, minMessage: 'At least one item is required')]
        #[Assert\All([
            new Assert\Positive(message: 'Quantity must be positive'),
        ])]
        public array $items,
    ) {
    }
}
