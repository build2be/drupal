<?php

// TODO: this file is kept for it's rich definition
// These definitely need to be moved to PoDbWriter mostly

/**
 * @file
 * Definition of Drupal\Core\Gettext\Writer.
 */

namespace Drupal\Core\Gettext;

use Drupal\Core\Gettext\GettextInterface;

/**
 * Defines a Gettext writer.
 */
abstract class Writer {

  protected $gettextInterface;    // Gettext Data interface
  protected $metaData = array();  // Gettext meta data e.g. language, plural formula.
  protected $biteSize = 100;      // Default bite size
  protected $langcode = '';       // Language code of the translation data.
  protected $language;            // Language object of selected language.
  protected $inProgress = FALSE;  // Boolean indicating the data connection is open and transfer may have started.
  protected $valid = FALSE;       // Boolean indicating valid data is available. // @todo Needed?
  protected $writeMode = '';      // Whether to replace or skip existing translations when writing translation.
  protected $resultsAdded = 0;    // Number of translations added.
  protected $resultsReplaced = 0; // Number of translations replaced.
  protected $resultsIgnored = 0;  // Number of translations Ignored.
  protected $resultsError = 0;    // Number of strings containing invalid html;
  protected $errorLog = array();  // Log of parsing errors.

  /**
   * Implements magic function __construct().
   */

  public function __construct(GettextInterface $interface, $langcode) {
    $this->gettextInterface = $interface;
    $this->langcode = $langcode;

    $languages = language_list();
    if (isset($languages[$langcode])) {
      $this->language = $languages[$langcode];
    }
    else {
      // @todo throw error: Unknown language code.
    }

    // Set default meta data.
    $this->metaData = array(
      'authors' => array(),
      'po_date' => date("Y-m-d H:iO"),
      'plurals' => 'nplurals=2; plural=(n > 1);',
    );
  }

  /**
   * Implements magic function __destruct().
   */
  public function __destruct() {
    $this->gettextInterface->close();
  }

  /**
   * Write translation string (singular or plural)
   *
   * @todo Define a translation object for this purpose?
   *       Or use a standard class for better performance?
   */
  public function write($translation) {

  }

  /**
   * Set header/meta data (date, plural formula, etc.)
   */
  public function setMetaData(array $data) {
    $this->metaData = array_merge($this->metaData, $data);
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
   * Return a bite size. Based on experience and size to be transfered within a reasonable time. Size in number of source/translations pairs. e.g. database records in locales_source or locales_target tables. A set of plural's are counted as one.
   */
  public function biteSize() {
    return $this->bite_size;
  }

  /**
   * Return the language code as defined by the data (e.g. po header). Use language filter (see below) as fallback.
   */
  public function langcode() {
    return $this->langcode;
  }

  /**
   * Accept write mode argument (replace, keep changes, skip existing)
   *
   * @todo Move this to __constructor()?
   */
  public function setWriteMode($mode) {
    $this->writeMode = $mode;
  }

  /**
   * Return Is valid. Valid data is available, not EOF, no errors, etc.
   *
   * @todo Needed for Writer?
   */
  public function valid() {
    return $this->valid;
  }

  /**
   * Get statistics (added, replaced, ignored, error, error log)
   */
  public function statistics() {
    return array(
      'added' => $this->resultsAdded,
      'replaced' => $this->resultsReplaced,
      'ignored' => $this->resultsIgnored,
      'error' => $this->resultsError,
    );
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
