<?php

/**
 * @file
 * Contains \Drupal\rest\Controller.
 */

namespace Drupal\rest;

use Drupal\Core\Entity\Field\Type\Field;
use Symfony\Component\DependencyInjection\ContainerAware;

class Controller extends ContainerAware {

  public function apidocs(){
    $endpoints = array();
    foreach ($this->getRestBundles() as $entity_type => $bundles) {
      foreach ($bundles as $bundle_name => $bundle) {
        $endpoints[] = array(
          'name' => $entity_type . ' => ' . $bundle_name,
          'href' => '/docs/rest/api/types/'. $entity_type . '/' . $bundle_name,
        );
      }
    }

    $render['#theme'] = 'rest_documentation_endpoints';
    $render['#title'] = 'API Endpoints for REST';
    $render['#endpoints'] = $endpoints;
    return $render;
  }

  public function relation($entity_type, $bundle, $field_name) {
    // TODO fix for drupal_set_title
    // drupal_set_title($field_name);

    $fields = $this->getEntityManager()->getFieldDefinitions($entity_type, $bundle);
    $field_definition = $fields[$field_name];
    $render['#theme'] = 'rest_documentation';
    $render['#field_description'] = $field_definition->getDescription();
    $render['#methods']['get'] = array(
      '#theme' => 'rest_documentation_section',
      '#method' => 'GET',
      '#headers' => array(
        '#theme' => 'item_list',
        '#title' => t('HTTP Headers'),
        '#items' => array(
          'Link: &lt;http://drupal.org/rest&gt;; rel="profile"'
        ),
      ),
      // @todo Add required and optional fields here.
      '#body' => array(),
    );
    return $render;
  }

  public function type($entity_type, $bundle) {
    $required = array();
    $optional = array();

    $fields = $this->getEntityManager()->getFieldDefinitions($entity_type, $bundle);

    // TODO: how to present the information?
    //       Let's make a table per field
    // TODO: SA what information is disclosed?
    //       All settings are exposed
    // TODO: how to check for field permissions?
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field */
    foreach ($fields as $id => $field) {
      $item = array();
      $item['name'] = $field->getName();
      $item['type'] = $field->getType();
      if ($field->getDescription()) {
        $item['description'] = $field->getDescription();
      }
      $item['datatype'] = $field->getDataType();

      $itemDefinition = $field->getItemDefinition();

      // TODO: setting may contain array as value
      $settings = $itemDefinition->getSettings();
      foreach( $settings as $key => $value) {
        $item['extra'][] = array($key, $value);
      }

      if ($field->isRequired()) {
        $required[] = $item;
      } else {
        $optional[] = $item;
      }
    }

    $render = array(
      '#theme' => 'rest_documentation_type',
      '#title' => 'Fields for ' . $entity_type . '/' . $bundle,
      '#required' => $required,
      '#optional' => $optional,
    );
    return $render;
  }

  protected function getRestBundles() {
    $bundles = \Drupal::entityManager()->getAllBundleInfo();

    // TODO: Change this to only expose info for REST enabled entity types.
    // TODO: filter out all ConfigEntities

    return $bundles;
  }

  protected function getEntityManager() {
    return \Drupal::entityManager();
  }

}
