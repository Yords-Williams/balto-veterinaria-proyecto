<?php

declare(strict_types=1);

namespace Drupal\vet_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\vet_api\Service\BusinessData;
use Drupal\vet_api\Service\ClinicGallery;
use Drupal\vet_api\Service\ProductCatalog;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Páginas públicas renderizadas con Twig y entidades de Drupal. */
final class HomeController extends ControllerBase {

  public function __construct(private readonly BusinessData $business, private readonly ClinicGallery $gallery, private readonly ProductCatalog $catalog) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('vet_api.business'), $container->get('vet_api.clinic_gallery'), $container->get('vet_api.catalog'));
  }

  public function home(): array {
    $gallery = $this->gallery->get();
    $featured = $this->catalog->featured();
    return [
      '#theme' => 'vet_home',
      '#business' => $this->business->getBusiness(),
      '#services' => $this->business->getServices(),
      '#clinic_gallery' => $gallery,
      '#featured_products' => $featured['products'],
      '#cache' => [
        'tags' => array_merge(['config:vet_api.business', 'node_list:servicio', 'node_list'], $gallery['cache_tags'], $featured['cache_tags']),
        'contexts' => ['user.permissions', 'user.node_grants:view', 'languages:language_interface'],
      ],
    ];
  }

}
