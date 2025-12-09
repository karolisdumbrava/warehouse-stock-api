<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Client;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\Product;
use App\Enum\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $this->client = new Client('Test Client', 'test-api-key');
    }

    public function testNewOrderHasPendingStatus(): void
    {
        $order = new Order($this->client);

        $this->assertSame(OrderStatus::PENDING, $order->getStatus());
    }

    public function testNewOrderHasCreatedAtTimestamp(): void
    {
        $before = new \DateTimeImmutable();
        $order = new Order($this->client);
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $order->createdAt);
        $this->assertLessThanOrEqual($after, $order->createdAt);
    }

    public function testNewOrderHasNoShippedAt(): void
    {
        $order = new Order($this->client);

        $this->assertNull($order->getShippedAt());
    }

    public function testNewOrderHasEmptyOrderLines(): void
    {
        $order = new Order($this->client);

        $this->assertCount(0, $order->orderLines);
    }

    public function testCanAddOrderLine(): void
    {
        $order = new Order($this->client);
        $product = new Product('TEST-SKU', 'Test Product');
        $orderLine = new OrderLine($order, $product, 10);

        $order->addOrderLine($orderLine);

        $this->assertCount(1, $order->orderLines);
        $this->assertTrue($order->orderLines->contains($orderLine));
    }

    public function testAddingSameOrderLineTwiceDoesNotDuplicate(): void
    {
        $order = new Order($this->client);
        $product = new Product('TEST-SKU', 'Test Product');
        $orderLine = new OrderLine($order, $product, 10);

        $order->addOrderLine($orderLine);
        $order->addOrderLine($orderLine);

        $this->assertCount(1, $order->orderLines);
    }

    public function testCanRemoveOrderLine(): void
    {
        $order = new Order($this->client);
        $product = new Product('TEST-SKU', 'Test Product');
        $orderLine = new OrderLine($order, $product, 10);

        $order->addOrderLine($orderLine);
        $order->removeOrderLine($orderLine);

        $this->assertCount(0, $order->orderLines);
    }

    public function testShipSetsStatusAndTimestamp(): void
    {
        $order = new Order($this->client);

        $order->ship();

        $this->assertSame(OrderStatus::SHIPPED, $order->getStatus());
        $this->assertNotNull($order->getShippedAt());
    }

    public function testCannotShipCanceledOrder(): void
    {
        $order = new Order($this->client);
        $order->cancel();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot ship a canceled order.');

        $order->ship();
    }

    public function testCancelSetsStatus(): void
    {
        $order = new Order($this->client);

        $order->cancel();

        $this->assertSame(OrderStatus::CANCELED, $order->getStatus());
    }

    public function testCannotCancelShippedOrder(): void
    {
        $order = new Order($this->client);
        $order->ship();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot cancel a shipped order.');

        $order->cancel();
    }

    public function testCanBeShippedReturnsTrueForPendingOrder(): void
    {
        $order = new Order($this->client);

        $this->assertTrue($order->canBeShipped());
    }

    public function testCanBeShippedReturnsFalseForShippedOrder(): void
    {
        $order = new Order($this->client);
        $order->ship();

        $this->assertFalse($order->canBeShipped());
    }

    public function testCanBeShippedReturnsFalseForCanceledOrder(): void
    {
        $order = new Order($this->client);
        $order->cancel();

        $this->assertFalse($order->canBeShipped());
    }

    public function testCanBeCanceledReturnsTrueForPendingOrder(): void
    {
        $order = new Order($this->client);

        $this->assertTrue($order->canBeCanceled());
    }

    public function testCanBeCanceledReturnsFalseForShippedOrder(): void
    {
        $order = new Order($this->client);
        $order->ship();

        $this->assertFalse($order->canBeCanceled());
    }

    public function testEmptyOrderIsNotFullyReserved(): void
    {
        $order = new Order($this->client);

        $this->assertFalse($order->isFullyReserved());
    }

    public function testEmptyOrderIsNotPartiallyReserved(): void
    {
        $order = new Order($this->client);

        $this->assertFalse($order->isPartiallyReserved());
    }
}
