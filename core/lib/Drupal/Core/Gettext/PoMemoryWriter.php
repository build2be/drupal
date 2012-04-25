<?php

/**
 * @file
 * Definition of Drupal\Core\Gettext\PoWriter.
 */

namespace Drupal\Core\Gettext;

use Drupal\Core\Gettext\POHeader;
use Drupal\Core\Gettext\BatchStateInterface;

/**
 * Defines a Gettext memory writer.
 *
 * This writer is used by the installer.
 *
 * TODO: do we need a BatchStateInterface?
 */
class POMemoryWriter implements PoWriterInterface, BatchStateInterface {

  private $_header;
  private $_items;

  function __construct() {
    $this->_items = array();
  }

  public function setState(array $state) {
    // nothing to do?
  }

  public function getState() {
    return array();
  }

  /**
   * Stores values into memory.
   *
   * The structure is context dependent.
   * TODO: where is this structure documented?
   * - array[context][source] = translation
   *
   * @param POItem $item
   */
  public function writeItem(POItem $item) {
    if (is_array($item->source)) {
      $item->source = implode(LOCALE_PLURAL_DELIMITER, $item->source);
      $item->translation = implode(LOCALE_PLURAL_DELIMITER, $item->translation);
    }
    $this->_items[isset($item->context) ? $item->context : ''][$item->source] = $item->translation;
  }

  public function writeItems(PoReaderInterface $reader, $count = 10) {
    $forever = $count == -1;
    while (($count-- > 0 || $forever) && ($item = $reader->readItem())) {
      $this->writeItem($item);
    }
  }

  public function getHeader() {
    // TODO: what
  }

  public function getLangcode() {
    // TODO: what
  }

  public function setHeader(POHeader $header) {
    // TODO: what
  }

  public function setLangcode($langcode) {
    // TODO: what
  }

  public function getData() {
    return $this->_items;
  }
}
