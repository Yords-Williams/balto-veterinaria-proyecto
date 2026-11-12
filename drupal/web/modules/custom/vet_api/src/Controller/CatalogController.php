<?php

declare(strict_types=1);

namespace Drupal\vet_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\vet_api\Service\BusinessData;
use Drupal\vet_api\Service\ProductCatalog;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CatalogController extends ControllerBase {

  public function __construct(
    private readonly ProductCatalog $catalog,
    private readonly BusinessData $business,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('vet_api.catalog'), $container->get('vet_api.business'));
  }

  public function catalog(Request $request): array {
    $q = trim((string) $request->query->get('q', ''));
    $category = (string) $request->query->get('category', '');
    $species = (string) $request->query->get('species', '');
    $categories = $this->catalog->categories();
    if (mb_strlen($q) > 100 || ($category !== '' && !isset($categories[$category])) || ($species !== '' && !isset(ProductCatalog::SPECIES[$species]))) {
      throw new BadRequestHttpException('Los filtros de búsqueda no son válidos.');
    }
    $result = $this->catalog->search($q, $category, $species);
    return [
      '#theme' => 'vet_catalog', '#business' => $this->business->getBusiness(),
      '#products' => $result['products'], '#total' => $result['total'],
      '#categories' => $categories, '#species_options' => ProductCatalog::SPECIES,
      '#filters' => ['q' => $q, 'category' => $category, 'species' => $species],
      '#pager' => ['#type' => 'pager'],
      '#cache' => ['tags' => $result['cache_tags'], 'contexts' => ['url.query_args', 'user.permissions', 'languages:language_interface']],
    ];
  }

}
