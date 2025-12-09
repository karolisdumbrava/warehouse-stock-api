<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WarehouseStockRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DomainException;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Stock level for a specific product at a specific warehouse.
 *
 * Tracks total quantity and reserved quantity separately to support
 * concurrent order processing without overselling.
 */
#[ORM\Entity(repositoryClass: WarehouseStockRepository::class)]
#[ORM\Table(name: 'warehouse_stocks')]
#[ORM\UniqueConstraint(name: 'warehouse_product_unique', columns: ['warehouse_id', 'product_id'])]
#[ORM\Index(name: 'idx_product', columns: ['product_id'])]
class WarehouseStock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\ManyToOne(targetEntity: Warehouse::class, inversedBy: 'stocks')]
    #[ORM\JoinColumn(nullable: false)]
    public Warehouse $warehouse {
        get {
            return $this->warehouse;
        }
    }

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'warehouseStocks')]
    #[ORM\JoinColumn(nullable: false)]
    public Product $product {
        get {
            return $this->product;
        }
    }

    /** Total physical quantity in warehouse */
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $quantity;

    /** Quantity reserved for pending orders */
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $reservedQuantity = 0;

    /** @var Collection<int, Reservation> */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'warehouseStock')]
    private Collection $reservations {
        get {
            return $this->reservations;
        }
    }

    public function __construct(Warehouse $warehouse, Product $product, int $quantity)
    {
        $this->warehouse = $warehouse;
        $this->product = $product;
        $this->quantity = $quantity;
        $this->reservations = new ArrayCollection();
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
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

    /**
     * Available = total - reserved
     */
    public function getAvailableQuantity(): int
    {
        return $this->quantity - $this->reservedQuantity;
    }

    /**
     * Reserve stock for an order.
     *
     * @param int $amount Quantity to reserve
     * @throws DomainException If insufficient stock available
     */
    public function reserve(int $amount): self
    {
        if ($amount > $this->getAvailableQuantity()) {
            throw new DomainException(sprintf(
                'Cannot reserve %d units. Only %d available.',
                $amount,
                $this->getAvailableQuantity()
            ));
        }

        $this->reservedQuantity += $amount;
        return $this;
    }

    /**
     * Release a reservation back to available stock.
     *
     * @param int $amount Quantity to release
     * @throws DomainException If amount exceeds reserved quantity
     */
    public function releaseReservation(int $amount): self
    {
        if ($amount > $this->reservedQuantity) {
            throw new DomainException(sprintf(
                'Cannot release %d units. Only %d reserved.',
                $amount,
                $this->reservedQuantity
            ));
        }

        $this->reservedQuantity -= $amount;
        return $this;
    }

    /**
     * Ship reserved stock (decrements both quantity and reserved).
     *
     * @param int $amount Quantity to ship
     * @throws DomainException If amount exceeds reserved quantity
     */
    public function ship(int $amount): self
    {
        if ($amount > $this->reservedQuantity) {
            throw new DomainException(sprintf(
                'Cannot ship %d units. Only %d reserved.',
                $amount,
                $this->reservedQuantity
            ));
        }

        $this->quantity -= $amount;
        $this->reservedQuantity -= $amount;
        return $this;
    }

}
