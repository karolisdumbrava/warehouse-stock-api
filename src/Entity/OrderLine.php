<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderLineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Individual line item within an order.
 *
 * Tracks requested quantity and reservations from various warehouses.
 */
#[ORM\Entity(repositoryClass: OrderLineRepository::class)]
#[ORM\Table(name: 'order_lines')]
#[ORM\Index(name: 'idx_order_line_order', columns: ['order_id'])]
class OrderLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'orderLines')]
    #[ORM\JoinColumn(nullable: false)]
    private Order $order {
        get {
            return $this->order;
        }
    }

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'orderLines')]
    #[ORM\JoinColumn(nullable: false)]
    public Product $product {
        get {
            return $this->product;
        }
    }

    #[ORM\Column]
    #[Assert\Positive]
    public int $requestedQuantity {
        get {
            return $this->requestedQuantity;
        }
    }

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $reservedQuantity = 0;

    /** @var Collection<int, Reservation> */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'orderLine', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $reservations {
        get {
            return $this->reservations;
        }
    }

    public function __construct(Order $order, Product $product, int $requestedQuantity)
    {
        $this->order = $order;
        $this->product = $product;
        $this->requestedQuantity = $requestedQuantity;
        $this->reservations = new ArrayCollection();
    }

    public function getReservedQuantity(): int
    {
        return $this->reservedQuantity;
    }

    public function setReservedQuantity(int $reservedQuantity): self
    {
        $this->reservedQuantity = $reservedQuantity;
        return $this;
    }

    public function addReservation(Reservation $reservation): self
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $this->reservedQuantity += $reservation->quantity;
        }
        return $this;
    }

    public function removeReservation(Reservation $reservation): self
    {
        if ($this->reservations->removeElement($reservation)) {
            $this->reservedQuantity -= $reservation->quantity;
        }
        return $this;
    }

    /**
     * Clear all reservations and reset reserved quantity.
     */
    public function clearReservations(): self
    {
        $this->reservations->clear();
        $this->reservedQuantity = 0;
        return $this;
    }

    /**
     * Calculate how many units are still needed.
     */
    public function getMissingQuantity(): int
    {
        return max(0, $this->requestedQuantity - $this->reservedQuantity);
    }

    /**
     * Check if requested quantity has been fully reserved.
     */
    public function isFullyReserved(): bool
    {
        return $this->reservedQuantity >= $this->requestedQuantity;
    }

    /**
     * Check if some but not all quantity has been reserved.
     */
    public function isPartiallyReserved(): bool
    {
        return $this->reservedQuantity > 0 && $this->reservedQuantity < $this->requestedQuantity;
    }
}
