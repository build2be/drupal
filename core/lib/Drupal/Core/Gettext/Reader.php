<?php

// TODO: this file is kept for it's rich definition
// These definitely need to be moved to PoFileReader mostly

/**
 * @file
 * Definition of Drupal\Core\Gettext\Reader.
 */

namespace Drupal\Core\Gettext;

use Drupal\Core\Gettext\GettextInterface;

/**
 * Defines a Gettext reader.
 *
 * @todo Or implement this as traversable/Iterator ?
 */
abstract class Reader {

  protected $gettextInterface;    // Gettext Data interface
  protected $metaData = array();  // Gettext meta data e.g. language, plural formula.
  protected $biteSize = 100;      // Default bite size
  protected $langcode = '';       // Language code of the translation data.
  //protected $language;            // Language object of selected language.
  protected $index = 0;           // Pointer where we are reading the content, in number of translations.
  protected $sourceSize;          // Calculated or estimated size of the data source in number of translations.
  protected $inProgress = FALSE;  // Boolean indicating the data connection is open and transfer may have started.
  //protected $valid = FALSE;       // Boolean indicating valid data is available. // @todo Needed?
  protected $finished = FALSE;    // Boolean indicating the last record has been read;
  protected $filter = array();    // Array of filter arguments used to filter translations being read.
  protected $errorLog = array();  // Log of parsing errors.

  /**
   * Implements magic function __construct().
   */
  public function __construct(GettextInterface $interface) {
    $this->gettextInterface = $interface;
  }

  /**
   * Implements magic function __destruct().
   */
  public function __destruct() {
    $this->gettextInterface->close();
  }

  /**
   * Return a translation object (singular or plural)
   *
   * @todo Define a translation object for this purpose?
   *       Or use a standard class for better performance?
   */
  public function read() {
  }

  /**
   * Return header/meta data (date, plural formula, etc.)
   */
  public function getMetaData() {
    return $this->metaData;
  }

  /**
   * Return TRUE if the file is opened or the transfer has started.
   */
  public function inProgress() {
    return $this->inProgress;
  }

  /**
   * Return the name of an error callback function
   */
  public function errorCallback() {
    return '';
  }

  /**
   * Return arguments for an error callback function
   */
  public function errorArguments() {
    return array();
  }

  /**
   * Return the name of a post processing callback function
   */
  public function postProcessCallback() {
    return '';
  }

  /**
   * Return arguments for a post processing callback function
   */
  public function postProcessArguments() {
    return array();
  }

  /**
   * Return the calculated or estimated size in number of translations. Zero for unknown. To be generated without opening the connection. e.g. use file size not number of lines.
   */
  public function size() {
    return $this->sourceSize;
  }

  /**
   * Return a bite size. Based on experience and size to be transfered within a reasonable time. Size in number of source/translations pairs. e.g. database records in locales_source or locales_target tables. A set of plural's are counted as one.
   */
  public function biteSize() {
    return $this->bite_size;
  }

  /**
   * Accept a set of data filter arguments: Language, Context
   */
  public function setFilter($arguments) {
    $this->filter = $arguments;
  }

  /**
   * Return Is valid. Valid data is available, not EOF, no errors, etc.
   */
  public function valid() {
    return $this->valid;
  }

  /**
   * Get percentage of completion (read)
   */
  public function poc() {
    if (!$this->$inProgress) {
      return 0;
    }
    if ($this->finished) {
      return 1;
    }
    // If reading is not finished, we limit the percentage to max. 95%
    // Percentages above 100% are a result of low estimate of source size and
    // will be suppressed.
    return min(0.95, $this->index/$this->sourceSize);
  }

  /**
   * Return syntax errors
   */
  public function getLog($category = NULL) {
    return $this->errorLog;
  }

  /**
   * Internal: log syntax errors
   */
  protected function log($line, $message) {
    $this->errorLog[$line] = $message;
  }

}
