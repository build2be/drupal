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

use Drupal\Core\Gettext\Reader;
use Drupal\Core\Gettext\POHeader;

/**
 * Defines PO / gettext related must haves.
 *
 * @see PoReaderInterface
 * @see PoWriterInterface
 */
interface PoInterface {
  function setLangcode($langcode);
  function getLangcode();

  function getHeader();
  function setHeader(POHeader $header);

}
