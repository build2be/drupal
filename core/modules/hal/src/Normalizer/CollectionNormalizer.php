<?php

/**
 * @file
 * Contains \Drupal\hal\Normalizer\CollectionNormalizer.
 */

namespace Drupal\hal\Normalizer;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Language\Language;
use Drupal\rest\LinkManager\LinkManagerInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Converts the Drupal entity object structure to a HAL array structure.
 */
class CollectionNormalizer extends NormalizerBase {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\serialization\Collection';

  /**
   * The collection link manager.
   *
   * @var \Drupal\rest\LinkManager\CollectionLinkManagerInterface
   */
  protected $linkManager;

  /**
   * Constructs an EntityNormalizer object.
   *
   * @param \Drupal\rest\LinkManager\LinkManagerInterface $link_manager
   *   The hypermedia link manager.
   */
  public function __construct(LinkManagerInterface $link_manager) {
    $this->linkManager = $link_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = array()) {
    // Create the array of normalized properties, starting with the URI.
    $normalized = array(
      '_links' => array(
        'self' => array(
          'href' => $object->getUri(),
        ),
      ),
    );

    // If we have additional hypermedia links add them here.
    $links = $object->getLinks();
    if (is_array($links) && count($links)) {
      foreach ($links as $key => $link) {
        $normalized['_links'][$key] = array('href' => $link);
      }
    }

    // Add the list of items.
    $link_relation = $this->linkManager->getCollectionItemRelation($object->getCollectionId());
    $normalized['_embedded'][$link_relation] = $this->serializer->normalize($object->getItems(), $format, $context);

    return $normalized;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Implement denormalization once normalization has settled.
   */
  public function denormalize($data, $class, $format = NULL, array $context = array()) {
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return FALSE;
  }
}
