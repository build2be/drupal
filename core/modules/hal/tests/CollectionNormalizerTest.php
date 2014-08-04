<?php
/**
 * @file
 * Contains \Drupal\hal\Tests\CollectionNormalizerTest.
 */

namespace Drupal\hal\Tests;

use Drupal\rest\LinkManager\LinkManager;

use Drupal\hal\Normalizer\CollectionNormalizer;
use Drupal\serialization\Collection;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the CollectionNormalizer's normalize support.
 *
 * @group HAL
 */
class CollectionNormalizerTest extends UnitTestCase {
  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'CollectionNormalizer test',
      'description' => "Unit test of the CollectionNormalizer's normalize support.",
      'group' => 'HAL',
    );
  }

  /**
   * Tests the supportsNormalization method.
   */
  public function testSupportsNormalization() {
    $collection = $this->getCollection();
    $normalizer = new CollectionNormalizer($this->getLinkManagerStub());
    $this->assertTrue($normalizer->supportsNormalization($collection, 'hal_json'));
    $this->assertFalse($normalizer->supportsNormalization($collection, 'json'));
    $this->assertFalse($normalizer->supportsNormalization(new \stdClass(), 'hal_json'));
  }

  /**
   * Tests the normalize method.
   */
  public function testNormalize() {
    $test_values = $this->getTestValues();
    $collection = $this->getCollection();

    // Create the normalizer and inject the LinkManagerStub.
    $normalizer = new CollectionNormalizer($this->getLinkManagerStub());
    // Inject the Serializer. Handle the call to Serializer::normalize,
    // ensuring that the items array is passed in.
    $serializer = $this->getSerializerStub();
    $serializer->expects($this->any())
      ->method('normalize')
      ->with($collection->getItems())
      ->will($this->returnValue($test_values['items']));
    $normalizer->setSerializer($serializer);
    // Get the normalized array.
    $normalized = $normalizer->normalize($collection, 'hal_json');

    // Test that self link points to collection URI.
    $this->assertEquals($normalized['_links']['self']['href'], $test_values['uri']);
    // There should only be a self-key.
    $this->assertEquals(array_keys($normalized['_links']), array('self'));

    // Test that the correct link relation was retrieved from the LinkManager
    // and added to _embedded.
    $this->assertArrayHasKey($test_values['item_link_relation'], $normalized['_embedded']);
    // Test that the item link relation points to the serialized item array.
    $this->assertEquals($normalized['_embedded'][$test_values['item_link_relation']], $test_values['items']);
  }

  /**
   * Tests the normalize method on pageable collection.
   */
  public function testNormalizePageableCollection() {
    $test_values = $this->getTestValues();
    $collection = $this->getPageableCollection();

    // Create the normalizer and inject the LinkManagerStub.
    $normalizer = new CollectionNormalizer($this->getLinkManagerStub());
    // Inject the Serializer. Handle the call to Serializer::normalize,
    // ensuring that the items array is passed in.
    $serializer = $this->getSerializerStub();
    $serializer->expects($this->any())
      ->method('normalize')
      ->with($collection->getItems())
      ->will($this->returnValue($test_values['items']));
    $normalizer->setSerializer($serializer);
    // Get the normalized array.
    $normalized = $normalizer->normalize($collection, 'hal_json');

    // Test that self link points to collection URI.
    $this->assertEquals($normalized['_links']['self']['href'], $test_values['uri']);
    // There should be the self-key, _first, _prev, _next and _last-keys.
    $this->assertArrayHasKey('self', $normalized['_links']);
    $this->assertArrayHasKey('first', $normalized['_links']);
    $this->assertArrayHasKey('prev', $normalized['_links']);
    $this->assertArrayHasKey('next', $normalized['_links']);
    $this->assertArrayHasKey('last', $normalized['_links']);
    $this->assertEquals(array_keys($normalized['_links']),
      array('self', 'first', 'prev', 'next', 'last')
    );

    // Test that the correct link relation was retrieved from the LinkManager
    // and added to _embedded.
    $this->assertArrayHasKey($test_values['item_link_relation'], $normalized['_embedded']);
    // Test that the item link relation points to the serialized item array.
    $this->assertEquals($normalized['_embedded'][$test_values['item_link_relation']], $test_values['items']);
  }

  /**
   * Get an \Drupal\serialization\Collection for testing.
   *
   * @return \Drupal\serialization\Collection
   *   The Collection object, configured with test values.
   */
  protected function getCollection() {
    $test_values = $this->getTestValues();

    // Get a dummy node.
    $node = $this->getMockBuilder('Drupal\node\Entity\Node')
      ->disableOriginalConstructor()
      ->getMock();

    $collection = new Collection('test_id');
    $collection->setUri($test_values['uri']);
    $collection->setItems(array($node));

    return $collection;
  }

  /**
   * Get an pageable \Drupal\serialization\Collection for testing.
   *
   * @return \Drupal\serialization\Collection
   *   The Collection object, configured with test values.
   */
  protected function getPageableCollection() {
    $test_values = $this->getTestValues();

    // Get a dummy node.
    $node = $this->getMockBuilder('Drupal\node\Entity\Node')
      ->disableOriginalConstructor()
      ->getMock();

    $collection = new Collection('test_id');
    $collection->setUri($test_values['uri']);
    $collection->setItems(array($node));

    $collection->setLinks(array(
      'first' => $test_values['uri'] . '?page=0',
      'prev' => $test_values['uri'] . '?page=0',
      'next' => $test_values['uri'] . '?page=2',
      'last' => $test_values['uri'] . '?page=2',
    ));

    return $collection;
  }

  /**
   * Get a stub LinkManager for testing.
   *
   * @return \PHPUnit_Framework_MockObject_MockObject
   *   The LinkManager stub.
   */
  protected function getLinkManagerStub() {
    $test_values = $this->getTestValues();

    $link_manager = $this->getMockBuilder('Drupal\rest\LinkManager\LinkManager')
      ->disableOriginalConstructor()
      ->getMock();

    $link_manager->expects($this->any())
      ->method('getCollectionItemRelation')
      ->will($this->returnValue($test_values['item_link_relation']));

    return $link_manager;
  }

  /**
   * Get a stub Serializer for testing.
   *
   * @return \PHPUnit_Framework_MockObject_MockObject
   *   The Serializer stub.
   */
  protected function getSerializerStub() {
    $serializer = $this->getMockBuilder('Symfony\Component\Serializer\Serializer')
      ->disableOriginalConstructor()
      ->getMock();

    return $serializer;
  }

  /**
   * Get the array of test values.
   *
   * @return array
   *   An array of test values, used for configuring stub methods and testing.
   */
  protected function getTestValues() {
    return array(
      'item_link_relation' => 'item',
      'items' => 'Array of serialized entities goes here',
      'uri' => 'http://example.com/test-path',
    );
  }
}
