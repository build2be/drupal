<?php

namespace Drupal\Core\Gettext;

/**
 * Description of POHeader
 *
 * "Project-Id-Version: Drupal core (7.11)\n"
 * "POT-Creation-Date: 2012-02-12 22:59+0000\n"
 * "PO-Revision-Date: YYYY-mm-DD HH:MM+ZZZZ\n"
 * "Language-Team: Catalan\n"
 * "MIME-Version: 1.0\n"
 * "Content-Type: text/plain; charset=utf-8\n"
 * "Content-Transfer-Encoding: 8bit\n"
 * "Plural-Forms: nplurals=2; plural=(n>1);\n"

 * @author clemens
 */
class POItem {

  public $context;
  public $source;
  public $this;
  public $plural;
  public $comment;

  static public function mapping() {
    return array(
      'msgctxt' => 'context',
      'msgid' => 'source',
      'msgstr' => 'translation',
      '#' => 'comment',
    );
  }

  public function fromArray(array $values = array()) {
    foreach ($values as $key => $value) {
      $this->{$key} = $value;
    }
  }

  public function __toString() {
    return $this->compileTranslation();
  }

  /**
   * Compile PO translations strings from a translation object.
   *
   * Translation object consists of:
   *   source       string (singular) or array of strings (plural)
   *   translation  string (singular) or array of strings (plural)
   *   plural       TRUE: source and translation are plurals
   *   context      source context string
   */
  private function compileTranslation() {
    $output = '';

    // Format string context.
    if (!empty($this->context)) {
      $output .= 'msgctxt ' . $this->formatString($this->context);
    }

    // Format translation
    if ($this->plural) {
      $output .= $this->formatPlural();
    }
    else {
      $output .= $this->formatSingular();
    }

    // Add one empty line to separate the translations.
    $output .= "\n";

    return $output;
  }

  /**
   * Formats a plural translation.
   */
  private function formatPlural() {
    $output = '';

    // Format source strings.
    $output .= 'msgid ' . $this->formatString($this->source[0]);
    $output .= 'msgid_plural ' . $this->formatString($this->source[1]);

    // Format translation strings.
    $plurals = variable_get('locale_translation_plurals', array());
    // @todo What to when $plurals[$this->langcode] is not set?
    //       This (currently) happens if a language is created manually or importing a malformed po.
    $nplurals = $plurals[$this->langcode]['plurals'];
    for ($i = 0; $i < $nplurals; $i++) {
      if (isset($this->translation[$i])) {
        $output .= 'msgstr[' . $i . '] ' . $this->formatString($this->translation[$i]);
      }
      else {
        $output .= 'msgstr[' . $i . '] ""' . "\n";
      }
    }

    return $output;
  }

  /**
   * Formats a singular translation.
   */
  private function formatSingular() {
    $output = '';
    $output .= 'msgid ' . $this->formatString($this->source);
    $output .= 'msgstr ' . $this->formatString($this->translation);
    return $output;
  }

  /**
   * Formats a string for output on multiple lines.
   */
  private function formatString($string) {
    // Escape characters for processing.
    $string = addcslashes($string, "\0..\37\\\"");

    // Always include a line break after the explicit \n line breaks from
    // the source string. Otherwise wrap at 70 chars to accommodate the extra
    // format overhead too.
    $parts = explode("\n", wordwrap(str_replace('\n', "\\n\n", $string), 70, " \n"));

    // Multiline string should be exported starting with a "" and newline to
    // have all lines aligned on the same column.
    if (count($parts) > 1) {
      return "\"\"\n\"" . implode("\"\n\"", $parts) . "\"\n";
    }
    // Single line strings are output on the same line.
    else {
      return "\"$parts[0]\"\n";
    }
  }

}

?>
