<?php

declare(strict_types=1);

namespace Drupal\vet_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\vet_api\EventSubscriber\ApiSubscriber;
use Drupal\vet_api\Service\BusinessData;
use Drupal\vet_api\Service\ProductCatalog;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Proyecciones explícitas de datos públicos; nunca serializa entidades enteras. */
final class ApiController extends ControllerBase {

  public function __construct(private readonly ProductCatalog $catalog, private readonly BusinessData $business) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('vet_api.catalog'), $container->get('vet_api.business'));
  }

  private function parameter(Request $request, string $name, int $max = 100): string {
    $value = $request->query->get($name, '');
    if (!is_string($value) || mb_strlen($value) > $max) {
      throw new BadRequestHttpException();
    }
    return trim($value);
  }

  public function products(Request $request): JsonResponse {
    $q = $this->parameter($request, 'q');
    $category = $this->parameter($request, 'category');
    $species = $this->parameter($request, 'species');
    $page = $this->parameter($request, 'page', 6);
    if (($category !== '' && !isset($this->catalog->categories()[$category])) || ($species !== '' && !isset(ProductCatalog::SPECIES[$species])) || ($page !== '' && !preg_match('/^[0-9]{1,6}$/D', $page))) {
      throw new BadRequestHttpException();
    }
    $page = (int) $page;
    $storage = $this->entityTypeManager()->getStorage('commerce_product_variation');
    $query = $storage->getQuery()->condition('type', 'vet_variation')->condition('status', 1)
      ->condition('product_id.entity.status', 1)->accessCheck(TRUE)->sort('variation_id');
    if ($q !== '') {
      // El chat omite conectores como «de» al extraer el nombre del producto.
      $title = $query->andConditionGroup();
      foreach (preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) as $word) {
        $title->condition('product_id.entity.title', $word, 'CONTAINS');
      }
      $query->condition($query->orConditionGroup()->condition('sku', $q, 'CONTAINS')->condition($title));
    }
    if ($category !== '') {
      $query->condition('product_id.entity.field_category.target_id', $category);
    }
    if ($species !== '') {
      $query->condition('product_id.entity.field_species.value', $species);
    }
    $total = (int) (clone $query)->count()->execute();
    $ids = $query->range($page * 50, 50)->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $variation) {
      $product = $variation->getProduct();
      if ($product && $product->bundle() === 'vet_product' && $product->access('view')) {
        $rows[] = $this->catalog->present($product, $variation);
      }
    }
    return ApiSubscriber::response(['data' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'page_size' => 50, 'catalog_is_example' => (bool) ($this->business->getBusiness()['catalog_is_example'] ?? TRUE)]]);
  }

  public function product(string $sku): JsonResponse {
    if (mb_strlen($sku) > 100) {
      throw new BadRequestHttpException();
    }
    $variations = $this->entityTypeManager()->getStorage('commerce_product_variation')->loadByProperties(['sku' => $sku, 'type' => 'vet_variation', 'status' => 1]);
    $variation = reset($variations);
    $product = $variation ? $variation->getProduct() : NULL;
    if (!$product || $product->bundle() !== 'vet_product' || !$product->isPublished() || !$product->access('view')) {
      throw new NotFoundHttpException();
    }
    return ApiSubscriber::response(['data' => $this->catalog->present($product, $variation)]);
  }

  public function categories(): JsonResponse {
    $rows = [];
    foreach ($this->catalog->categories() as $id => $name) {
      $rows[] = ['id' => (string) $id, 'name' => $name];
    }
    return ApiSubscriber::response(['data' => $rows]);
  }

  public function services(): JsonResponse {
    return ApiSubscriber::response(['data' => $this->business->getServices()]);
  }

  public function schedule(): JsonResponse {
    $data = $this->business->getBusiness();
    return ApiSubscriber::response(['data' => array_intersect_key($data, array_flip([
      'name', 'city', 'addresses', 'whatsapp', 'whatsapp_url', 'appointment_url', 'phone_url',
      'facebook_name', 'facebook_url', 'email', 'hospitalization', 'consultation_schedule',
      'payment_methods', 'yape_number', 'service_pricing_note', 'schedule_is_example', 'map_url', 'maps_link',
    ]))]);
  }

}
