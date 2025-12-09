<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

/**
 * Represents a customer order containing one or more order lines.
 *
 * Orders go through the following lifecycle:
 * - PENDING: Initial state, no stock reserved
 * - PARTIALLY_RESERVED: Some items reserved, waiting for more stock
 * - RESERVED: All items fully reserved
 * - SHIPPED: Order has been shipped, stock decremented
 * - CANCELED: Order canceled, reservations released
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
#[ORM\Index(columns: ['status'], name: 'idx_order_status')]
#[ORM\Index(columns: ['client_id'], name: 'idx_order_client')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private Client $client {
        get {
            return $this->client;
        }
    }

    #[ORM\Column(enumType: OrderStatus::class)]
    private OrderStatus $status;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt {
        get {
            return $this->createdAt;
        }
    }

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $shippedAt = null;

    /** @var Collection<int, OrderLine> */
    #[ORM\OneToMany(targetEntity: OrderLine::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $orderLines {
        get {
            return $this->orderLines;
        }
    }

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->status = OrderStatus::PENDING;
        $this->createdAt = new \DateTimeImmutable();
        $this->orderLines = new ArrayCollection();
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getShippedAt(): ?\DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function setShippedAt(?\DateTimeImmutable $shippedAt): self
    {
        $this->shippedAt = $shippedAt;
        return $this;
    }

    public function addOrderLine(OrderLine $orderLine): self
    {
        if (!$this->orderLines->contains($orderLine)) {
            $this->orderLines->add($orderLine);
        }
        return $this;
    }

    public function removeOrderLine(OrderLine $orderLine): self
    {
        $this->orderLines->removeElement($orderLine);
        return $this;
    }

    public function isFullyReserved(): bool
    {
        if ($this->orderLines->isEmpty()) {
            return false;
        }

        foreach ($this->orderLines as $line) {
            if (!$line->isFullyReserved()) {
                return false;
            }
        }
        return true;
    }

    public function isPartiallyReserved(): bool
    {
        $hasReservation = false;
        $allFulfilled = true;

        foreach ($this->orderLines as $line) {
            if ($line->getReservedQuantity() > 0) {
                $hasReservation = true;
            }
            if (!$line->isFullyReserved()) {
                $allFulfilled = false;
            }
        }

        return $hasReservation && !$allFulfilled;
    }

    /**
     * Update order status based on current reservation state.
     */
    public function updateStatusFromReservations(): self
    {
        if ($this->isFullyReserved()) {
            $this->status = OrderStatus::RESERVED;
        } elseif ($this->isPartiallyReserved()) {
            $this->status = OrderStatus::PARTIALLY_RESERVED;
        }
        return $this;
    }

    public function canBeShipped(): bool
    {
        return $this->status !== OrderStatus::CANCELED
            && $this->status !== OrderStatus::SHIPPED;
    }

    public function canBeCanceled(): bool
    {
        return $this->status !== OrderStatus::SHIPPED
            && $this->status !== OrderStatus::CANCELED;
    }

    /**
     * Mark order as shipped.
     *
     * @throws DomainException If order is canceled
     */
    public function ship(): self
    {
        if ($this->status === OrderStatus::CANCELED) {
            throw new DomainException('Cannot ship a canceled order.');
        }

        $this->status = OrderStatus::SHIPPED;
        $this->shippedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Cancel the order.
     *
     * @throws DomainException If order is already shipped
     */
    public function cancel(): self
    {
        if ($this->status === OrderStatus::SHIPPED) {
            throw new DomainException('Cannot cancel a shipped order.');
        }

        $this->status = OrderStatus::CANCELED;
        return $this;
    }
}
