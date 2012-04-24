<?php

/**
 * @file
 * Definition of Drupal\Core\Gettext\PoWriter.
 */

namespace Drupal\Core\Gettext;

use Drupal\Core\Gettext\POHeader;
use Drupal\Core\Gettext\BatchStateInterface;

/**
 * Defines a Gettext writer.
 */
class PoFileWriter implements PoStreamInterface, PoWriterInterface, BatchStateInterface {

  private $_uri;
  private $_header;
  private $_fd;
  private $_seekpos;
  private $_open = FALSE;

  /**
   * @see BatchStateInterface
   */
  function __construct() {
    // empty
  }

  public function getHeader() {
    return $this->_header;
  }

  public function setHeader(POHeader $header) {
    $this->_header = $header;
  }

  public function getLangcode() {
    return $this->_langcode;
  }

  public function setLangcode($langcode) {
    $this->_langcode = $langcode;
  }

  public function open() {
    // Open in append mode
    $this->_fd = fopen($this->getURI(), 'a');
    $this->_seekpos = ftell($this->_fd);
    if ($this->_seekpos == 0) {
      // If file is new position == 0
      $this->writeHeader();
    }
    else {
      $reader = new PoFileReader($this->uri);
      $this->_header = $reader->getHeader();
    }
  }

  public function close() {
    fclose($this->_fd);
  }

  public function setState(array $state) {
    $this->_uri = $state['uri'];
    $this->open();
  }

  public function getState() {
    return array(
      'uri' => $this->_uri,
      'seekpos' => ftell($this->_fd),
    );
  }

  private function write($data) {
    $result = fputs($this->_fd, $data);
    if ($result === FALSE) {
      // TODO: better context for message
      throw new \Exception("Unable to write data : " . substr($data, 0, 20));
    }
    $this->_seekpos = ftell($this->_fd);
  }

  private function writeHeader() {
    $this->write($this->_header);
  }

  public function writeItem(POItem $item) {
    $this->write($item);
  }

  public function writeItems(PoReaderInterface $reader, $count = 10) {
    $forever = $count == -1;
    while (($count-- > 0 || $forever) && ($item = $reader->readItem())) {
      $this->writeItem($item);
    }
  }

  public function getURI() {
    if (empty($this->_uri)) {
      throw new \Exception("Empty URI");
    }
    return $this->_uri;
  }

  public function setURI($uri) {
    $this->_uri = $uri;
  }

}
