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

use Drupal\Core\Gettext\BatchStateInterface;
use Drupal\Core\Gettext\POReader;
use Drupal\Core\Gettext\POHeader;

/**
 * Defines a Gettext reader for PO format.
 *
 * According to http://www.gnu.org/software/gettext/manual/gettext.html#PO-Files
 * a PO file may contain the following
 *
 * white-space
 * #  translator-comments
 * #. extracted-comments
 * #: reference...
 * #, flag...
 * #| msgid previous-untranslated-string
 * msgid untranslated-string
 * msgstr translated-string
 *
 * TODOs:
 * Current implementation has a simple white-space fall-thru. This should
 * be improved
 *
 * Current implementation misses the special comments
 * #.
 * #:
 * #|
 */
class PoFileReader implements BatchStateInterface, PoStreamInterface, PoReaderInterface {

  /**
   * Source line number being parsed.
   *
   * @var int
   */
  private $lineno = 0;

  /**
   * The context of the translation being parsed.
   *
   * @var string
   */
  private $context = 'COMMENT';

  /**
   * Current entry being read.
   *
   * @var array
   */
  private $current = array();
  private $_uri = '';
  private $_langcode = NULL;
  private $_size;
  private $_fd;
  private $_header;

  private $translation;
  private $finished;

  /**
   * @see BatchStateInterface
   */
  public function __construct() {
    // empty
  }

  public function getURI() {
    return $this->_uri;
  }

  public function setURI($uri) {
    $this->_uri = $uri;
  }

  public function getLangcode() {
    return $this->_langcode;
  }

  public function setLangcode($langcode) {
    $this->_langcode = $langcode;
  }

  public function open() {
    if (!empty($this->_uri)) {
      $this->_fd = fopen($this->_uri, 'rb');
      $this->_size = ftell($this->_fd);
      // We immediately read the header as we are at BOF
      $this->readHeader();
    }
    else {
      throw new \Exception("Cannot open without URI set");
    }
  }

  public function close() {
    if ($this->_fd) {
      fclose($this->_fd);
    }
  }


  public function setState(array $state) {
    $this->setURI($state['uri']);
    $this->setLangcode($state['langcode']);
    // Make sure to (re)read the POHeader
    $this->open();
    // Move to last read position.
    if (isset($state['seekpos'])) {
      fseek($this->_fd, $state['seekpos']);
    }
    if (isset($state['lineno'])) {
      $this->lineno = $state['lineno'];
    }
  }

  public function getState() {
    return array(
      'class' => __CLASS__,
      'uri' => $this->_uri,
      'langcode' => $this->_langcode,
      'seekpos' => ftell($this->_fd),
      'lineno' => $this->lineno,
    );
  }

  /**
   * Return a translation object (singular or plural)
   *
   * @todo Define a translation object for this purpose?
   *       Or use a standard class for better performance?
   */
  public function readItem() {
    $this->readTranslation();
    return $this->translation;
  }

  private function readTranslation() {
    $this->translation = NULL;
    while (!$this->finished && is_null($this->translation)) {
      $this->readLine();
    }
    return $this->translation;
  }

  public function getHeader() {
    return $this->_header;
  }

  public function setHeader(POHeader $header) {
    // TODO : throw exception?
  }

  /**
   * Reads the header from the given input stream.
   *
   * We need to read the optional first COMMENT
   * Next read a MSGID and a MSGSTR
   *
   * TODO: is a header required?
   */
  private function readHeader() {
    $translation = $this->readTranslation();
    $header = new POHeader;
    $header->setFromString(trim($translation->translation));
    $this->_header = $header;
  }

