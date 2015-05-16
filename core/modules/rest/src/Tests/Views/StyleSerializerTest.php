<?php

/**
 * @file
 * Contains \Drupal\rest\Tests\Views\StyleSerializerTest.
 */

namespace Drupal\rest\Tests\Views;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;
use Drupal\serialization\Collection;
use Drupal\system\Tests\Cache\AssertPageCacheContextsAndTagsTrait;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Drupal\views\Tests\Plugin\PluginTestBase;
use Drupal\views\Tests\ViewTestData;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the serializer style plugin.
 *
 * @group rest
 * @see \Drupal\rest\Plugin\views\display\RestExport
 * @see \Drupal\rest\Plugin\views\style\Serializer
 * @see \Drupal\rest\Plugin\views\row\DataEntityRow
 * @see \Drupal\rest\Plugin\views\row\DataFieldRow
 */
class StyleSerializerTest extends PluginTestBase {

  use AssertPageCacheContextsAndTagsTrait;

  /**
   * {@inheritdoc}
   */
  protected $dumpHeaders = TRUE;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = array('views_ui', 'entity_test', 'hal', 'rest_test_views', 'node', 'text', 'field');

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_serializer_display_field', 'test_serializer_display_entity', 'test_serializer_node_display_field');

  /**
   * A user with administrative privileges to look at test entity and configure views.
   */
  protected $adminUser;

  protected function setUp() {
    parent::setUp();

    ViewTestData::createTestViews(get_class($this), array('rest_test_views'));

    $this->adminUser = $this->drupalCreateUser(array('administer views', 'administer entity_test content', 'access user profiles', 'view test entity'));

    // Save some entity_test entities.
    for ($i = 1; $i <= 10; $i++) {
      entity_create('entity_test', array('name' => 'test_' . $i, 'user_id' => $this->adminUser->id()))->save();
    }

    $this->enableViewsTestModule();
  }

  /**
   * Retrieves a Drupal path or an absolute path with hal+json.
   *
   * Sets Accept-Header to application/hal+json and decodes the result.
   *
   * @param string $path
   *   Path to request HAL+JSON from.
   * @param array $options
   *   Array of options to pass to the URL generator.
   * @param array $headers
   *   Array of headers.
   *
   * @return array
   *   Decoded json.
   * Requests a Drupal path in HAL+JSON format, and JSON decodes the response.
   */
  protected function drupalGetHalJson($path, array $options = array(), array $headers = array()) {
    $headers[] = 'Accept: application/hal+json';
    return Json::decode($this->drupalGet($path, $options, $headers));
  }

  /**
   * Retrieve the Collection object for given view.
   *
   * @return \Drupal\serialization\Collection
   *   The collection object to pass into the serializer.
   */
  protected function getCollectionFromView(ViewExecutable $view) {
    return $view->getStyle()->getCollection($view);
  }

