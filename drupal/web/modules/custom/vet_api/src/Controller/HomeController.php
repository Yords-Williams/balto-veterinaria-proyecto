<?php

declare(strict_types=1);

namespace Drupal\vet_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\vet_api\Service\BusinessData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Páginas públicas renderizadas con Twig y entidades de Drupal. */
final class HomeController extends ControllerBase {

  public function __construct(private readonly BusinessData $business) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('vet_api.business'));
  }

  public function home(): array {
    return [
      '#theme' => 'vet_home',
      '#business' => $this->business->getBusiness(),
      '#services' => $this->business->getServices(),
      '#cache' => [
        'tags' => ['config:vet_api.business', 'node_list:servicio', 'node_list'],
        'contexts' => ['user.permissions', 'user.node_grants:view', 'languages:language_interface'],
      ],
    ];
  }

  public function store(): array {
    return [
      '#theme' => 'vet_store_preview',
      '#business' => $this->business->getBusiness(),
      '#cache' => ['tags' => ['config:vet_api.business']],
    ];
  }

}
