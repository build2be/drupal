<?php
/**
 * @file
 * Contains \Drupal\rest\LinkManager\LinkManager.
 */

namespace Drupal\rest\LinkManager;

class LinkManager implements LinkManagerInterface {

  /**
   * The type link manager.
   *
   * @var \Drupal\rest\LinkManager\TypeLinkManagerInterface
   */
  protected $typeLinkManager;

  /**
   * The relation link manager.
   *
   * @var \Drupal\rest\LinkManager\RelationLinkManagerInterface
   */
  protected $relationLinkManager;

  /**
   * The collection link manager.
   *
   * @var \Drupal\rest\LinkManager\CollectionLinkManagerInterface
   */
  protected $collectionLinkManager;

  /**
   * Constructor.
   *
   * @param \Drupal\rest\LinkManager\TypeLinkManagerInterface $type_link_manager
   *   Manager for handling type links corresponding to bundles.
   * @param \Drupal\rest\LinkManager\RelationLinkManagerInterface $relation_link_manager
   *   Manager for handling link relations corresponding to fields.
   * @param \Drupal\rest\LinkManager\CollectionLinkManagerInterface $collection_link_manager
   *   Manager for handling collection links.
   */
  public function __construct(TypeLinkManagerInterface $type_link_manager, RelationLinkManagerInterface $relation_link_manager, CollectionLinkManagerInterface $collection_link_manager) {
    $this->typeLinkManager = $type_link_manager;
    $this->relationLinkManager = $relation_link_manager;
    $this->collectionLinkManager = $collection_link_manager;
  }

  /**
   * Implements \Drupal\rest\LinkManager\TypeLinkManagerInterface::getTypeUri().
   */
  public function getTypeUri($entity_type, $bundle, $context = array()) {
    return $this->typeLinkManager->getTypeUri($entity_type, $bundle, $context);
  }

  /**
   * Implements \Drupal\rest\LinkManager\TypeLinkManagerInterface::getTypeInternalIds().
   */
  public function getTypeInternalIds($type_uri, $context = array()) {
    return $this->typeLinkManager->getTypeInternalIds($type_uri, $context);
  }

  /**
   * Implements \Drupal\rest\LinkManager\RelationLinkManagerInterface::getRelationUri().
   */
  public function getRelationUri($entity_type, $bundle, $field_name, $context = array()) {
    return $this->relationLinkManager->getRelationUri($entity_type, $bundle, $field_name, $context);
  }

  /**
   * Implements \Drupal\rest\LinkManager\RelationLinkManagerInterface::getRelationInternalIds().
   */
  public function getRelationInternalIds($relation_uri) {
    return $this->relationLinkManager->getRelationInternalIds($relation_uri);
  }

  /**
   * {@inheritdoc}
   */
  public function setLinkDomain($domain) {
    $this->relationLinkManager->setLinkDomain($domain);
    $this->typeLinkManager->setLinkDomain($domain);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionItemRelation($collection_id) {
    return $this->collectionLinkManager->getCollectionItemRelation($collection_id);
  }

}

