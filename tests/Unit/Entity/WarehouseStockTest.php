<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Product;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use PHPUnit\Framework\TestCase;

class WarehouseStockTest extends TestCase
{
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        $this->warehouse = new Warehouse('Test Warehouse', 'Test Location');
        $this->product = new Product('TEST-SKU', 'Test Product');
    }

    public function testNewStockHasCorrectInitialValues(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 100);

        $this->assertSame(100, $stock->getQuantity());
        $this->assertSame(0, $stock->getReservedQuantity());
        $this->assertSame(100, $stock->getAvailableQuantity());
    }

    public function testAvailableQuantityIsCalculatedCorrectly(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 100);
        $stock->setReservedQuantity(30);

        $this->assertSame(70, $stock->getAvailableQuantity());
    }

    public function testReserveIncreasesReservedQuantity(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 100);

        $stock->reserve(30);

        $this->assertSame(30, $stock->getReservedQuantity());
        $this->assertSame(70, $stock->getAvailableQuantity());
    }

    public function testReserveMultipleTimes(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 100);

        $stock->reserve(30);
        $stock->reserve(20);

        $this->assertSame(50, $stock->getReservedQuantity());
        $this->assertSame(50, $stock->getAvailableQuantity());
    }

    public function testCannotReserveMoreThanAvailable(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 100);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot reserve 150 units. Only 100 available.');

        $stock->reserve(150);
    }

    public function testCannotReserveMoreThanRemainingAvailable(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 100);
        $stock->reserve(80);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot reserve 30 units. Only 20 available.');

        $stock->reserve(30);
    }

    public function testReleaseReservationDecreasesReservedQuantity(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 100);
        $stock->reserve(50);

        $stock->releaseReservation(30);

        $this->assertSame(20, $stock->getReservedQuantity());
        $this->assertSame(80, $stock->getAvailableQuantity());
    }

    public function testCannotReleaseMoreThanReserved(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 100);
        $stock->reserve(30);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot release 50 units. Only 30 reserved.');

        $stock->releaseReservation(50);
    }

    public function testShipDecreasesBothQuantityAndReserved(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 100);
        $stock->reserve(50);

        $stock->ship(30);

        $this->assertSame(70, $stock->getQuantity());
        $this->assertSame(20, $stock->getReservedQuantity());
        $this->assertSame(50, $stock->getAvailableQuantity());
    }

    public function testCannotShipMoreThanReserved(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 100);
        $stock->reserve(30);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot ship 50 units. Only 30 reserved.');

        $stock->ship(50);
    }

    public function testCanReserveExactlyAvailableAmount(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 100);

        $stock->reserve(100);

        $this->assertSame(100, $stock->getReservedQuantity());
        $this->assertSame(0, $stock->getAvailableQuantity());
    }
}
