<?php

/**
 * @file
 * Contains \Drupal\rest\EventSubscriber\RouteSubscriber.
 */

namespace Drupal\rest\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityType;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\rest\Plugin\Type\ResourcePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;


/**
 * Subscriber for REST-style routes.
 */
class ResourceRoutes extends RouteSubscriberBase {

  /**
   * The plugin manager for REST plugins.
   *
   * @var \Drupal\rest\Plugin\Type\ResourcePluginManager
   */
  protected $manager;

  /**
   * The Drupal configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Constructs a RouteSubscriber object.
   *
   * @param \Drupal\rest\Plugin\Type\ResourcePluginManager $manager
   *   The resource plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The configuration factory holding resource settings.
   */
  public function __construct(ResourcePluginManager $manager, ConfigFactoryInterface $config) {
    $this->manager = $manager;
    $this->config = $config;
  }

  /**
   * Alters existing routes for a specific collection.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection for adding routes.
   * @return array
   */
  protected function alterRoutes(RouteCollection $collection) {
    $routes = array();
    $enabled_resources = $this->config->get('rest.settings')->get('resources') ?: array();

    // Iterate over all enabled resource plugins.
    foreach ($enabled_resources as $id => $enabled_methods) {
      $plugin = $this->manager->getInstance(array('id' => $id));

      foreach ($plugin->routes() as $name => $route) {
        $method = $route->getRequirement('_method');
        // Only expose routes where the method is enabled in the configuration.
        if ($method && isset($enabled_methods[$method])) {
          $route->setRequirement('_access_rest_csrf', 'TRUE');

          // Check that authentication providers are defined.
          if (empty($enabled_methods[$method]['supported_auth']) || !is_array($enabled_methods[$method]['supported_auth'])) {
            watchdog('rest', 'At least one authentication provider must be defined for resource @id', array(':id' => $id), WATCHDOG_ERROR);
            continue;
          }

          // Check that formats are defined.
          if (empty($enabled_methods[$method]['supported_formats']) || !is_array($enabled_methods[$method]['supported_formats'])) {
            watchdog('rest', 'At least one format must be defined for resource @id', array(':id' => $id), WATCHDOG_ERROR);
            continue;
          }

          // If the route has a format requirement, then verify that the
          // resource has it.
          $format_requirement = $route->getRequirement('_format');
          if ($format_requirement && !in_array($format_requirement, $enabled_methods[$method]['supported_formats'])) {
            continue;
          }

          // The configuration seems legit at this point, so we set the
          // authentication provider and add the route.
          $route->setOption('_auth', $enabled_methods[$method]['supported_auth']);
          $routes["rest.$name"] = $route;
          $collection->add("rest.$name", $route);
        }
      }
    }
  }

  /**
   * Generate relational routes.
   *
   * TODO: why do we generate routes for relations?
   *
   * @param RouteBuildEvent $event
   */
  public function relationRoutes(RouteBuildEvent $event) {
    $collection = $event->getRouteCollection();

    // TODO: why filter out the non relation ones? Ie field_image is exposed through _links to ie HAL browser.
    $link_field_types = array(
      'entity_reference',
      'taxonomy_term_reference',
    );
    $fieldMap = \Drupal::entityManager()->getFieldMap();

    foreach ($fieldMap as $entity_type => $fields) {
      foreach ($fields as $field_name => $field) {
        // FIXME
        //if (in_array($field['type'], $link_field_types)) {
          foreach ($field['bundles'] as $bundle) {
            // TODO why use relation in link. This should be in */api/* path
            $route = new Route("/rest/relation/$entity_type/$bundle/$field_name", array(
              '_content' => 'Drupal\rest\Controller::relation',
              'entity_type' => $entity_type,
              'bundle' => $bundle,
              'field_name' => $field_name,
            ), array(
              '_method' => 'GET',
              '_access' => 'TRUE',
            ));
            $collection->add("rest.relation.$entity_type.$bundle.$field_name", $route);
          }
        //}
      }
    }
  }

  /**
   * Add all supported EntityTypes to the routes.
   *
   * @param RouteBuildEvent $event
   */
  public function typeRoutes(RouteBuildEvent $event) {
    $collection = $event->getRouteCollection();

    foreach ($this->getRestBundles() as $entity_type => $bundles) {
      foreach ($bundles as $bundle_name => $bundle) {
        $route = new Route("/docs/rest/api/types/$entity_type/$bundle_name", array(
          '_content' => 'Drupal\rest\Controller::type',
          'entity_type' => $entity_type,
          'bundle' => $bundle_name,
        ), array(
          '_method' => 'GET',
          '_access' => 'TRUE',
        ));
        $collection->add("rest.type.$entity_type.$bundle_name", $route);
      }
    }
  }

  protected function getRestBundles() {
    $bundles = \Drupal::entityManager()->getAllBundleInfo();

    // TODO: Change this to only expose info for REST enabled entity types.
    // TODO: filter out all ConfigEntities

    return $bundles;
  }

  /**
   * Implements EventSubscriberInterface::getSubscribedEvents().
   */
  static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();

    $events[RoutingEvents::DYNAMIC][] = array('relationRoutes');
    $events[RoutingEvents::DYNAMIC][] = array('typeRoutes');
    return $events;
  }

}