  /**
   * Reads a line from a PO file.
   *
   * While reading a line it's content is processed according to current
   * context.
   *
   * The parser context. Can be:
   *  - 'COMMENT' (#)
   *  - 'MSGID' (msgid)
   *  - 'MSGID_PLURAL' (msgid_plural)
   *  - 'MSGCTXT' (msgctxt)
   *  - 'MSGSTR' (msgstr or msgstr[])
   *  - 'MSGSTR_ARR' (msgstr_arg)
   *
   * @return boolean FALSE or NULL
   */
  private function readLine() {
    // a string or boolean FALSE
    $line = fgets($this->_fd);
    $this->finished = ($line === FALSE);
    if (!$this->finished) {

      if ($this->lineno == 0) {
        // The first line might come with a UTF-8 BOM, which should be removed.
        $line = str_replace("\xEF\xBB\xBF", '', $line);
        // Current plurality for 'msgstr[]'.
        $this->plural = 0;
      }

      $this->lineno++;

      // Trim away the linefeed.
      $line = trim(strtr($line, array("\\\n" => "")));

      if (!strncmp('#', $line, 1)) {
        // Lines starting with '#' are comments.

        if ($this->context == 'COMMENT') {
          // Already in comment token, insert the comment.
          $this->current['#'][] = substr($line, 1);
        }
        elseif (($this->context == 'MSGSTR') || ($this->context == 'MSGSTR_ARR')) {
          // We are currently in string token, close it out.
          $this->saveOneString();

          // Start a new entry for the comment.
          $this->current = array();
          $this->current['#'][] = substr($line, 1);

          $this->context = 'COMMENT';
          return TRUE;
        }
        else {
          // A comment following any other token is a syntax error.
          $this->log('The translation file %filename contains an error: "msgstr" was expected but not found on line %line.', $this->lineno);
          return FALSE;
        }
        return;
      }
      elseif (!strncmp('msgid_plural', $line, 12)) {
        // A plural form for the current message.

        if ($this->context != 'MSGID') {
          // A plural form cannot be added to anything else but the id directly.
          $this->log('The translation file %filename contains an error: "msgid_plural" was expected but not found on line %line.', $this->lineno);
          return FALSE;
        }

        // Remove 'msgid_plural' and trim away whitespace.
        $line = trim(substr($line, 12));
        // At this point, $line should now contain only the plural form.

        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          // The plural form must be wrapped in quotes.
          $this->log('The translation file %filename contains a syntax error on line %line.', $this->lineno);
          return FALSE;
        }

        // Append the plural form to the current entry.
        if (is_string($this->current['msgid'])) {
          // The first value was stored as string. Now we know the context is
          // plural, it is converted to array.
          $this->current['msgid'] = array($this->current['msgid']);
        }
        $this->current['msgid'][] = $quoted;

        $this->context = 'MSGID_PLURAL';
        return;
      }
      elseif (!strncmp('msgid', $line, 5)) {
        // Starting a new message.

        if (($this->context == 'MSGSTR') || ($this->context == 'MSGSTR_ARR')) {
          // We are currently in a message string, close it out.
          $this->saveOneString();

          // Start a new context for the id.
          $this->current = array();
        }
        elseif ($this->context == 'MSGID') {
          // We are currently already in the context, meaning we passed an id with no data.
          $this->log('The translation file %filename contains an error: "msgid" is unexpected on line %line.', $this->lineno);
          return FALSE;
        }

        // Remove 'msgid' and trim away whitespace.
        $line = trim(substr($line, 5));
        // At this point, $line should now contain only the message id.

        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          // The message id must be wrapped in quotes.
          $this->log('The translation file %filename contains a syntax error on line %line.', $this->lineno);
          return FALSE;
        }

        $this->current['msgid'] = $quoted;
        $this->context = 'MSGID';
        return;
      }
      elseif (!strncmp('msgctxt', $line, 7)) {
        // Starting a new context.

        if (($this->context == 'MSGSTR') || ($this->context == 'MSGSTR_ARR')) {
          // We are currently in a message, start a new one.
          $this->saveOneString($this->current);
          $this->current = array();
        }
        elseif (!empty($this->current['msgctxt'])) {
          // A context cannot apply to another context.
          $this->log('The translation file %filename contains an error: "msgctxt" is unexpected on line %line.', $this->lineno);
          return FALSE;
        }

        // Remove 'msgctxt' and trim away whitespaces.
        $line = trim(substr($line, 7));
        // At this point, $line should now contain the context.

        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          // The context string must be quoted.
          $this->log('The translation file %filename contains a syntax error on line %line.', $this->lineno);
          return FALSE;
        }

        $this->current['msgctxt'] = $quoted;

