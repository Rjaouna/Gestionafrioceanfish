<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AjaxExceptionSubscriber
{
    #[AsEventListener]
    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->headers->get('X-Requested-With') !== 'XMLHttpRequest'
            && !$request->headers->contains('Accept', 'application/json')) {
            return;
        }

        $exception = $event->getThrowable();
        $status = match (true) {
            $exception instanceof AccessDeniedException => 403,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            $exception instanceof \DomainException, $exception instanceof \InvalidArgumentException => 422,
            default => 500,
        };
        $message = $status >= 500 ? 'Une erreur interne est survenue.' : $exception->getMessage();

        $event->setResponse(new JsonResponse([
            'success' => false,
            'message' => $message !== '' ? $message : 'Action refusée.',
            'data' => [],
        ], $status));
    }
}
