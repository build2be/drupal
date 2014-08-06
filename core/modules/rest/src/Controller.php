<?php

/**
 * @file
 * Contains \Drupal\rest\Controller.
 */

namespace Drupal\rest;

use Drupal\Core\Entity\Field\Type\Field;
use Drupal\Core\Routing\RouteMatch;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\Routing\Route;

class Controller extends ContainerAware
{

  // TODO use DiC
  //      see getRestBundles()
  //      see getEntityManager()

  /**
   * Renders the REST documentation.
   *
   * This is the start point for exploring the exposed rest entities.
   *
   * Only exposed entities are listed.
   *
   * @return array
   */
  public function docRoot()
  {
    $config = \Drupal::config('rest.settings')->get('resources') ?: array();

    $items = array();
    foreach ($config as $id => $config) {
      list(, $entity) = explode(":", $id);
      $items[] = l($entity, '/docs/rest/api/' . $entity);
    }

    $result = array(
      '#theme' => 'item_list',
      '#title' => t('Rest resources'),
      '#items' => $items,
    );

    return $result;
  }

  /**
   * Document page for the given entity type.
   *
   * The request methods and bundles are listen.
   *
   * @param string $entity_type
   * @return array
   */
  public function docEntity($entity_type)
  {
    // TODO: validate access permission
    $config = \Drupal::config('rest.settings')->get('resources') ?: array();

    $all_bundles = $this->getRestBundles();

    // TODO: make sure exists and add proper response
    $config = $config["entity:" . $entity_type];

    // TODO: validate rest method permissions
    $methods = array();
    foreach ($config as $method => $settings) {
      $supported_formats = join(', ', $settings['supported_formats']);
      $supported_auth = join(", ", $settings['supported_auth']);
      $methods[] = t("Method %method using formats '%supported_formats' with authentication methods '%supported_auth'", array(
        '%method' => $method,
        '%supported_auth' => $supported_auth,
        '%supported_formats' => $supported_formats
      ));
    }
    $m = array(
      '#theme' => 'item_list',
      '#title' => t("Available methods"),
      '#items' => $methods,
    );

    $doc_refs = array();
    foreach (array_keys($all_bundles[$entity_type]) as $bundle) {
      // TODO fixed path to align in /docs/rest/api/$entity_type/$bundle/$field
      $doc_refs[] = l($bundle, '/docs/rest/api/types/' . $entity_type . '/' . $bundle);
    }

    $bundles = array(
      '#theme' => 'item_list',
      '#title' => t("Resource bundles are:"),
      '#items' => $doc_refs,
    );

    $help = $this->getModuleHelp($entity_type);

    $result = array(
      '#theme' => 'item_list',
      '#title' => t('Rest resources for type %entity.', array('%entity' => $entity_type)),
      '#items' => array(
        array('#markup' => $help),
        $bundles,
        $m,
      ),
    );

    return $result;
  }

  private function getModuleHelp($module)
  {
    // Drupal help is a hack
    $routeMatch = new RouteMatch('blaat', new Route('blaat'));
    return \Drupal::moduleHandler()->invoke($module, 'help', array('help.page.' . $module, $routeMatch));
  }

  /**
   * List all fields for given entity_type type and bundle.
   *
   * @param $entity_type
   * @param $bundle
   * @return array
   */
  public function docBundle($entity_type, $bundle)
  {
    $required = array();
    $optional = array();

    $fields = $this->getEntityManager()->getFieldDefinitions($entity_type, $bundle);

    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field */
    foreach ($fields as $id => $field) {
      // TODO: fix the HAL/serializer path to match to /docs/rest/api/$entity_type/$bundle/$field
      $line = '<dt>' . l($field->getName(), "rest/relation/$entity_type/$bundle/$id") . '</dt>';
      $line .= '<dd>' . $field->getDescription() . '</dd>';
      $item = array(
        '#markup' => $line,
      );

      if ($field->isRequired()) {
        $required[] = $item;
      } else {
        $optional[] = $item;
      }
    }

    $requiredItems = array(
      '#theme' => 'item_list',
      '#title' => "Required fields",
      '#items' => $required,
    );
    $optionalItems = array(
      '#theme' => 'item_list',
      '#title' => "Optional fields",
      '#items' => $optional,
    );

    $render = array(
      '#theme' => 'item_list',
      '#title' => 'Fields for ' . l($entity_type, '/docs/rest/api/' . $entity_type) . ' / ' . $bundle,
      '#items' => array($requiredItems, $optionalItems),
    );
    return $render;
  }

  /**
   * Generate field page.
   *
   * @param $entity_type
   * @param $bundle
   * @param $field_name
   * @return mixed
   */
  public function docField($entity_type, $bundle, $field)
  {
    // TODO fix for drupal_set_title
    // drupal_set_title($field_name);

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $fields */
    $fields = $this->getEntityManager()->getFieldDefinitions($entity_type, $bundle);

    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
    $field_definition = $fields[$field];

    // TODO: SA what information is disclosed?
    //       All settings are exposed
    // TODO: how to check for field permissions?

    $rows = array();
    $rows[] = array("Name", $field_definition->getName());
    $rows[] = array("Description", $field_definition->getDescription());
    $rows[] = array("Type", $field_definition->getType());
    $rows[] = array("Bundle", $field_definition->getBundle());
    $settings = $this->arrayToTree($field_definition->getSettings());
    $rows[] = array("Settings", $this->arrayToTree($field_definition->getSettings()));
    $rows[] = array("Settings - print_r", print_r($field_definition->getSettings(), TRUE));

    $result = array(
      '#theme' => 'table',
      '#title' => t("Rest resources for !entity_type / !bundle / !field_name", array(
        '!entity_type' => l($entity_type, '/docs/rest/api/' . $entity_type),
        '!bundle' => l($bundle, '/docs/rest/api/types/' . $entity_type . '/' . $bundle),
        '!field_name' => $field,
      )),
      '#rows' => $rows,
    );
    return $result;
  }

  /**
   * @param $data
   * @param int $depth
   * @return string
   */
  private function arrayToTree($data, $depth = 0) {
    $result = '';
    if (is_array($data)) {
      foreach($data as $key => $value) {
        $result .= str_repeat('&nbsp;', $depth) . "$key: " . $this->arrayToTree($value, $depth++);
      }
    }
    else {
      $result = $data . '<br/>';
    }
    return $result;
  }

  protected function getRestBundles()
  {
    // TODO use DIC
    $bundles = \Drupal::entityManager()->getAllBundleInfo();

    $config = \Drupal::config('rest.settings')->get('resources');

    // TODO: Change this to only expose info for REST enabled entity_type types.
    //       aka filter out all ConfigEntities too.

    return $bundles;
  }

  protected function getEntityManager()
  {
    // TODO use DIC
    return \Drupal::entityManager();
  }

}
