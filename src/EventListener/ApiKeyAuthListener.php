<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Repository\ClientRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
readonly class ApiKeyAuthListener
{
    private const string HEADER_NAME = 'X-API-KEY';

    public function __construct(
        private ClientRepository $clientRepository,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        $apiKey = $request->headers->get(self::HEADER_NAME);

        if ($apiKey === null) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Missing API key. Include X-API-KEY header.'],
                Response::HTTP_UNAUTHORIZED
            ));
            return;
        }

        $client = $this->clientRepository->findByApiKey($apiKey);

        if ($client === null) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Invalid API key.'],
                Response::HTTP_UNAUTHORIZED
            ));
            return;
        }

        $request->attributes->set('client', $client);
    }
}
