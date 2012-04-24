<?php

/**
 * @file
 * Definition of Drupal\Core\Gettext\PoWriter.
 */

namespace Drupal\Core\Gettext;

use Drupal\Core\Gettext\POInterface;
use Drupal\Core\Gettext\POItem;

/**
 * Defines a Gettext writer.
 */
interface PoWriterInterface extends POInterface {
  function writeItem(POItem $item);
  function writeItems(PoReaderInterface $reader, $count = 10);
}
