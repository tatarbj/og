<?php

/**
 * @file
 * Contains \Drupal\example\Routing\RouteSubscriber.
 */

namespace Drupal\og_ui\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Routing\RouteProvider;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\og_ui\OgUi;
use Drupal\og_ui\OgUiAdminRouteInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new RouteSubscriber object.
   *
   * @param EntityTypeManager $entity_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManager $entity_manager) {
    $this->entityTypeManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {

      if ($og_task = $entity_type->getLinkTemplate('og-group-admin-pages')) {
        $entity_type_id = $entity_type->id();
        $route = new Route($og_task);

        $route
          ->addDefaults([
            '_controller' => '\Drupal\og_ui\Controller\OgUiController::ogTasks',
            '_title' => 'Tasks',
          ])
          ->addRequirements([
            '_custom_access' => '\Drupal\og_ui\Access\OgUiRoutingAccess::GroupTabAccess',
          ])
          ->setOption('parameters', [
            $entity_type_id => ['type' => 'entity:' . $entity_type_id],
          ])
          ->setOption('_admin_route', TRUE);

        $collection->add('entity.' . $entity_type_id . '.og_group_admin_pages', $route);
      }
    }

    $this->createRoutesFromAdminRoutesPlugins($collection);
  }

  protected function createRoutesFromAdminRoutesPlugins(RouteCollection $collection) {
    /** @var RouteProvider $route_provider */
    $route_provider = \Drupal::getContainer()->get('router.route_provider');

    $plugins = OgUi::getGroupAdminPlugins();
    foreach ($plugins->getDefinitions() as $definition) {

      /** @var OgUiAdminRouteInterface $plugin */
      $plugin = $plugins->createInstance($definition['id']);

      // Iterate over all the parent routes.
      foreach ($definition['parents_routes'] as $parent_route) {

        if (!$route_provider->getRoutesByNames([$parent_route])) {
          $params = [
            '@router_name' => $parent_route,
            '@plugin_name' => '',
          ];
          \Drupal::logger('og_ui')->alert($this->t('The router @router_name, needed by @plugin_name, does not exists.', $params));
          continue;
        }

        $parent_path = $route_provider->getRouteByName($parent_route)->getPath();
        $path = $parent_path . '/group/' . $definition['path'];

        // Create a route for each route callback.
        foreach ($plugin->getRoutes() as $sub_route => $route_info) {
          $route = new Route($path . '/' . $route_info['sub_path']);
          $route
            ->addDefaults([
              '_controller' => $route_info['controller'],
              '_title' => $route_info['title'],
            ])
            ->addRequirements([
              '_custom_access' => $definition['access'],
              '_plugin_id' => $definition['id'],
            ])
            ->setOption('_admin_route', TRUE);

          $collection->add($parent_route . '.' . $definition['route_id'] . '.' . $sub_route, $route);
        }
      }
    }
  }

}
