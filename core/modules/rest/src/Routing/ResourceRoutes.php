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
   * @param RouteBuildEvent $event
   */
  public function relationRoutes(RouteBuildEvent $event) {
    $collection = $event->getRouteCollection();

    $link_field_types = array(
      'entity_reference',
      'taxonomy_term_reference',
    );

    foreach (entity_get_bundles() as $entity_type => $bundles) {
      // TODO fix relationRoutes
      //      drush cache-rebuild generated

      $skip = array(
        'comment',                // Missing bundle for entity type comment
        //'block',
        'contact_message',        // Missing bundle for entity type contact_message
        'breakpoint',             // Attempt to create an unnamed breakpoint.
        'breakpoint_group',       // Attempt to create an unnamed breakpoint group.
        'editor',                 // The "" plugin does not exist.
        'entity_form_display',    // Missing required properties for an EntityDisplay entity.'
        'entity_view_display',    // Missing required properties for an EntityDisplay entity.'
        'field_config',           // Attempt to create an unnamed field.'
        'field_instance_config',  // Attempt to create an instance of a field without a field_name.'
        'taxonomy_term',          // Missing bundle for entity type taxonomy_term
      );
      foreach ($bundles as $bundle_name => $bundle) {
        if (in_array($entity_type, $skip)) {
          continue;
        }
        /**
         * @var $entity \Drupal\Core\Entity\EntityInterface
         */
        $entity = entity_create($entity_type, array('type' => $bundle_name));
        if ($entity instanceof ContentEntityBase) {
          /**
           * @var $fields \Drupal\Core\Field\FieldDefinitionInterface[]
           */
          $fields = $entity->getFieldDefinitions();
          /**
           * @var $field_definition \Drupal\Core\Field\FieldDefinitionInterface
           */
          foreach ($fields as $field_name => $field_definition) {
            $field_type = $field_definition->getType();
            if (in_array($field_type, $link_field_types)) {
              // echo "$entity_type:$bundle_name:$field_name is a : " . $field_type . PHP_EOL;
              $route = new Route("/api/rest/relations/$entity_type/$bundle_name/$field_name", array(
                '_content' => 'Drupal\rest\Controller::relation',
                'field_name' => $field_name,
                'field_definition' => $field_definition,
              ), array(
                '_method' => 'GET',
                '_access' => 'TRUE',
              ));
              $collection->add("rest.relation.$entity_type.$bundle_name.$field_name", $route);
            }
          }
        }
      }
    }
  }

  /**
   * Add all available EntityTypes to the routes.
   *
   * @param RouteBuildEvent $event
   */
  public function typeRoutes(RouteBuildEvent $event) {
    $collection = $event->getRouteCollection();

    // @todo Change this to only expose info for REST enabled entity types.
    foreach ($this->getRestEntities() as $entity_type => $bundles) {
      foreach ($bundles as $bundle_name => $bundle) {
        $route = new Route("/api/rest/types/$entity_type/$bundle_name", array(
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
