<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\CreateOrderRequest;
use App\Entity\Client;
use App\Entity\Product;
use App\Exception\InvalidQuantityException;
use App\Exception\ProductNotFoundException;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Service\OrderService;
use App\Service\ReoptimizationService;
use App\Service\StockAllocationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class OrderServiceTest extends TestCase
{
    private OrderService $service;
    private ProductRepository $productRepository;
    private Client $client;

    protected function setUp(): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $this->productRepository = $this->createStub(ProductRepository::class);
        $orderRepository = $this->createStub(OrderRepository::class);
        $allocationService = $this->createStub(StockAllocationService::class);
        $reoptimizationService = $this->createStub(ReoptimizationService::class);

        $this->client = new Client('Test Client', 'test-api-key');
        $this->setEntityId($this->client, 1);

        $this->service = new OrderService(
            $entityManager,
            $this->productRepository,
            $orderRepository,
            $allocationService,
            $reoptimizationService,
        );
    }

    public function testCreateOrderWithValidProducts(): void
    {
        $product = new Product('BOX-S', 'Small Box');
        $this->setEntityId($product, 1);

        $this->productRepository
            ->method('findBySku')
            ->willReturn($product);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');

        $service = new OrderService(
            $entityManager,
            $this->productRepository,
            $this->createStub(OrderRepository::class),
            $this->createStub(StockAllocationService::class),
            $this->createStub(ReoptimizationService::class),
        );

        $request = new CreateOrderRequest(['BOX-S' => 10]);
        $order = $service->createOrder($request, $this->client);

        $this->assertCount(1, $order->orderLines);
        $this->assertSame(10, $order->orderLines->first()->requestedQuantity);
    }

    public function testCreateOrderThrowsExceptionForUnknownProduct(): void
    {
        $this->productRepository
            ->method('findBySku')
            ->willReturn(null);

        $this->expectException(ProductNotFoundException::class);

        $request = new CreateOrderRequest(['UNKNOWN' => 10]);
        $this->service->createOrder($request, $this->client);
    }

    public function testCreateOrderThrowsExceptionForZeroQuantity(): void
    {
        $product = new Product('BOX-S', 'Small Box');

        $this->productRepository
            ->method('findBySku')
            ->willReturn($product);

        $this->expectException(InvalidQuantityException::class);

        $request = new CreateOrderRequest(['BOX-S' => 0]);
        $this->service->createOrder($request, $this->client);
    }

    public function testCreateOrderThrowsExceptionForNegativeQuantity(): void
    {
        $product = new Product('BOX-S', 'Small Box');

        $this->productRepository
            ->method('findBySku')
            ->willReturn($product);

        $this->expectException(InvalidQuantityException::class);

        $request = new CreateOrderRequest(['BOX-S' => -5]);
        $this->service->createOrder($request, $this->client);
    }

    public function testCreateOrderWithMultipleProducts(): void
    {
        $productA = new Product('BOX-S', 'Small Box');
        $productB = new Product('BOX-M', 'Medium Box');

        $this->productRepository
            ->method('findBySku')
            ->willReturnMap([
                ['BOX-S', $productA],
                ['BOX-M', $productB],
            ]);

        $request = new CreateOrderRequest(['BOX-S' => 10, 'BOX-M' => 5]);
        $order = $this->service->createOrder($request, $this->client);

        $this->assertCount(2, $order->orderLines);
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }
}
