<?php

/**
 * @file
 * Contains \Drupal\node\Tests\Views\NodeFieldFilterTest.
 */

namespace Drupal\node\Tests\Views;

use Drupal\Core\Language\Language;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests node field filters with translations.
 *
 * @group node
 */
class NodeFieldFilterTest extends NodeTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = array('language');

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_field_filters');

  /**
   * List of node titles by language.
   *
   * @var array
   */
  public $node_titles = array();

  function setUp() {
    parent::setUp();

    // Create Page content type.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(array('type' => 'page', 'name' => 'Basic page'));
    }

    // Add two new languages.
    $language = new Language(array(
      'id' => 'fr',
      'name' => 'French',
    ));
    language_save($language);

    $language = new Language(array(
      'id' => 'es',
      'name' => 'Spanish',
    ));
    language_save($language);

    // Make the body field translatable. The title is already translatable by
    // definition.
    $field = FieldStorageConfig::loadByName('node', 'body');
    $field->translatable = TRUE;
    $field->save();

    // Set up node titles.
    $this->node_titles = array(
      'en' => 'Food in Paris',
      'es' => 'Comida en Paris',
      'fr' => 'Nouriture en Paris',
    );

    // Create node with translations.
    $node = $this->drupalCreateNode(array('title' => $this->node_titles['en'], 'langcode' => 'en', 'type' => 'page', 'body' => array(array('value' => $this->node_titles['en']))));
    foreach (array('es', 'fr') as $langcode) {
      $translation = $node->addTranslation($langcode, array('title' => $this->node_titles[$langcode]));
      $translation->body->value = $this->node_titles[$langcode];
    }
    $node->save();
  }

  /**
   * Tests body and title filters.
   */
  public function testFilters() {
    // Test the title filter page, which filters for title contains 'Comida'.
    // Should show just the Spanish translation, once.
    $this->assertPageCounts('test-title-filter', array('es' => 1, 'fr' => 0, 'en' => 0), 'Comida title filter');

    // Test the body filter page, which filters for body contains 'Comida'.
    // Should show just the Spanish translation, once.
    $this->assertPageCounts('test-body-filter', array('es' => 1, 'fr' => 0, 'en' => 0), 'Comida body filter');

    // Test the title Paris filter page, which filters for title contains
    // 'Paris'. Should show each translation once.
    $this->assertPageCounts('test-title-paris', array('es' => 1, 'fr' => 1, 'en' => 1), 'Paris title filter');

    // Test the body Paris filter page, which filters for body contains
    // 'Paris'. Should show each translation once.
    $this->assertPageCounts('test-body-paris', array('es' => 1, 'fr' => 1, 'en' => 1), 'Paris body filter');
  }

  /**
   * Asserts that the given node translation counts are correct.
   *
   * @param string $path
   *   Path of the page to test.
   * @param array $counts
   *   Array whose keys are languages, and values are the number of times
   *   that translation should be shown on the given page.
   * @param string $message
   *   Message suffix to display.
   */
  protected function assertPageCounts($path, $counts, $message) {
    // Disable read more links.
    entity_get_display('node', 'page', 'teaser')->removeComponent('links')->save();

    // Get the text of the page.
    $this->drupalGet($path);
    $text = $this->getTextContent();

    // Check the counts. Note that the title and body are both shown on the
    // page, and they are the same. So the title/body string should appear on
    // the page twice as many times as the input count.
    foreach ($counts as $langcode => $count) {
      $this->assertEqual(substr_count($text, $this->node_titles[$langcode]), 2 * $count, 'Translation ' . $langcode . ' has count ' . $count . ' with ' . $message);
    }
  }
}