  /**
   * Checks the behavior of the Serializer callback paths and row plugins.
   */
  public function testSerializerResponses() {
    // Test the serialize callback.
    $view = Views::getView('test_serializer_display_field');
    $view->initDisplay();
    $this->executeView($view);

    // application/json-type serialization.
    $actual_json = $this->drupalGetJSON('test/serialize/field');
    $this->assertResponse(200);
    $this->assertCacheTags($view->getCacheTags());
    // @todo Due to https://www.drupal.org/node/2352009 we can't yet test the
    // propagation of cache max-age.

    // Test the http Content-type.
    $headers = $this->drupalGetHeaders();
    $this->assertEqual($headers['content-type'], 'application/json', 'The header Content-type is correct.');

    $expected = array();
    foreach ($view->result as $row) {
      $expected_row = array();
      foreach ($view->field as $id => $field) {
        $expected_row[$id] = $field->render($row);
      }
      $expected[] = $expected_row;
    }

    $this->assertIdentical($actual_json, $expected, 'The expected JSON output was found.');

    // Test that the rendered output and the preview output are the same.
    $view->destroy();
    $view->setDisplay('rest_export_1');
    // Mock the request content type by setting it on the display handler.
    $view->display_handler->setContentType('json');
    $output = $view->preview();
    $this->assertIdentical(Json::encode($actual_json), drupal_render_root($output), 'The expected JSON preview output was found.');

    // Test a 403 callback.
    $this->drupalGet('test/serialize/denied');
    $this->assertResponse(403);

    // Test the entity rows.
    $view = Views::getView('test_serializer_display_entity');
    $view->setDisplay('rest_export_1');
    $this->executeView($view);

    $serializer = $this->container->get('serializer');
    // Create the entity collection.
    $collection = $this->getCollectionFromView($view);
    $expected = $serializer->serialize($collection, 'json');

    $this->assertFalse($collection->hasLinks(), 'Collection created from a non-paging view does not have (hypermedia) link relations');

    $actual_json = $this->drupalGetJSON('test/serialize/entity');
    $this->assertResponse(200);
    $this->assertIdentical(Json::encode($actual_json), $expected, 'The expected JSON output was found.');

    // application/hal+json-type serialization.
    $expected = $serializer->serialize($collection, 'hal_json');
    $actual_json = $this->drupalGetHalJson('test/serialize/entity');
    $this->assertIdentical(Json::encode($actual_json), $expected, 'The expected HAL output was found.');

    // Make assertions on the structure of the response.
    $this->assertTrue(isset($actual_json['_embedded']), 'Has _embedded key.');
    $this->assertTrue(isset($actual_json['_links']), 'Has _links key.');

    $this->assertEqual(count($actual_json['_embedded']['item']), 10);
    $this->assertEqual($actual_json['_links']['self']['href'], $this->viewUrl($view));
    $this->assertEqual(array_keys($actual_json['_links']), array('self'));
  }

  /**
   * Build an absolute URL from a view path, accounting for paging.
   *
   * @param ViewExecutable $view
   * @param null $page
   *
   * @return string
   */
  protected function viewUrl(ViewExecutable $view, $page = NULL) {
    // TODO: this is hackish as toString return path include start /
    $base_url = 'base:/'  . $view->getUrl()->toString();
    $options = array(
      'absolute' => TRUE,
    );
    if (isset($page)) {
      $options += array('query' => array('page' => $page));
    }

    return Url::fromUri($base_url, $options)->toString();
  }

