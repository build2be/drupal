<?php
/**
 * @file
 * Contains \Drupal\rest\LinkManager\CollectionLinkManager.
 */

namespace Drupal\rest\LinkManager;

/**
 * Default collection link relation mapper.
 */
class CollectionLinkManager implements CollectionLinkManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function getCollectionItemRelation($collection_id) {
    // By default, use the item IANA Link Relation, which is a generic way to
    // link to items from a collection. See http://tools.ietf.org/html/rfc6573.
    return 'item';
  }

}
