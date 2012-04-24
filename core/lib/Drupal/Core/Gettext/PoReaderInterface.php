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

use Drupal\Core\Gettext\POInterface;

/**
 * Defines a Gettext reader for PO format.
 */
interface PoReaderInterface extends PoInterface {
  function readItem();
}
