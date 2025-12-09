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

readonly class OrderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository      $productRepository,
        private OrderRepository        $orderRepository,
        private StockAllocationService $allocationService,
        private ReoptimizationService  $reoptimizationService,
    ) {
    }

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
     * @throws \Exception
     */
    public function createAndAllocateOrder(CreateOrderRequest $request, Client $client): AllocationResult
    {
        $order = $this->createOrder($request, $client);

        return $this->allocationService->allocateOrder($order);
    }

    public function getOrder(int $orderId, Client $client): Order
    {
        $order = $this->orderRepository->findByIdAndClient($orderId, $client);

        if ($order === null) {
            throw new OrderNotFoundException($orderId, $client->id);
        }

        return $order;
    }

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
