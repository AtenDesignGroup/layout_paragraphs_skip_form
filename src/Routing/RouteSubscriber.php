<?php

namespace Drupal\layout_paragraphs_skip_form\Routing;

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
    if ($route = $collection->get('layout_paragraphs.builder.insert')) {
      $route->setDefault('_controller', '\Drupal\layout_paragraphs_skip_form\Controller\InsertComponentController::skipInsertForm');
    }
  }

}