  /**
   * Checks the paging behavior of callback paths and row plugins.
   */
  protected function testSerializerPageableCollectionHalJsonResponses() {
    // Test the entity rows - with paging.
    $view = Views::getView('test_serializer_display_entity');
    $view->setDisplay('rest_export_paging');
    $this->executeView($view);

    // Get the serializer service.
    $serializer = $this->container->get('serializer');
    // Create the entity collection from the current view.
    $collection = $this->getCollectionFromView($view);
    $this->assertTrue($collection->hasLinks(), 'Collection created from a paging view has (hypermedia) link relations');

    $expected = $serializer->serialize($collection, 'hal_json');
    $actual_json = $this->drupalGetHalJson('test/serialize/entity_paging');
    $this->assertIdentical(Json::encode($actual_json), $expected, 'The expected HAL output was found.');

    // Make assertions on the structure of the response.
    $this->assertTrue(isset($actual_json['_embedded']) && isset($actual_json['_links']), 'Has _links and _embedded keys');

    $this->assertEqual(count($actual_json['_embedded']['item']), 1);
    $this->assertEqual($actual_json['_links']['self']['href'], $this->viewUrl($view));
    $this->assertEqual($actual_json['_links']['first']['href'], $this->viewUrl($view, 0));
    $this->assertEqual($actual_json['_links']['next']['href'], $this->viewUrl($view, 1));
    $this->assertEqual($actual_json['_links']['last']['href'], $this->viewUrl($view, 9));
    $this->assertEqual(array_keys($actual_json['_links']), array(
      'self',
      'first',
      'next',
      'last',
    ));

    // Load the second page.
    $actual_json_page_1 = $this->drupalGetHalJson($actual_json['_links']['next']['href']);
    $this->assertTrue(isset($actual_json_page_1['_embedded']) && isset($actual_json_page_1['_links']), 'Has _links and _embedded keys');

    $this->assertEqual(count($actual_json_page_1['_embedded']['item']), 1);
    $this->assertEqual($actual_json_page_1['_links']['self']['href'], $this->viewUrl($view, 1));
    $this->assertEqual($actual_json_page_1['_links']['first']['href'], $this->viewUrl($view, 0));
    $this->assertEqual($actual_json_page_1['_links']['prev']['href'], $this->viewUrl($view, 0));
    $this->assertEqual($actual_json_page_1['_links']['next']['href'], $this->viewUrl($view, 2));
    $this->assertEqual($actual_json_page_1['_links']['last']['href'], $this->viewUrl($view, 9));
    $this->assertEqual(array_keys($actual_json_page_1['_links']), array(
      'self',
      'first',
      'prev',
      'next',
      'last',
    ));

    // Test the entity rows - with paging.
    $view = Views::getView('test_serializer_display_entity');
    $view->setDisplay('rest_export_paging');
    $view->setCurrentPage(1);
    $this->executeView($view);

    // Create the entity collection.
    $collection = $this->getCollectionFromView($view);
    $this->assertTrue($collection->hasLinks(), 'Collection created from a paging view has (hypermedia) link relations');
    $expected = $serializer->serialize($collection, 'hal_json');

    $this->assertEqual(Json::encode($actual_json_page_1), $expected, 'The expected HAL output for page=1 was found.');

    // Load the last page.
    $actual_json_page_last = $this->drupalGetHalJSON($actual_json['_links']['last']['href']);

    $this->assertTrue(isset($actual_json_page_last['_embedded']) && isset($actual_json_page_last['_links']), 'Has _links and _embedded keys');

    $this->assertEqual(count($actual_json_page_last['_embedded']['item']), 1);
    $this->assertEqual($actual_json_page_last['_links']['self']['href'], $this->viewUrl($view, 9));
    $this->assertEqual($actual_json_page_last['_links']['first']['href'], $this->viewUrl($view, 0));
    $this->assertEqual($actual_json_page_last['_links']['prev']['href'], $this->viewUrl($view, 8));
    $this->assertEqual($actual_json_page_last['_links']['last']['href'], $this->viewUrl($view, 9));
    $this->assertEqual(array_keys($actual_json_page_last['_links']), array(
      'self',
      'first',
      'prev',
      'last',
    ));

    $expected_cache_tags = $view->getCacheTags();
    $expected_cache_tags[] = 'entity_test_list';
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    foreach ($entities as $entity) {
      $expected_cache_tags = Cache::mergeTags($expected_cache_tags, $entity->getCacheTags());
    }
    $this->assertCacheTags($expected_cache_tags);

    $this->assertEqual(Json::encode($actual_json_page_last), $expected, 'The expected HAL output for last page was found.');

    // Change the default format to xml.
    $view->setDisplay('rest_export_1');
    $view->getDisplay()->setOption('style', array(
      'type' => 'serializer',
      'options' => array(
        'uses_fields' => FALSE,
        'formats' => array(
          'xml' => 'xml',
        ),
      ),
    ));
    $view->save();
    $expected = $serializer->serialize($collection, 'xml');
    $actual_xml = $this->drupalGet('test/serialize/entity');
    $this->assertIdentical($actual_xml, $expected, 'The expected XML output was found.');

    // Allow multiple formats.
    $view->setDisplay('rest_export_1');
    $view->getDisplay()->setOption('style', array(
      'type' => 'serializer',
      'options' => array(
        'uses_fields' => FALSE,
        'formats' => array(
          'xml' => 'xml',
          'json' => 'json',
        ),
      ),
    ));
    $view->save();
    $expected = $serializer->serialize($collection, 'json');
    $actual_json = $this->drupalGet('test/serialize/entity', array(), array('Accept: application/json'));
    $this->assertIdentical($actual_json, $expected, 'The expected JSON output was found.');
    $expected = $serializer->serialize($collection, 'xml');
    $actual_xml = $this->drupalGet('test/serialize/entity', array(), array('Accept: application/xml'));
    $this->assertIdentical($actual_xml, $expected, 'The expected XML output was found.');
  }

