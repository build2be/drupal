<?php

namespace Drupal\Core\Gettext;

use Drupal\Core\Gettext\POWriter;
use Drupal\Core\Gettext\POHeader;
use Drupal\Core\Gettext\POItem;

class PODbWriter implements PoWriterInterface, BatchStateInterface {
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
  private $_header;

  static function getDefaultState() {
    return array(
      'langcode' => NULL,
      'report' => array(
        'additions' => 0,
        'updates' => 0,
        'deletes' => 0,
        'skips' => 0,
        'ignored' => 0,
      ),
      'options' => array(
        'overwrite_options' => array(
          'not_customized' => FALSE,
          'customized' => FALSE,
        ),
        'customized' => LOCALE_NOT_CUSTOMIZED,
      ),
    );
  }

  /**
   * Report array summarizing the number of changes done in the form:
   * array(inserts, updates, deletes).
   *
   * @var array
   */
  private $_report;

  /**
   * @see BatchStateInterface
   */
  function __construct() {
    $this->setState(array());
  }

  public function getLangcode() {
    return $this->_langcode;
  }

  public function setLangcode($langcode) {
    $this->_langcode = $langcode;
  }

  public function getReport() {
    return $this->_report;
  }

  function setReport($report) {
    $report += array(
      'additions' => 0,
      'updates' => 0,
      'deletes' => 0,
      'skips' => 0,
      'ignored' => 0,
    );
    $this->_report = $report;
  }

  function getOptions() {
    return $this->_options;
  }

  function setOptions(array $options) {
    if (!isset($options['overwrite_options'])) {
      $options['overwrite_options'] = array(
        'not_customized' => FALSE,
        'customized' => FALSE,
      );
    }
    if (!isset($options['customized'])) {
      $options['customized'] = LOCALE_NOT_CUSTOMIZED;
    }
    $this->_options = $options;
  }

  /**
   * Implementation of BatchInterface::setState
   *
   * @param array $state
   */
  public function setState(array $state) {
    $state += self::getDefaultState();
    $this->_report = $state['report'];
    $this->setLangcode($state['langcode']);
    $this->setOptions($state['options']);
  }

  public function getState() {
    return array(
      'class' => __CLASS__,
      'report' => $this->getReport(),
      'langcode' => $this->getLangcode(),
      'options' => $this->getOptions(),
    );
  }

  function getHeader() {
    return $this->_header;
  }

  function setHeader(POHeader $header) {
    $this->_header = $header;
    $locale_plurals = variable_get('locale_translation_plurals', array());
    $options = $this->getOptions();
    $overwrite_options = $options['overwrite_options'];
    $lang = $this->_langcode;
    if (array_sum($overwrite_options) || empty($locale_plurals[$lang]['plurals'])) {
      // Get and store the plural formula if available.
      $plural = $header->getPlural();
      // TODO: this is a sloppy way to create a file name
      // but _locale_import_parse_plural_forms is also weird to me still
      $filepath = __CLASS__ . "::" . __METHOD__;
      if (isset($plural) && $p = $header->_locale_import_parse_plural_forms($plural, $filepath)) {
        list($nplurals, $formula) = $p;
        $locale_plurals[$lang] = array(
          'plurals' => $nplurals,
          'formula' => $formula,
        );
        variable_set('locale_translation_plurals', $locale_plurals);
      }
    }
  }

  function writeItem(POItem $item) {
    if ($item->plural) {
      $item->source = join(LOCALE_PLURAL_DELIMITER, $item->source);
      $item->translation = join(LOCALE_PLURAL_DELIMITER, $item->translation);
    }
    $this->_locale_import_one_string_db($this->_langcode, $item->context, $item->source, $item->translation, 'location', $this->_options['overwrite_options'], $this->_options['customized']);
  }

  public function writeItems(PoReaderInterface $reader, $count = 10) {
    $forever = $count == -1;
    while (($count-- > 0 || $forever) && ($item = $reader->readItem())) {
      $this->writeItem($item);
    }
  }

  /**
   * Imports one string into the database.
   *
   * @param $langcode
   *   Language code to import string into.
   * @param $context
   *   The context of this string.
   * @param $source
   *   Source string.
   * @param $translation
   *   Translation to language specified in $langcode.
   * @param $location
   *   Location value to save with source string.
   * @param $overwrite_options
   *   An associative array indicating what data should be overwritten, if any.
   *   - not_customized: not customized strings should be overwritten.
   *   - customized: customized strings should be overwritten.
   * @param $customized
   *   (optional) Whether the strings being imported should be saved as customized.
   *   Use LOCALE_CUSTOMIZED or LOCALE_NOT_CUSTOMIZED.
   *
   * @return
   *   The string ID of the existing string modified or the new string added.
   */
  function _locale_import_one_string_db($langcode, $context, $source, $translation, $location, $overwrite_options, $customized = LOCALE_NOT_CUSTOMIZED) {

    // Initialize overwrite options if not set.
    $overwrite_options += array(
      'not_customized' => FALSE,
      'customized' => FALSE,
    );

    // Look up the source string and any existing translation.
    $string = db_query("SELECT s.lid, t.customized FROM {locales_source} s LEFT JOIN {locales_target} t ON s.lid = t.lid AND t.language = :language WHERE s.source = :source AND s.context = :context", array(
      ':source' => $source,
      ':context' => $context,
      ':language' => $langcode,
        ))
        ->fetchObject();

    if (!empty($translation)) {
      // Skip this string unless it passes a check for dangerous code.
      if (!locale_string_is_safe($translation)) {
        watchdog('locale', 'Import of string "%string" was skipped because of disallowed or malformed HTML.', array('%string' => $translation), WATCHDOG_ERROR);
        $this->_report['skips']++;
        return 0;
      }
      elseif (isset($string->lid)) {
        // We have this source string saved already.
        db_update('locales_source')
            ->fields(array(
              'location' => $location,
            ))
            ->condition('lid', $string->lid)
            ->execute();

        if (!isset($string->customized)) {
          // No translation in this language.
          db_insert('locales_target')
              ->fields(array(
                'lid' => $string->lid,
                'language' => $langcode,
                'translation' => $translation,
                'customized' => $customized,
              ))
              ->execute();

          $this->_report['additions']++;
        }
        elseif ($overwrite_options[$string->customized ? 'customized' : 'not_customized']) {
          // Translation exists, only overwrite if instructed.
          db_update('locales_target')
              ->fields(array(
                'translation' => $translation,
                'customized' => $customized,
              ))
              ->condition('language', $langcode)
              ->condition('lid', $string->lid)
              ->execute();

          $this->_report['updates']++;
        }
        return $string->lid;
      }
      else {
        // No such source string in the database yet.
        $lid = db_insert('locales_source')
            ->fields(array(
              'location' => $location,
              'source' => $source,
              'context' => (string) $context,
            ))
            ->execute();

        db_insert('locales_target')
            ->fields(array(
              'lid' => $lid,
              'language' => $langcode,
              'translation' => $translation,
              'customized' => $customized,
            ))
            ->execute();

        $this->_report['additions']++;
        return $lid;
      }
    }
    elseif (isset($string->lid) && isset($string->customized) && $overwrite_options[$string->customized ? 'customized' : 'not_customized']) {
      // Empty translation, remove existing if instructed.
      db_delete('locales_target')
          ->condition('language', $langcode)
          ->condition('lid', $string->lid)
          ->execute();

      $this->_report['deletes']++;
      return $string->lid;
    }
  }

}