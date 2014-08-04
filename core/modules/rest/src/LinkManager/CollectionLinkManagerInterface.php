<?php
/**
 * @file
 * Contains \Drupal\rest\LinkManager\CollectionLinkManagerInterface.
 */


namespace Drupal\rest\LinkManager;

/**
 * Interface for mapping collection (e.g. Views) link relations.
 */
interface CollectionLinkManagerInterface {

  /**
   * Get link relating collection to item.
   *
   * @param string $collection_id
   *   The identifier of a collection (e.g. View name).
   *
   * @return string
   *   The link relation.
   */
  public function getCollectionItemRelation($collection_id);
}