  /**
   * Tests the response format configuration.
   */
  public function testResponseFormatConfiguration() {
    $this->drupalLogin($this->adminUser);

    $style_options = 'admin/structure/views/nojs/display/test_serializer_display_field/rest_export_1/style_options';

    // Select only 'xml' as an accepted format.
    $this->drupalPostForm($style_options, array('style_options[formats][xml]' => 'xml'), t('Apply'));
    $this->drupalPostForm(NULL, array(), t('Save'));

    // Should return a 406.
    $this->drupalGet('test/serialize/field', array(), array('Accept: application/json'));
    $this->assertResponse(406, 'A 406 response was returned when JSON was requested.');
     // Should return a 200.
    $this->drupalGet('test/serialize/field', array(), array('Accept: application/xml'));
    $this->assertResponse(200, 'A 200 response was returned when XML was requested.');

    // Add 'json' as an accepted format, so we have multiple.
    $this->drupalPostForm($style_options, array('style_options[formats][json]' => 'json'), t('Apply'));
    $this->drupalPostForm(NULL, array(), t('Save'));

    // Should return a 200.
    // @todo This should be fixed when we have better content negotiation.
    $this->drupalGet('test/serialize/field', array(), array('Accept: */*'));
    $this->assertResponse(200, 'A 200 response was returned when any format was requested.');

    // Should return a 200. Emulates a sample Firefox header.
    $this->drupalGet('test/serialize/field', array(), array('Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'));
    $this->assertResponse(200, 'A 200 response was returned when a browser accept header was requested.');

    // Should return a 200.
    $this->drupalGet('test/serialize/field', array(), array('Accept: application/json'));
    $this->assertResponse(200, 'A 200 response was returned when JSON was requested.');
    // Should return a 200.
    $this->drupalGet('test/serialize/field', array(), array('Accept: application/xml'));
    $this->assertResponse(200, 'A 200 response was returned when XML was requested');
    // Should return a 406.
    $this->drupalGet('test/serialize/field', array(), array('Accept: application/html'));
    $this->assertResponse(406, 'A 406 response was returned when HTML was requested.');
  }

  /**
   * Test the field ID alias functionality of the DataFieldRow plugin.
   */
  public function testUIFieldAlias() {
    $this->drupalLogin($this->adminUser);

    // Test the UI settings for adding field ID aliases.
    $this->drupalGet('admin/structure/views/view/test_serializer_display_field/edit/rest_export_1');
    $row_options = 'admin/structure/views/nojs/display/test_serializer_display_field/rest_export_1/row_options';
    $this->assertLinkByHref($row_options);

    // Test an empty string for an alias, this should not be used. This also
    // tests that the form can be submitted with no aliases.
    $this->drupalPostForm($row_options, array('row_options[field_options][name][alias]' => ''), t('Apply'));
    $this->drupalPostForm(NULL, array(), t('Save'));

    $view = Views::getView('test_serializer_display_field');
    $view->setDisplay('rest_export_1');
    $this->executeView($view);

    $expected = array();
    foreach ($view->result as $row) {
      $expected_row = array();
      foreach ($view->field as $id => $field) {
        $expected_row[$id] = $field->render($row);
      }
      $expected[] = $expected_row;
    }

    $this->assertIdentical($this->drupalGetJSON('test/serialize/field'), $expected);

    // Test a random aliases for fields, they should be replaced.
    $alias_map = array(
      'name' => $this->randomMachineName(),
      // Use # to produce an invalid character for the validation.
      'nothing' => '#' . $this->randomMachineName(),
      'created' => 'created',
    );

    $edit = array('row_options[field_options][name][alias]' => $alias_map['name'], 'row_options[field_options][nothing][alias]' => $alias_map['nothing']);
    $this->drupalPostForm($row_options, $edit, t('Apply'));
    $this->assertText(t('The machine-readable name must contain only letters, numbers, dashes and underscores.'));

    // Change the map alias value to a valid one.
    $alias_map['nothing'] = $this->randomMachineName();

    $edit = array('row_options[field_options][name][alias]' => $alias_map['name'], 'row_options[field_options][nothing][alias]' => $alias_map['nothing']);
    $this->drupalPostForm($row_options, $edit, t('Apply'));

    $this->drupalPostForm(NULL, array(), t('Save'));

    $view = Views::getView('test_serializer_display_field');
    $view->setDisplay('rest_export_1');
    $this->executeView($view);

    $expected = array();
    foreach ($view->result as $row) {
      $expected_row = array();
      foreach ($view->field as $id => $field) {
        $expected_row[$alias_map[$id]] = $field->render($row);
      }
      $expected[] = $expected_row;
    }

    $this->assertIdentical($this->drupalGetJSON('test/serialize/field'), $expected);
  }


