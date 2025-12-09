<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CreateOrderRequest;
use App\Entity\Client;
use App\Entity\Order;
use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/orders')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    /**
     * @throws \Exception
     */
    #[Route('', name: 'order_create', methods: ['POST'])]
    public function create(
        Request $request,
        #[MapRequestPayload] CreateOrderRequest $createRequest,
    ): JsonResponse {
        $result = $this->orderService->createAndAllocateOrder(
            $createRequest,
            $this->getClient($request)
        );

        return $this->json([
            'success' => true,
            'fully_allocated' => $result->fullyAllocated,
            'warehouses_used' => $result->warehousesUsed,
            'missing_items' => $result->missingItems,
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'order_get', methods: ['GET'])]
    public function get(Request $request, int $id): JsonResponse
    {
        $order = $this->orderService->getOrder($id, $this->getClient($request));

        return $this->json($this->serializeOrder($order));
    }

    #[Route('/{id<\d+>}/ship', name: 'order_ship', methods: ['POST'])]
    public function ship(Request $request, int $id): JsonResponse
    {
        $order = $this->orderService->shipOrder($id, $this->getClient($request));

        return $this->json([
            'success' => true,
            'order' => $this->serializeOrder($order),
        ]);
    }

    #[Route('/{id<\d+>}/cancel', name: 'order_cancel', methods: ['POST'])]
    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = $this->orderService->cancelOrder($id, $this->getClient($request));

        return $this->json([
            'success' => true,
            'order' => $this->serializeOrder($order),
        ]);
    }

    private function getClient(Request $request): Client
    {
        return $request->attributes->get('client');
    }

    private function serializeOrder(Order $order): array
    {
        $lines = [];

        foreach ($order->orderLines as $line) {
            $reservations = [];

            foreach ($line->reservations as $reservation) {
                $reservations[] = [
                    'warehouse' => $reservation->getWarehouse()->getName(),
                    'quantity' => $reservation->quantity,
                ];
            }

            $lines[] = [
                'product_sku' => $line->product->sku,
                'product_name' => $line->product->getName(),
                'requested_quantity' => $line->requestedQuantity,
                'reserved_quantity' => $line->getReservedQuantity(),
                'missing_quantity' => $line->getMissingQuantity(),
                'reservations' => $reservations,
            ];
        }

        return [
            'id' => $order->id,
            'status' => $order->getStatus()->value,
            'created_at' => $order->createdAt->format('c'),
            'shipped_at' => $order->getShippedAt()?->format('c'),
            'lines' => $lines,
        ];
    }
}
