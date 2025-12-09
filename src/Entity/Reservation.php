<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Links an order line to warehouse stock, recording the reserved quantity.
 *
 * An order line may have multiple reservations from different warehouses.
 */
#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservations')]
#[ORM\Index(name: 'idx_reservation_order_line', columns: ['order_line_id'])]
#[ORM\Index(name: 'idx_reservation_warehouse_stock', columns: ['warehouse_stock_id'])]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\ManyToOne(targetEntity: OrderLine::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private OrderLine $orderLine {
        get {
            return $this->orderLine;
        }
    }

    #[ORM\ManyToOne(targetEntity: WarehouseStock::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    public WarehouseStock $warehouseStock {
        get {
            return $this->warehouseStock;
        }
    }

    #[ORM\Column]
    #[Assert\Positive]
    public int $quantity {
        get {
            return $this->quantity;
        }
    }

    public function __construct(OrderLine $orderLine, WarehouseStock $warehouseStock, int $quantity)
    {
        $this->orderLine = $orderLine;
        $this->warehouseStock = $warehouseStock;
        $this->quantity = $quantity;
    }

    /**
     * Get the warehouse this reservation is from.
     */
    public function getWarehouse(): Warehouse
    {
        return $this->warehouseStock->warehouse;
    }

    /**
     * Get the product being reserved.
     */
    public function getProduct(): Product
    {
        return $this->orderLine->product;
    }

}
