<?php

/**
 * @file
 * Contains \Drupal\serialization\Tests\CollectionTest.
 */

namespace Drupal\serialization\Tests;

use Drupal\serialization\Collection;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Collection class.
 *
 * @group Serialization
 *
 * @coversDefaultClass \Drupal\serialization\Collection
 */
class CollectionTest extends UnitTestCase {
  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'CollectionTest',
      'description' => 'Tests the Collection class used for serializing collections.',
      'group' => 'Serialization',
    );
  }

  /**
   * Tests the constructor, as well as getters and setters.
   *
   * @covers ::__construct
   * @covers ::getTitle
   * @covers ::setTitle
   * @covers ::getURI
   * @covers ::setURI
   * @covers ::getDescription
   * @covers ::setDescription
   */
  public function testConstructor() {
    $collection_id = $this->randomName();
    $collection = new Collection($collection_id);
    $this->assertSame($collection_id, $collection->getCollectionId(), 'Id has been set accordingly');

    $this->assertEquals($collection->getTitle(), NULL, 'Collection title is not set');
    $collection->setTitle($collection_id);
    $this->assertEquals($collection->getTitle(), $collection_id, 'Collection title has been set');

    $this->assertEquals($collection->getUri(), NULL, 'Collection URI is not set');
    $collection->setURI('http://example.com/' . $collection_id);
    $this->assertEquals($collection->getUri(), 'http://example.com/' . $collection_id, 'Collection URI has been set');

    $this->assertEquals($collection->getDescription(), NULL, 'Collection description is not set');
    $collection->setDescription($collection_id);
    $this->assertEquals($collection->getDescription(), $collection_id, 'Collection description has been set');
  }

  /**
   * Tests items setter and getter as well as iteration.
   *
   * @covers ::setItems
   * @covers ::getItems
   * @covers ::getIterator
   */
  public function testSettingItemsAndIterating() {
    $collection = new Collection('example');

    $this->assertEquals($collection->getItems(), NULL, 'By default the  items are NULL');

    $example = array(1, 2, 3);
    $collection->setItems($example);
    $this->assertEquals($collection->getItems(), $example, 'Setters and getters work on the Collection');

    // Iterating over collection returns the same array.
    $array = array();
    foreach ($collection as $k => $v) {
      $array[$k] = $v;
    }
    $this->assertEquals($array, $collection->getItems(), 'Array filled via iteration matches the array of the getter');
  }

  /**
   * Tests setters and getters for link relations.
   *
   * @covers ::setLinks
   * @covers ::getLinks
   * @covers ::hasLinks
   * @covers ::getLink
   * @covers ::setLink
   */
  public function testSetGetHasLinks() {
    $collection = new Collection('example');
    $this->assertFalse($collection->hasLinks(), 'By default a Collection does not have any link relations');

    $collection->setURI('http://example.com/collection');
    $this->assertFalse($collection->hasLinks(), 'Setting the main URI (self) of the collection does not count as a link relation');

    $this->assertEquals($collection->getLink('next'), NULL, 'Before setting a link for a type the getter returns NULL');
    $collection->setLink('next', 'http://example.com/collection/?page=1');
    $this->assertEquals($collection->getLink('next'), 'http://example.com/collection/?page=1', 'After setting a link for a type the getter returns correctly');
    $this->assertTrue($collection->hasLinks(), 'Setting another URI (self) of the collection does not count as a link relation');
    $this->assertEquals($collection->getLinks(), array('next' => 'http://example.com/collection/?page=1'));

    $collection->setLinks(array());
    $this->assertFalse($collection->hasLinks(), 'After resetting links to an empty array, hasLinks should return false');

    $collection->setLinks(NULL);
    $this->assertFalse($collection->hasLinks(), 'After resetting links to NULL, hasLinks should return false');
  }
}
