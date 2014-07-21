<?php

/**
 * @file
 * Contains \Drupal\rest\Controller.
 */

namespace Drupal\rest;

use Drupal\Core\Entity\Field\Type\Field;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\Response;

class Controller extends ContainerAware {

  public function relation($field_name, $field_definition) {
    // TODO fix for drupal_set_title
    // drupal_set_title($field_name);

    $render['#theme'] = 'rest_documentation';
    $render['#field_description'] = $field_definition['description'];
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
    // TODO: fix for CR https://www.drupal.org/node/2067859
    //drupal_set_title($entity_type . ': ' . $bundle);

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
      $items = array();
      $items[] = "Type: " . $field->getType();
      if ($field->getDescription()) {
        $items[] = "Description: " . $field->getDescription();
      }
      $items[] = "Data/Storage type: " . $field->getDataType();

      $itemDefinition = $field->getItemDefinition();

      // TODO: setting may contain array as value
      $settings = $itemDefinition->getSettings();
      foreach( $settings as $key => $value) {
        $items[] = "$key: $value";
      }

      $value = array(
        '#theme' => 'item_list',
        '#title' => $field->getName(),
        '#items' => $items,
      );
      if ($field->isRequired()) {
        $required[] = $value;
      } else {
        $optional[] = $value;
      }
    }

    $render = array(
      array(
        '#theme' => 'item_list',
        '#title' => t('Required fields'),
        '#items' => $required,
      ),
      array(
        '#theme' => 'item_list',
        '#title' => t('Optional fields'),
        '#items' => $optional,
      ),
    );
    return $render;
  }

  protected function getEntityManager() {
    return \Drupal::entityManager();
  }

}
