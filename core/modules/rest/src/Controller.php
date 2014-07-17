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
    // TODO : make this a proper content response somehow.
    $response = new Response();
    $response->setContent(drupal_render($render));
    return $response;
  }

  public function type($entity_type, $bundle) {
    // TODO: fix for CR https://www.drupal.org/node/2067859
    //drupal_set_title($entity_type . ': ' . $bundle);

    $required = array();
    $optional = array();

    $entity = entity_create($entity_type, array('type' => $bundle));
    foreach ($entity->getProperties() as $field) {
      $definition = $field->getItemDefinition();
      if (isset($definition['required'])) {
        $required[] = $field->getName();
      }
      else {
        $optional[] = $field->getName();
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

    // TODO : make this a proper content response somehow.
    $response = new Response();
    $response->setContent(drupal_render($render));
    return $response;
  }
}
