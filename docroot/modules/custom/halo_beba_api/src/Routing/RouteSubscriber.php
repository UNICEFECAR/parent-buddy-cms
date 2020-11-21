<?php

namespace Drupal\halo_beba_api\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('media.settings')) {
      $route->setRequirement('_permission', 'administer site configuration');
    }
    if ($route = $collection->get('media_library.settings')) {
      $route->setRequirement('_permission', 'administer site configuration');
    }
    if ($route = $collection->get('lightning_media.bulk_upload')) {
      $route->setRequirement('_permission', 'dropzone upload files');
    }
  }
}
