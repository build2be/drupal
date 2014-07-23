<?php

/**
 * @file
 * Contains \Drupal\rest\Controller.
 */

namespace Drupal\rest;

use Drupal\Core\Entity\Field\Type\Field;
use Symfony\Component\DependencyInjection\ContainerAware;

class Controller extends ContainerAware {

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
  public function docRoot() {
    $config = \Drupal::config('rest.settings')->get('resources') ?: array();

    $all_bundles = $this->getRestBundles();

    $items = array();
    foreach ($config as $id => $config) {
      $item = array();
      foreach ($config as $method => $settings) {
        $supported_formats = join(', ', $settings['supported_formats']);
        $supported_auth = join(", ", $settings['supported_auth']);
        $item[] = t("Method %method using formats '%supported_formats' with authentication methods '%supported_auth'", array(
          '%method' => $method,
          '%supported_auth' => $supported_auth,
          '%supported_formats' => $supported_formats
        ));
      }

      list(, $entity) = explode(":", $id);
      $bundles = array_keys($all_bundles[$entity]);
      $doc_refs = array();
      foreach ($bundles as $bundle) {
        $doc_refs[] = l($bundle, '/docs/rest/api/types/' . $entity . '/' . $bundle);
      }
      $items[] = array(
        '#theme' => 'item_list',
        '#title' => t("Resource %entity has documentation for !doc-urls", array(
          '%entity' => $entity,
          '!doc-urls' => join(', ', $doc_refs)
        )),
        '#items' => $item,
      );
    }

    $result = array(
      '#theme' => 'item_list',
      '#title' => t('Rest resources'),
      '#items' => $items,
    );

    return $result;
  }

  /**
   * List all fields for given entity type and bundle.
   *
   * @param $entity_type
   * @param $bundle
   * @return array
   */
  public function type($entity_type, $bundle) {
    $required = array();
    $optional = array();

    $fields = $this->getEntityManager()->getFieldDefinitions($entity_type, $bundle);

    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field */
    foreach ($fields as $id => $field) {
      $item = l($field->getName(), "rest/relation/$entity_type/$bundle/$id");

      if ($field->isRequired()) {
        $required[] = $item;
      }
      else {
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
      '#title' => 'Fields for ' . $entity_type . ' / ' . $bundle,
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
  public function relation($entity_type, $bundle, $field_name) {
    // TODO fix for drupal_set_title
    // drupal_set_title($field_name);

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $fields */
    $fields = $this->getEntityManager()->getFieldDefinitions($entity_type, $bundle);

    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
    $field_definition = $fields[$field_name];

    // TODO: SA what information is disclosed?
    //       All settings are exposed
    // TODO: how to check for field permissions?

    $rows = array();
    $rows[] = array("Type", $field_definition->getType());
    $rows[] = array("Description", $field_definition->getDescription());
    foreach ($field_definition->getSettings() as $key => $value) {
      $rows[] = array("Settings - $key", print_r($value, TRUE));
    }

    $result = array(
      '#theme' => 'table',
      '#title' => t("Rest resources for !entity_type / !bundle / !field_name", array(
        '!entity_type' => l($entity_type, ''),
        '!bundle' => l($bundle, '/docs/rest/api/types/' . $entity_type . '/' . $bundle),
        '!field_name' => $field_name,
      )),
      '#rows' => $rows,
    );
    return $result;
  }

  protected function getRestBundles() {
    // TODO use DIC
    $bundles = \Drupal::entityManager()->getAllBundleInfo();

    $config = \Drupal::config('rest.settings')->get('resources');

    // TODO: Change this to only expose info for REST enabled entity types.
    //       aka filter out all ConfigEntities too.

    return $bundles;
  }

  protected function getEntityManager() {
    // TODO use DIC
    return \Drupal::entityManager();
  }

}
