<?php

declare(strict_types=1);

namespace Drupal\vet_api\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\image\Entity\ImageStyle;

/** Selección de fotos publicadas, sin incrustar historias en las plantillas. */
final class ClinicGallery {

  public function __construct(private readonly EntityTypeManagerInterface $entities, private readonly FileUrlGeneratorInterface $fileUrls) {}

  public function get(): array {
    $result = ['portada' => NULL, 'nosotros' => NULL, 'logo' => NULL, 'stories' => [], 'cache_tags' => ['node_list:historia_balto']];
    // Compatible con el arranque anterior a ejecutar la semilla de fotografías.
    if (!$this->entities->getStorage('node_type')->load('historia_balto')) {
      return $result;
    }
    $storage = $this->entities->getStorage('node');
    $ids = $storage->getQuery()->condition('type', 'historia_balto')->condition('status', 1)->accessCheck(TRUE)->sort('field_clinic_order')->sort('nid')->range(0, 20)->execute();
    foreach ($storage->loadMultiple($ids) as $node) {
      $item = $node->get('field_clinic_photo')->first();
      if (!$node->access('view') || !$item?->entity) {
        continue;
      }
      $uri = $item->entity->getFileUri();
      $role = $node->get('field_clinic_role')->value;
      $style = ImageStyle::load($role === 'logo' ? 'balto_brand' : ($role === 'historia' ? 'balto_photo_card' : 'balto_photo_wide'));
      $row = [
        'title' => $node->label(), 'caption' => $node->get('field_clinic_caption')->value,
        'image_url' => $style ? $style->buildUrl($uri) : $this->fileUrls->generateString($uri),
        'original_url' => $this->fileUrls->generateString($uri), 'alt' => $item->alt,
        'width' => $item->width, 'height' => $item->height,
      ];
      $result['cache_tags'] = array_merge($result['cache_tags'], $node->getCacheTags(), $item->entity->getCacheTags(), $style?->getCacheTags() ?? []);
      if ($role === 'historia') {
        $result['stories'][] = $row;
      }
      elseif (array_key_exists($role, $result) && $result[$role] === NULL) {
        $result[$role] = $row;
      }
    }
    return $result;
  }

}