  /**
   * Tests the Serializer paths and responses for field-based views.
   */
  public function testSerializerFieldDisplayResponse() {
    $view = Views::getView('test_serializer_display_field');
    $view->setDisplay('rest_export_1');
    // Mock the request content type by setting it on the display handler.
    $view->display_handler->setContentType('hal_json');
    $this->executeView($view);

    $view_output = $view->preview();
    $view_result = array();
    foreach ($view->result as $row) {
      $expected_row = array();
      foreach ($view->field as $id => $field) {
        $expected_row[$id] = $field->render($row);
      }
      $view_result[] = $expected_row;
    }

    $serializer = $this->container->get('serializer');
    $collection = $this->getCollectionFromView($view);
    $expected = $serializer->serialize($collection, 'hal_json');

    $actual_json = $this->drupalGetHalJson('test/serialize/field');

    $this->assertIdentical(Json::encode($actual_json), drupal_render($view_output), 'Preview output matches the (reserialized) JSON returned from the view via HTTP GET.');
    $this->assertIdentical(Json::encode($actual_json), $expected, 'HAL serializer output matches the (reserialized) JSON returned from the view via HTTP GET.');
    $this->assertIdentical($actual_json['_embedded']['item'], $view_result, 'View result matches JSON returned from the view via HTTP GET');
  }

