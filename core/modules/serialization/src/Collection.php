<?php
/**
 * @file
 * Contains \Drupal\serialization\Collection
 */

namespace Drupal\serialization;

/**
 * Provides a wrapper for a collection of entities, e.g. a feed channel.
 */
class Collection implements \IteratorAggregate {

  /**
   * An internal identifier for this collection (e.g. view name).
   *
   * @var string
   */
  protected $id;

  /**
   * The entities in the collection.
   *
   * var array
   */
  protected $items;

  /**
   * The title of the collection.
   *
   * @var string
   */
  protected $title;

  /**
   * The URI of the collection.
   *
   * @var string
   */
  protected $uri;

  /**
   * The description of the collection.
   *
   * @var string
   */
  protected $description;

  /**
   * Hypermedia links (prev, next, first, last for paging collections)
   *
   * @var array
   */
  protected $links = array();

  /**
   * Constructor.
   *
   * @param string $collection_id
   *   The internal identifier for the collection (e.g. view name).
   */
  public function __construct($collection_id) {
    $this->id = $collection_id;
  }

  /**
   * Get the internal ID of the collection.
   */
  public function getCollectionId() {
    return $this->id;
  }

  /**
   * Get the items in the collection.
   *
   * @return array
   *   The items of the collection.
   */
  public function getItems() {
    return $this->items;
  }

  /**
   * Set the items list.
   *
   * @param array $items
   *   The items of the collection.
   */
  public function setItems($items) {
    $this->items = $items;
  }

  /**
   * Get the collection title.
   *
   * @return string
   *   The title of the collection.
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * Set the collection title.
   *
   * @param string $title
   *   The items of the collection.
   */
  public function setTitle($title) {
    $this->title = $title;
  }

  /**
   * Get the collection URI.
   *
   * @return string
   *   The URI ("self") of the collection.
   */
  public function getUri() {
    return $this->uri;
  }

  /**
   * Set the collection URI.
   *
   * @param string $uri
   *   The URI ("self") of the collection.
   */
  public function setUri($uri) {
    $this->uri = $uri;
  }

  /**
   * Get the collection description.
   *
   * @return string
   *   The description of the collection.
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * Set the collection URI.
   *
   * @param string $description
   *   The description of the collection.
   */
  public function setDescription($description) {
    $this->description = $description;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    return new \ArrayIterator($this->getItems());
  }

  /**
   * Sets a link URI for a given type.
   *
   * @param string $type
   *   The link relation type.
   * @param string $uri
   *   The link relation URI.
   */
  public function setLink($type, $uri) {
    $this->links[$type] = $uri;
  }

  /**
   * Gets a link URI for a given type.
   *
   * @param string $type
   *   The link relation type.
   *
   * @return NULL|String
   *   The URI of the link relation type or NULL.
   */
  public function getLink($type) {
    return isset($this->links[$type]) ? $this->links[$type] : NULL;
  }

  /**
   * Sets all link URIs.
   *
   * @param array $links
   *   Associative array of link relation types and URIs.
   */
  public function setLinks($links) {
    $this->links = $links;
  }

  /**
   * Gets all link URIs.
   *
   * @return array
   *   Associative array of link relation types and URIs.
   */
  public function getLinks() {
    return $this->links;
  }

  /**
   * Returns true if (hypermedia) link relations have been added.
   *
   * @return bool
   *   True if link relations have been added.
   */
  public function hasLinks() {
    return is_array($this->links) && count($this->links);
  }
}
