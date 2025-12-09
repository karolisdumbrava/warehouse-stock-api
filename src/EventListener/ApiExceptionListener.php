<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\LoggableException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
readonly class ApiExceptionListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        $context = [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
        ];

        if ($client = $request->attributes->get('client')) {
            $context['client_id'] = $client->id;
            $context['client_name'] = $client->getName();
        }

        if ($exception instanceof LoggableException) {
            $this->logger->warning(
                $exception->getLogMessage(),
                array_merge($context, $exception->getLogContext(), [
                    'exception' => $exception::class,
                ])
            );

            $response = new JsonResponse(
                ['error' => $exception->getPublicMessage()],
                $exception->getStatusCode()
            );
        } elseif ($exception instanceof HttpExceptionInterface) {
            $this->logger->warning(
                $exception->getMessage(),
                array_merge($context, [
                    'exception' => $exception::class,
                    'status_code' => $exception->getStatusCode(),
                ])
            );

            $response = new JsonResponse(
                ['error' => $exception->getMessage()],
                $exception->getStatusCode()
            );
        } else {
            $this->logger->error(
                'Unexpected error: ' . $exception->getMessage(),
                array_merge($context, [
                    'exception' => $exception::class,
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                ])
            );

            $response = new JsonResponse(
                ['error' => 'An unexpected error occurred'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $event->setResponse($response);
    }
}