  /**
   * Tests the Serializer paths and responses for field-based views with paging.
   */
  public function testSerializerFieldDisplayPagingResponse() {
    $view = Views::getView('test_serializer_display_field');
    $view->setDisplay('rest_export_paging');
    // Mock the request content type by setting it on the display handler.
    $view->display_handler->setContentType('hal_json');
    $this->executeView($view);

    $view_output = $view->preview();
    $view_result = array();
    foreach ($view->result as $row) {
      $expected_row = array();
      foreach ($view->field as $id => $field) {
        $expected_row[$id] = $field->render($row);
      }
      $view_result[] = $expected_row;
    }

    $serializer = $this->container->get('serializer');
    $collection = $this->getCollectionFromView($view);
    $expected = $serializer->serialize($collection, 'hal_json');

    $actual_json = $this->drupalGetHalJson('test/serialize/field-paging');

    $this->assertIdentical(Json::encode($actual_json), drupal_render($view_output), 'Preview output matches the (reserialized) JSON returned from the view via HTTP GET.');
    $this->assertIdentical(Json::encode($actual_json), $expected, 'HAL serializer output matches the (reserialized) JSON returned from the view via HTTP GET.');
    $this->assertIdentical($actual_json['_embedded']['item'], $view_result, 'View result matches JSON returned from the view via HTTP GET');

    // Make assertions on the structure of the response.
    $this->assertTrue(isset($actual_json['_embedded']) && isset($actual_json['_links']), 'Has _links and _embedded keys');

    $this->assertEqual(count($actual_json['_embedded']['item']), 1);
    $this->assertEqual($actual_json['_links']['self']['href'], $this->viewUrl($view));
    $this->assertEqual($actual_json['_links']['first']['href'], $this->viewUrl($view, 0));
    $this->assertEqual($actual_json['_links']['next']['href'], $this->viewUrl($view, 1));
    $this->assertEqual($actual_json['_links']['last']['href'], $this->viewUrl($view, 4));
    $this->assertEqual(array_keys($actual_json['_links']), array(
      'self',
      'first',
      'next',
      'last',
    ));

    // Load the second page.
    $actual_json_page_1 = $this->drupalGetHalJson($actual_json['_links']['next']['href']);

    $this->assertTrue(isset($actual_json_page_1['_embedded']) && isset($actual_json_page_1['_links']), 'Has _links and _embedded keys');

    $this->assertEqual(count($actual_json_page_1['_embedded']['item']), 1);
    $this->assertEqual($actual_json_page_1['_links']['self']['href'], $this->viewUrl($view, 1));
    $this->assertEqual($actual_json_page_1['_links']['first']['href'], $this->viewUrl($view, 0));
    $this->assertEqual($actual_json_page_1['_links']['prev']['href'], $this->viewUrl($view, 0));
    $this->assertEqual($actual_json_page_1['_links']['next']['href'], $this->viewUrl($view, 2));
    $this->assertEqual($actual_json_page_1['_links']['last']['href'], $this->viewUrl($view, 4));
    $this->assertEqual(array_keys($actual_json_page_1['_links']), array(
      'self',
      'first',
      'prev',
      'next',
      'last',
    ));

    // Test the entity rows - with paging.
    $view = Views::getView('test_serializer_display_field');
    $view->setDisplay('rest_export_paging');
    $view->setCurrentPage(1);
    $this->executeView($view);

    // Create the entity collection.
    $collection = $this->getCollectionFromView($view);
    $this->assertTrue($collection->hasLinks(), 'Collection created from a paging view has (hypermedia) link relations');
    $expected = $serializer->serialize($collection, 'hal_json');

    $this->assertEqual(Json::encode($actual_json_page_1), $expected, 'The expected HAL output for page=1 was found.');

    // Load the last page.
    $actual_json_page_last = $this->drupalGetHalJson($actual_json['_links']['last']['href']);

    $this->assertTrue(isset($actual_json_page_last['_embedded']) && isset($actual_json_page_last['_links']), 'Has _links and _embedded keys');

    $this->assertEqual(count($actual_json_page_last['_embedded']['item']), 1);
    $this->assertEqual($actual_json_page_last['_links']['self']['href'], $this->viewUrl($view, 4));
    $this->assertEqual($actual_json_page_last['_links']['first']['href'], $this->viewUrl($view, 0));
    $this->assertEqual($actual_json_page_last['_links']['prev']['href'], $this->viewUrl($view, 3));
    $this->assertEqual($actual_json_page_last['_links']['last']['href'], $this->viewUrl($view, 4));
    $this->assertEqual(array_keys($actual_json_page_last['_links']), array(
      'self',
      'first',
      'prev',
      'last',
    ));

    // Test the entity rows - with paging.
    $view = Views::getView('test_serializer_display_field');
    $view->setDisplay('rest_export_paging');
    $view->setCurrentPage(4);
    $this->executeView($view);

    // Create the entity collection.
    $collection = $this->getCollectionFromView($view);
    $this->assertTrue($collection->hasLinks(), 'Collection created from a paging view has (hypermedia) link relations');
    $expected = $serializer->serialize($collection, 'hal_json');

    $this->assertEqual(Json::encode($actual_json_page_last), $expected, 'The expected HAL output for last page was found.');
  }

