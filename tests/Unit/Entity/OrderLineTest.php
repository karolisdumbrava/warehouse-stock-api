<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Client;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\Product;
use App\Entity\Reservation;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use PHPUnit\Framework\TestCase;

class OrderLineTest extends TestCase
{
    private Order $order;
    private Product $product;

    protected function setUp(): void
    {
        $client = new Client('Test Client', 'test-api-key');
        $this->order = new Order($client);
        $this->product = new Product('TEST-SKU', 'Test Product');
    }

    public function testNewOrderLineHasCorrectInitialValues(): void
    {
        $line = new OrderLine($this->order, $this->product, 50);

        $this->assertSame(50, $line->requestedQuantity);
        $this->assertSame(0, $line->getReservedQuantity());
        $this->assertSame(50, $line->getMissingQuantity());
        $this->assertCount(0, $line->reservations);
    }

    public function testIsFullyReservedWhenReservedEqualsRequested(): void
    {
        $line = new OrderLine($this->order, $this->product, 50);
        $line->setReservedQuantity(50);

        $this->assertTrue($line->isFullyReserved());
    }

    public function testIsNotFullyReservedWhenReservedLessThanRequested(): void
    {
        $line = new OrderLine($this->order, $this->product, 50);
        $line->setReservedQuantity(30);

        $this->assertFalse($line->isFullyReserved());
    }

    public function testIsPartiallyReservedWhenSomeButNotAllReserved(): void
    {
        $line = new OrderLine($this->order, $this->product, 50);
        $line->setReservedQuantity(30);

        $this->assertTrue($line->isPartiallyReserved());
    }

    public function testIsNotPartiallyReservedWhenNoneReserved(): void
    {
        $line = new OrderLine($this->order, $this->product, 50);

        $this->assertFalse($line->isPartiallyReserved());
    }

    public function testIsNotPartiallyReservedWhenFullyReserved(): void
    {
        $line = new OrderLine($this->order, $this->product, 50);
        $line->setReservedQuantity(50);

        $this->assertFalse($line->isPartiallyReserved());
    }

    public function testMissingQuantityCalculation(): void
    {
        $line = new OrderLine($this->order, $this->product, 50);
        $line->setReservedQuantity(30);

        $this->assertSame(20, $line->getMissingQuantity());
    }

    public function testMissingQuantityIsZeroWhenFullyReserved(): void
    {
        $line = new OrderLine($this->order, $this->product, 50);
        $line->setReservedQuantity(50);

        $this->assertSame(0, $line->getMissingQuantity());
    }

    public function testMissingQuantityNeverNegative(): void
    {
        $line = new OrderLine($this->order, $this->product, 50);
        $line->setReservedQuantity(100); // Over-reserved

        $this->assertSame(0, $line->getMissingQuantity());
    }

    public function testAddReservationUpdatesReservedQuantity(): void
    {
        $line = new OrderLine($this->order, $this->product, 50);
        $warehouse = new Warehouse('Test', 'Location');
        $stock = new WarehouseStock($warehouse, $this->product, 100);
        $reservation = new Reservation($line, $stock, 30);

        $line->addReservation($reservation);

        $this->assertSame(30, $line->getReservedQuantity());
        $this->assertCount(1, $line->reservations);
    }

    public function testAddingSameReservationTwiceDoesNotDuplicate(): void
    {
        $line = new OrderLine($this->order, $this->product, 50);
        $warehouse = new Warehouse('Test', 'Location');
        $stock = new WarehouseStock($warehouse, $this->product, 100);
        $reservation = new Reservation($line, $stock, 30);

        $line->addReservation($reservation);
        $line->addReservation($reservation);

        $this->assertSame(30, $line->getReservedQuantity());
        $this->assertCount(1, $line->reservations);
    }

    public function testClearReservationsResetsEverything(): void
    {
        $line = new OrderLine($this->order, $this->product, 50);
        $warehouse = new Warehouse('Test', 'Location');
        $stock = new WarehouseStock($warehouse, $this->product, 100);
        $reservation = new Reservation($line, $stock, 30);

        $line->addReservation($reservation);
        $line->clearReservations();

        $this->assertSame(0, $line->getReservedQuantity());
        $this->assertCount(0, $line->reservations);
    }
}
