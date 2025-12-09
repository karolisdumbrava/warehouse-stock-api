<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Client;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\Product;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Repository\WarehouseStockRepository;
use App\Service\StockAllocationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class StockAllocationServiceTest extends TestCase
{
    private StockAllocationService $service;
    private EntityManagerInterface $entityManager;
    private WarehouseStockRepository $warehouseStockRepository;
    private Client $client;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->warehouseStockRepository = $this->createStub(WarehouseStockRepository::class);
        $this->client = new Client('Test Client', 'test-api-key');

        $this->service = new StockAllocationService(
            $this->entityManager,
            $this->warehouseStockRepository
        );
    }

    public function testAllocatesFromSingleWarehouseWhenSufficient(): void
    {
        $warehouse = $this->createWarehouse(1, 'Vilnius');
        $product = $this->createProduct(1, 'BOX-S');
        $stock = new WarehouseStock($warehouse, $product, 100);

        $order = new Order($this->client);
        $orderLine = new OrderLine($order, $product, 50);
        $order->addOrderLine($orderLine);

        $this->warehouseStockRepository
            ->method('findAvailableStocksForProducts')
            ->willReturn([$stock]);

        $result = $this->service->allocateOrder($order);

        $this->assertTrue($result->fullyAllocated);
        $this->assertSame(1, $result->warehousesUsed);
        $this->assertEmpty($result->missingItems);
        $this->assertSame(50, $orderLine->getReservedQuantity());
    }

    /**
     * @throws \Exception
     */
    public function testAllocatesFromMultipleWarehousesWhenNeeded(): void
    {
        $warehouse1 = $this->createWarehouse(1, 'Vilnius');
        $warehouse2 = $this->createWarehouse(2, 'Kaunas');
        $product = $this->createProduct(1, 'BOX-S');

        $stock1 = new WarehouseStock($warehouse1, $product, 30);
        $stock2 = new WarehouseStock($warehouse2, $product, 40);

        $order = new Order($this->client);
        $orderLine = new OrderLine($order, $product, 50);
        $order->addOrderLine($orderLine);

        $this->warehouseStockRepository
            ->method('findAvailableStocksForProducts')
            ->willReturn([$stock1, $stock2]);

        $result = $this->service->allocateOrder($order);

        $this->assertTrue($result->fullyAllocated);
        $this->assertSame(2, $result->warehousesUsed);
        $this->assertSame(50, $orderLine->getReservedQuantity());
    }

    public function testMinimizesWarehouseCount(): void
    {
        $warehouse1 = $this->createWarehouse(1, 'Vilnius');
        $warehouse2 = $this->createWarehouse(2, 'Kaunas');

        $productA = $this->createProduct(1, 'BOX-S');
        $productB = $this->createProduct(2, 'BOX-M');

        // Vilnius has both products - should be preferred
        $stock1A = new WarehouseStock($warehouse1, $productA, 100);
        $stock1B = new WarehouseStock($warehouse1, $productB, 100);

        // Kaunas only has product A
        $stock2A = new WarehouseStock($warehouse2, $productA, 100);

        $order = new Order($this->client);
        $lineA = new OrderLine($order, $productA, 20);
        $lineB = new OrderLine($order, $productB, 20);
        $order->addOrderLine($lineA);
        $order->addOrderLine($lineB);

        $this->warehouseStockRepository
            ->method('findAvailableStocksForProducts')
            ->willReturn([$stock1A, $stock1B, $stock2A]);

        $result = $this->service->allocateOrder($order);

        $this->assertTrue($result->fullyAllocated);
        $this->assertSame(1, $result->warehousesUsed);
    }

    public function testHandlesPartialFulfillment(): void
    {
        $warehouse = $this->createWarehouse(1, 'Vilnius');
        $product = $this->createProduct(1, 'BOX-S');
        $stock = new WarehouseStock($warehouse, $product, 30);

        $order = new Order($this->client);
        $orderLine = new OrderLine($order, $product, 50);
        $order->addOrderLine($orderLine);

        $this->warehouseStockRepository
            ->method('findAvailableStocksForProducts')
            ->willReturn([$stock]);

        $result = $this->service->allocateOrder($order);

        $this->assertFalse($result->fullyAllocated);
        $this->assertSame(['BOX-S' => 20], $result->missingItems);
        $this->assertSame(30, $orderLine->getReservedQuantity());
    }

    public function testHandlesNoStockAvailable(): void
    {
        $product = $this->createProduct(1, 'BOX-S');

        $order = new Order($this->client);
        $orderLine = new OrderLine($order, $product, 50);
        $order->addOrderLine($orderLine);

        $this->warehouseStockRepository
            ->method('findAvailableStocksForProducts')
            ->willReturn([]);

        $result = $this->service->allocateOrder($order);

        $this->assertFalse($result->fullyAllocated);
        $this->assertSame(['BOX-S' => 50], $result->missingItems);
        $this->assertSame(0, $result->warehousesUsed);
    }

    public function testHandlesEmptyOrder(): void
    {
        $order = new Order($this->client);

        $result = $this->service->allocateOrder($order);

        $this->assertTrue($result->fullyAllocated);
        $this->assertSame(0, $result->warehousesUsed);
        $this->assertEmpty($result->missingItems);
    }

    public function testReservesCorrectQuantityInWarehouseStock(): void
    {
        $warehouse = $this->createWarehouse(1, 'Vilnius');
        $product = $this->createProduct(1, 'BOX-S');
        $stock = new WarehouseStock($warehouse, $product, 100);

        $order = new Order($this->client);
        $orderLine = new OrderLine($order, $product, 30);
        $order->addOrderLine($orderLine);

        $this->warehouseStockRepository
            ->method('findAvailableStocksForProducts')
            ->willReturn([$stock]);

        $this->service->allocateOrder($order);

        $this->assertSame(30, $stock->getReservedQuantity());
        $this->assertSame(70, $stock->getAvailableQuantity());
    }

    public function testCreatesReservationRecords(): void
    {
        $warehouse = $this->createWarehouse(1, 'Vilnius');
        $product = $this->createProduct(1, 'BOX-S');
        $stock = new WarehouseStock($warehouse, $product, 100);

        $order = new Order($this->client);
        $orderLine = new OrderLine($order, $product, 30);
        $order->addOrderLine($orderLine);

        $this->warehouseStockRepository
            ->method('findAvailableStocksForProducts')
            ->willReturn([$stock]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');

        $service = new StockAllocationService($entityManager, $this->warehouseStockRepository);
        $service->allocateOrder($order);

        $this->assertCount(1, $orderLine->reservations);
        $reservation = $orderLine->reservations->first();
        $this->assertSame(30, $reservation->quantity);
    }

    public function testAllocatesMultipleProductsFromSameWarehouse(): void
    {
        $warehouse = $this->createWarehouse(1, 'Vilnius');
        $productA = $this->createProduct(1, 'BOX-S');
        $productB = $this->createProduct(2, 'BOX-M');

        $stockA = new WarehouseStock($warehouse, $productA, 100);
        $stockB = new WarehouseStock($warehouse, $productB, 100);

        $order = new Order($this->client);
        $lineA = new OrderLine($order, $productA, 30);
        $lineB = new OrderLine($order, $productB, 20);
        $order->addOrderLine($lineA);
        $order->addOrderLine($lineB);

        $this->warehouseStockRepository
            ->method('findAvailableStocksForProducts')
            ->willReturn([$stockA, $stockB]);

        $result = $this->service->allocateOrder($order);

        $this->assertTrue($result->fullyAllocated);
        $this->assertSame(1, $result->warehousesUsed);
        $this->assertSame(30, $lineA->getReservedQuantity());
        $this->assertSame(20, $lineB->getReservedQuantity());
    }

    private function createWarehouse(int $id, string $name): Warehouse
    {
        $warehouse = new Warehouse($name);
        $reflection = new \ReflectionClass($warehouse);
        $property = $reflection->getProperty('id');
        $property->setValue($warehouse, $id);

        return $warehouse;
    }

    private function createProduct(int $id, string $sku): Product
    {
        $product = new Product($sku, $sku . ' Product');
        $reflection = new \ReflectionClass($product);
        $property = $reflection->getProperty('id');
        $property->setValue($product, $id);

        return $product;
    }
}