  /**
   * Tests the raw output options for row field rendering.
   */
  public function testFieldRawOutput() {
    $this->drupalLogin($this->adminUser);

    // Test the UI settings for adding field ID aliases.
    $this->drupalGet('admin/structure/views/view/test_serializer_display_field/edit/rest_export_1');
    $row_options = 'admin/structure/views/nojs/display/test_serializer_display_field/rest_export_1/row_options';
    $this->assertLinkByHref($row_options);

    // Test an empty string for an alias, this should not be used. This also
    // tests that the form can be submitted with no aliases.
    $this->drupalPostForm($row_options, array('row_options[field_options][created][raw_output]' => '1'), t('Apply'));
    $this->drupalPostForm(NULL, array(), t('Save'));

    $view = Views::getView('test_serializer_display_field');
    $view->setDisplay('rest_export_1');
    $this->executeView($view);

    // Just test the raw 'created' value against each row.
    foreach ($this->drupalGetJSON('test/serialize/field') as $index => $values) {
      $this->assertIdentical($values['created'], $view->result[$index]->views_test_data_created, 'Expected raw created value found.');
    }
  }

  /**
   * Tests the live preview output for json output.
   */
  public function testLivePreview() {
    // We set up a request so it looks like an request in the live preview.
    $request = new Request();
    $request->setFormat('drupal_ajax', 'application/vnd.drupal-ajax');
    $request->headers->set('Accept', 'application/vnd.drupal-ajax');
      /** @var \Symfony\Component\HttpFoundation\RequestStack $request_stack */
    $request_stack = \Drupal::service('request_stack');
    $request_stack->push($request);

    $view = Views::getView('test_serializer_display_entity');
    $view->setDisplay('rest_export_1');
    $view->display_handler->setContentType('json');
    $this->executeView($view);

    // Get the serializer service.
    $serializer = $this->container->get('serializer');

    // Create the collection.
    $collection = $this->getCollectionFromView($view);
    $expected = SafeMarkup::checkPlain($serializer->serialize($collection, 'json'));

    $view->live_preview = TRUE;

    $build = $view->preview();
    $rendered_json = $build['#markup'];
    $this->assertEqual($rendered_json, $expected, 'Ensure the previewed json is escaped.');
    $view->destroy();

    $expected = SafeMarkup::checkPlain($serializer->serialize($rendered_json, 'xml'));

    // Change the request format to xml.
    $view->setDisplay('rest_export_1');
    $view->getDisplay()->setOption('style', array(
      'type' => 'serializer',
      'options' => array(
        'uses_fields' => FALSE,
        'formats' => array(
          'xml' => 'xml',
        ),
      ),
    ));

    $this->executeView($view);
    $build = $view->preview();
    $rendered_xml = $build['#markup'];
    $this->assertEqual($rendered_xml, $expected, 'Ensure we preview xml when we change the request format.');
  }

  /**
   * Tests the views interface for rest export displays.
   */
  public function testSerializerViewsUI() {
    $this->drupalLogin($this->adminUser);
    // Click the "Update preview button".
    $this->drupalPostForm('admin/structure/views/view/test_serializer_display_field/edit/rest_export_1', $edit = array(), t('Update preview'));
    $this->assertResponse(200);
    // Check if we receive the expected result.
    $result = $this->xpath('//div[@id="views-live-preview"]/pre');
    $this->assertIdentical($this->drupalGet('test/serialize/field'), (string) $result[0], 'The expected JSON preview output was found.');
  }

  /**
   * Tests the field row style using fieldapi fields.
   */
  public function testFieldapiField() {
    $this->drupalCreateContentType(array('type' => 'page'));
    $node = $this->drupalCreateNode();

    $result = $this->drupalGetJSON('test/serialize/node-field');
    $this->assertEqual($result[0]['nid'], $node->id());
    $this->assertEqual($result[0]['body'], $node->body->processed);

    // @todo: add HAL+JSON and paging.
  }

}
