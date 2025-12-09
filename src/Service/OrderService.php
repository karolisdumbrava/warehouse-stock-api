<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\AllocationResult;
use App\DTO\CreateOrderRequest;
use App\Entity\Client;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Exception\InvalidOrderStateException;
use App\Exception\InvalidQuantityException;
use App\Exception\OrderNotFoundException;
use App\Exception\ProductNotFoundException;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

/**
 * Handles order lifecycle operations.
 *
 * Provides methods for creating, retrieving, shipping, and canceling orders.
 * Coordinates with StockAllocationService for inventory management and
 * ReoptimizationService for improving allocations when stock is freed.
 */
readonly class OrderService
{
    /**
     * Constructs a new OrderService.
     *
     * @param EntityManagerInterface $entityManager
     *   The entity manager for database operations.
     * @param ProductRepository $productRepository
     *   Repository for querying products.
     * @param OrderRepository $orderRepository
     *   Repository for querying orders.
     * @param StockAllocationService $allocationService
     *   Service for allocating stock to orders.
     * @param ReoptimizationService $reoptimizationService
     *   Service for reoptimizing partially reserved orders.
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private OrderRepository $orderRepository,
        private StockAllocationService $allocationService,
        private ReoptimizationService $reoptimizationService,
    ) {
    }

    /**
     * Creates a new order without allocating stock.
     *
     * Validates that all product SKUs exist and quantities are positive.
     * The order is persisted but not flushed to allow for transaction control.
     *
     * @param CreateOrderRequest $request
     *   The order creation request containing items.
     * @param Client $client
     *   The client creating the order.
     *
     * @return Order
     *   The newly created order entity.
     *
     * @throws ProductNotFoundException
     *   When a product SKU does not exist.
     * @throws InvalidQuantityException
     *   When a quantity is zero or negative.
     */
    public function createOrder(CreateOrderRequest $request, Client $client): Order
    {
        $order = new Order($client);

        foreach ($request->items as $sku => $quantity) {
            $product = $this->productRepository->findBySku($sku);

            if ($product === null) {
                throw new ProductNotFoundException($sku);
            }

            if ($quantity <= 0) {
                throw new InvalidQuantityException($sku);
            }

            $orderLine = new OrderLine($order, $product, $quantity);
            $order->addOrderLine($orderLine);
        }

        $this->entityManager->persist($order);

        return $order;
    }

    /**
     * Creates an order and immediately attempts stock allocation.
     *
     * Combines order creation with stock allocation in a single operation.
     * The allocation uses a greedy algorithm to minimize warehouse usage.
     *
     * @param CreateOrderRequest $request
     *   The order creation request containing items.
     * @param Client $client
     *   The client creating the order.
     *
     * @return AllocationResult
     *   Result containing allocation status, warehouses used, and missing items.
     *
     * @throws ProductNotFoundException
     *   When a product SKU does not exist.
     * @throws InvalidQuantityException
     *   When a quantity is zero or negative.
     * @throws Exception
     *   When database transaction fails.
     */
    public function createAndAllocateOrder(CreateOrderRequest $request, Client $client): AllocationResult
    {
        $order = $this->createOrder($request, $client);

        return $this->allocationService->allocateOrder($order);
    }

    /**
     * Retrieves an order by ID, ensuring it belongs to the client.
     *
     * @param int $orderId
     *   The order ID to retrieve.
     * @param Client $client
     *   The client who should own the order.
     *
     * @return Order
     *   The order entity.
     *
     * @throws OrderNotFoundException
     *   When order does not exist or belongs to another client.
     */
    public function getOrder(int $orderId, Client $client): Order
    {
        $order = $this->orderRepository->findByIdAndClient($orderId, $client);

        if ($order === null) {
            throw new OrderNotFoundException($orderId, $client->id);
        }

        return $order;
    }

    /**
     * Ships an order, decrementing stock from warehouses.
     *
     * Marks the order as shipped and reduces both quantity and reserved
     * quantity in all associated warehouse stocks. Cannot ship orders
     * that are already shipped or canceled.
     *
     * @param int $orderId
     *   The order ID to ship.
     * @param Client $client
     *   The client who owns the order.
     *
     * @return Order
     *   The updated order entity.
     *
     * @throws OrderNotFoundException
     *   When order does not exist or belongs to another client.
     * @throws InvalidOrderStateException
     *   When order cannot be shipped in its current state.
     */
    public function shipOrder(int $orderId, Client $client): Order
    {
        $order = $this->getOrder($orderId, $client);

        if (!$order->canBeShipped()) {
            throw new InvalidOrderStateException(
                $orderId,
                $order->getStatus()->value,
                'ship'
            );
        }

        $this->entityManager->wrapInTransaction(function () use ($order) {
            foreach ($order->orderLines as $line) {
                foreach ($line->reservations as $reservation) {
                    $reservation->warehouseStock->ship($reservation->quantity);
                }
            }

            $order->ship();
        });

        return $order;
    }

    /**
     * Cancels an order, releasing all reservations back to warehouse stock.
     *
     * Releases all reserved stock and triggers reoptimization for other
     * partially reserved orders that might benefit from the freed stock.
     * Cannot cancel orders that are already shipped.
     *
     * @param int $orderId
     *   The order ID to cancel.
     * @param Client $client
     *   The client who owns the order.
     *
     * @return Order
     *   The updated order entity.
     *
     * @throws OrderNotFoundException
     *   When order does not exist or belongs to another client.
     * @throws InvalidOrderStateException
     *   When order cannot be canceled in its current state.
     */
    public function cancelOrder(int $orderId, Client $client): Order
    {
        $order = $this->getOrder($orderId, $client);

        if (!$order->canBeCanceled()) {
            throw new InvalidOrderStateException(
                $orderId,
                $order->getStatus()->value,
                'cancel'
            );
        }

        $freedProductIds = [];

        $this->entityManager->wrapInTransaction(function () use ($order, &$freedProductIds) {
            foreach ($order->orderLines as $line) {
                foreach ($line->reservations as $reservation) {
                    $reservation->warehouseStock->releaseReservation($reservation->quantity);
                    $freedProductIds[] = $line->product->id;
                }
                $line->clearReservations();
            }

            $order->cancel();
        });

        if (!empty($freedProductIds)) {
            $this->reoptimizationService->reoptimizeForProducts(array_unique($freedProductIds));
        }

        return $order;
    }
}
