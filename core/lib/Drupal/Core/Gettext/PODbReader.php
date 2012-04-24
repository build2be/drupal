<?php

namespace Drupal\Core\Gettext;

use Drupal\Core\Gettext\BatchStateInterface;
use Drupal\Core\Gettext\POHeader;

class PODbReader implements BatchStateInterface, PoReaderInterface {
  /*
   * @param $overwrite_options
   *   An associative array indicating what data should be overwritten, if any.
   *   - not_customized: not customized strings should be overwritten.
   *   - customized: customized strings should be overwritten.
   * @param $customized
   *   (optional) Whether the strings being imported should be saved as customized.
   *   Use LOCALE_CUSTOMIZED or LOCALE_NOT_CUSTOMIZED.
   */

  private $_options;
  private $_langcode;
  private $_result;

  /**
   * lid of last read record
   *
   * This is used to manage state.
   * TODO: state is not working yet ... see prepared statement
   *
   * @see PODbReader::readItem()
   * @see PODbReader::buildQuery()
   */
  private $_lid = -1;

  /**
   * @see BatchStateInterface
   */
  function __construct() {
    $this->setOptions(array());
  }

  public function getLangcode() {
    return $this->_langcode;
  }

  public function setLangcode($langcode) {
    $this->_langcode = $langcode;
  }

  function getOptions() {
    return $this->_options;
  }

  function setOptions(array $options) {
    if (!isset($options['override_options'])) {
      $options['override_options'] = array();
    }
    if (!isset($options['customized'])) {
      $options['customized'] = LOCALE_NOT_CUSTOMIZED;
    }
    $this->_options = array(
      'override_options' => $options['override_options'],
      'customized' => $options['customized'],
    );
  }

  function setState(array $state) {
    $this->_lid = $state['lid'];
    $this->setOptions($state['options']);
    $this->buildQuery();
  }

  function getState() {
    return array(
      '__CLASS__' => __CLASS__,
      'lid' => $this->_lid,
      'options' => $this->_options,
    );
  }

  function getHeader() {
    return new POHeader($this->getLangcode());
  }

  public function setHeader(POHeader $header) {
    // empty on purpose
  }

  /**
   * Generates a structured array of all translated strings for the language.
   *
   * @param $language
   *   Language object to generate the output for, or NULL if generating
   *   translation template.
   * @param $options
   *   (optional) An associative array specifying what to include in the output:
   *   - customized: include customized strings (if TRUE)
   *   - uncustomized: include non-customized string (if TRUE)
   *   - untranslated: include untranslated source strings (if TRUE)
   *   Ignored if $language is NULL.
   *
   * @return
   *   An array of translated strings that can be used to generate an export.
   */
  private function buildQuery() {
    $langcode = $this->_langcode;
    $options = $this->_options;

    // Assume FALSE for all options if not provided by the API.
    $options += array(
      'customized' => FALSE,
      'not_customized' => FALSE,
      'not_translated' => FALSE,
    );
    if (array_sum($options) == 0) {
      // If user asked to not include anything in the translation files,
      // that would not make sense, so just fall back on providing a template.
      $language = NULL;
    }

    // Build and execute query to collect source strings and translations.
    $query = db_select('locales_source', 's');
    if (!empty($language)) {
      if ($options['not_translated']) {
        // Left join to keep untranslated strings in.
        $query->leftJoin('locales_target', 't', 's.lid = t.lid AND t.language = :language', array(':language' => $langcode));
      }
      else {
        // Inner join to filter for only translations.
        $query->innerJoin('locales_target', 't', 's.lid = t.lid AND t.language = :language', array(':language' => $langcode));
      }
      if ($options['customized']) {
        if (!$options['not_customized']) {
          // Filter for customized strings only.
          $query->condition('t.customized', LOCALE_CUSTOMIZED);
        }
        // Else no filtering needed in this case.
      }
      else {
        if ($options['not_customized']) {
          // Filter for non-customized strings only.
          $query->condition('t.customized', LOCALE_NOT_CUSTOMIZED);
        }
        else {
          // Filter for strings without translation.
          $query->isNull('t.translation');
        }
      }
      $query->fields('t', array('translation'));
    }
    else {
      $query->leftJoin('locales_target', 't', 's.lid = t.lid');
    }
    $query->fields('s', array('lid', 'source', 'context', 'location'));

    // TODO: we need to order by lid
    // This does not seem to work
    $query->orderBy('s.lid');
    $query->condition('s.lid', $this->_lid, '>');

    $this->_result = $query->execute();
    //echo "Executing: (lid = $this->_lid) : \n" . $this->_result->getQueryString() . "\n";
  }

  private function getResult() {
    if (!isset($this->_result)) {
      $this->buildQuery();
    }
    return $this->_result;
  }

  function readItem() {
    $result = $this->getResult();
    $values = $result->fetchAssoc();

    if ($values) {
      $poItem = new POItem();
      $poItem->fromArray($values);
      // Manage state
      $this->_lid = $values['lid'];
      return $poItem;
    }
  }

}