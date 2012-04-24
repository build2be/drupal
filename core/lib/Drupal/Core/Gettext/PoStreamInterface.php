<?php

/**
 * @file
 * Definition of Drupal\Core\Gettext\PoReader.
 *
 * TODOs
 * - constructor needs a state
 * - add getState
 * - gettextInterface should have a readLine method
 */

namespace Drupal\Core\Gettext;

/**
 * Defines PO / gettext related must haves.
 *
 * @see PoReaderInterface
 * @see PoWriterInterface
 */
interface PoStreamInterface {
  function open();
  function close();

  function getURI();
  function setURI($uri);
}