        $this->context = 'MSGCTXT';
        return;
      }
      elseif (!strncmp('msgstr[', $line, 7)) {
        // A message string for a specific plurality.

        if (($this->context != 'MSGID') && ($this->context != 'MSGCTXT') && ($this->context != 'MSGID_PLURAL') && ($this->context != 'MSGSTR_ARR')) {
          // Message strings must come after msgid, msgxtxt, msgid_plural, or other msgstr[] entries.
          $this->log('The translation file %filename contains an error: "msgstr[]" is unexpected on line %line.', $this->lineno);
          return FALSE;
        }

        // Ensure the plurality is terminated.
        if (strpos($line, ']') === FALSE) {
          $this->log('The translation file %filename contains a syntax error on line %line.', $this->lineno);
          return FALSE;
        }

        // Extract the plurality.
        $frombracket = strstr($line, '[');
        $this->plural = substr($frombracket, 1, strpos($frombracket, ']') - 1);

        // Skip to the next whitespace and trim away any further whitespace, bringing $line to the message data.
        $line = trim(strstr($line, " "));

        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          // The string must be quoted.
          $this->log('The translation file %filename contains a syntax error on line %line.', $this->lineno);
          return FALSE;
        }
        if (!isset($this->current['msgstr']) || !is_array($this->current['msgstr'])) {
          $this->current['msgstr'] = array();
        }

        $this->current['msgstr'][$this->plural] = $quoted;

        $this->context = 'MSGSTR_ARR';
        return;
      }
      elseif (!strncmp("msgstr", $line, 6)) {
        // A string for the an id or context.

        if (($this->context != 'MSGID') && ($this->context != 'MSGCTXT')) {
          // Strings are only valid within an id or context scope.
          $this->log('The translation file %filename contains an error: "msgstr" is unexpected on line %line.', $this->lineno);
          return FALSE;
        }

        // Remove 'msgstr' and trim away away whitespaces.
        $line = trim(substr($line, 6));
        // At this point, $line should now contain the message.

        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          // The string must be quoted.
          $this->log('The translation file %filename contains a syntax error on line %line.', $this->lineno);
          return FALSE;
        }

        $this->current['msgstr'] = $quoted;

        $this->context = 'MSGSTR';
        return;
      }
      elseif ($line != '') {
        // Anything that is not a token may be a continuation of a previous token.

        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          // The string must be quoted.
          $this->log('The translation file %filename contains a syntax error on line %line.', $this->lineno);
          return FALSE;
        }

        // Append the string to the current context.
        if (($this->context == 'MSGID') || ($this->context == 'MSGID_PLURAL')) {
          if (is_array($this->current['msgid'])) {
            // Add string to last array element.
            $last_index = count($this->current['msgid']) - 1;
            $this->current['msgid'][$last_index] .= $quoted;
          }
          else {
            $this->current['msgid'] .= $quoted;
          }
        }
        elseif ($this->context == 'MSGCTXT') {
          $this->current['msgctxt'] .= $quoted;
        }
        elseif ($this->context == 'MSGSTR') {
          $this->current['msgstr'] .= $quoted;
        }
        elseif ($this->context == 'MSGSTR_ARR') {
          $this->current['msgstr'][$this->plural] .= $quoted;
        }
        else {
          // No valid context to append to.
          $this->log('The translation file %filename contains an error: there is an unexpected string on line %line.', $this->lineno);
          return FALSE;
        }
        return;
      }
    }

    // Empty line read or EOF of PO file, closed out the last entry.
    if (($this->context == 'MSGSTR') || ($this->context == 'MSGSTR_ARR')) {
      $this->saveOneString($this->current);
      $this->current = array();
    }
    elseif ($this->context != 'COMMENT') {
      $this->log('The translation file %filename ended unexpectedly at line %line.', $this->lineno);
      return FALSE;
    }
  }

  /**
   * Sets an error message if an error occurred during locale file parsing.
   *
   * @param $message
   *   The message to be translated.
   * @param $lineno
   *   An optional line number argument.
   */
  protected function log($message, $lineno = NULL) {
    if (isset($lineno)) {
      $vars['%line'] = $lineno;
    }
    $t = get_t();
    $this->errorLog[] = $t($message, $vars);
  }

  /**
   * Store the parsed values as translation object.
   */
  public function saveOneString() {
    $value = $this->current;
    $plural = FALSE;

    $comments = '';
    if (isset($value['#'])) {
      $comments = $this->shortenComments($value['#']);
    }

    if (is_array($value['msgstr'])) {
      // Sort plural variants by their form index.
      ksort($value['msgstr']);
      $plural = TRUE;
    }

    $translation = new POItem;
    $translation->context = isset($value['msgctxt']) ? $value['msgctxt'] : '';
    $translation->source = $value['msgid'];
    $translation->translation = $value['msgstr'];
    $translation->plural = $plural;
    $translation->comment = $comments;

    $this->translation = $translation;

    $this->context = 'COMMENT';
  }

  /**
   * Parses a string in quotes.
   *
   * @param $string
   *   A string specified with enclosing quotes.
   *
   * @return
   *   The string parsed from inside the quotes.
   */
  function parseQuoted($string) {
    if (substr($string, 0, 1) != substr($string, -1, 1)) {
      return FALSE;   // Start and end quotes must be the same
    }
    $quote = substr($string, 0, 1);
    $string = substr($string, 1, -1);
    if ($quote == '"') {        // Double quotes: strip slashes
      return stripcslashes($string);
    }
    elseif ($quote == "'") {  // Simple quote: return as-is
      return $string;
    }
    else {
      return FALSE;             // Unrecognized quote
    }
  }

  /**
   * Generates a short, one-string version of the passed comment array.
   *
   * @param $comment
   *   An array of strings containing a comment.
   *
   * @return
   *   Short one-string version of the comment.
   */
  private function shortenComments($comment) {
    $comm = '';
    while (count($comment)) {
      $test = $comm . substr(array_shift($comment), 1) . ', ';
      if (strlen($comm) < 130) {
        $comm = $test;
      }
      else {
        break;
      }
    }
    return trim(substr($comm, 0, -2));
  }

}
