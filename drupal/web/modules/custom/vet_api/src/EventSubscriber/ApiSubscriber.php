<?php

declare(strict_types=1);

namespace Drupal\vet_api\EventSubscriber;

use Drupal\Core\Site\Settings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/** Autenticación previa al enrutado y errores uniformes del espacio de API. */
final class ApiSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => ['authenticate', 300], KernelEvents::EXCEPTION => ['exception', 100]];
  }

  private function applies(string $path): bool {
    return $path === '/api/vet' || str_starts_with($path, '/api/vet/');
  }

  public static function response(array $data, int $status = 200, array $headers = []): JsonResponse {
    return new JsonResponse($data, $status, $headers + [
      'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
      'Vary' => 'X-API-KEY',
    ]);
  }

  public function authenticate(RequestEvent $event): void {
    if (!$this->applies($event->getRequest()->getPathInfo())) {
      return;
    }
    $expected = (string) Settings::get('vet_api_key', '');
    $provided = $event->getRequest()->headers->get('X-API-KEY', '');
    if ($expected === '' || strlen($expected) < 32) {
      $event->setResponse(self::response(['error' => ['code' => 'unavailable', 'message' => 'La API no está configurada.']], 503));
    }
    elseif ($provided === '' || !hash_equals($expected, $provided)) {
      $event->setResponse(self::response(['error' => ['code' => 'unauthorized', 'message' => 'Se requiere una API key válida en X-API-KEY.']], 401));
    }
  }

  public function exception(ExceptionEvent $event): void {
    if (!$this->applies($event->getRequest()->getPathInfo())) {
      return;
    }
    $exception = $event->getThrowable();
    $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
    $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
    $messages = [400 => 'Los parámetros de consulta no son válidos.', 403 => 'Acceso denegado.', 404 => 'No se encontró el recurso solicitado.', 405 => 'Método no permitido. Usa GET.'];
    // Nunca publicar excepciones, consultas SQL, credenciales o trazas.
    $event->setResponse(self::response(['error' => ['code' => 'http_' . $status, 'message' => $messages[$status] ?? 'No se pudo procesar la consulta.']], $status, $headers));
  }

}
