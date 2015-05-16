<?php

/**
 * @file
 * Contains \Drupal\rest\Plugin\views\style\Serializer.
 */

namespace Drupal\rest\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\CacheablePluginInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\serialization\Collection;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * The style plugin for serialized output formats.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "serializer",
 *   title = @Translation("Serializer"),
 *   help = @Translation("Serializes views row data using the Serializer component."),
 *   display_types = {"data"}
 * )
 */
class Serializer extends StylePluginBase implements CacheablePluginInterface {

  /**
   * Overrides \Drupal\views\Plugin\views\style\StylePluginBase::$usesRowPlugin.
   */
  protected $usesRowPlugin = TRUE;

  /**
   * Overrides Drupal\views\Plugin\views\style\StylePluginBase::$usesFields.
   */
  protected $usesGrouping = FALSE;

  /**
   * The serializer which serializes the views result.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The available serialization formats.
   *
   * @var array
   */
  protected $formats = [];

  /**
   * The URL generator service.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('serializer'),
      $container->getParameter('serializer.formats'),
      $container->get('url_generator'),
      $container->get('state')
    );
  }

  /**
   * Constructs a Plugin object.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, SerializerInterface $serializer, array $serializer_formats, UrlGeneratorInterface $url_generator, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->definition = $plugin_definition + $configuration;
    $this->serializer = $serializer;
    $this->formats = $serializer_formats;
    $this->urlGenerator = $url_generator;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['formats'] = array('default' => array());

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['formats'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Accepted request formats'),
      '#description' => $this->t('Request formats that will be allowed in responses. If none are selected all formats will be allowed.'),
      '#options' => array_combine($this->formats, $this->formats),
      '#default_value' => $this->options['formats'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);

    $formats = $form_state->getValue(array('style_options', 'formats'));
    $form_state->setValue(array('style_options', 'formats'), array_filter($formats));
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return $this->serializer->serialize($this->getCollection(), $this->displayHandler->getContentType());
  }

  /**
   * Gets a list of all available formats that can be requested.
   *
   * This will return the configured formats, or all formats if none have been
   * selected.
   *
   * @return array
   *   An array of formats.
   */
  public function getFormats() {
    return $this->options['formats'];
  }

  /**
   * {@inheritdoc}
   */
  public function isCacheable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['request_format'];
  }

  /**
   * Instantiate Collection object needed to encapsulate serialization.
   *
   * @return \Drupal\serialization\Collection
   *   Collection object wrapping items/rows of the view.
   */
  public function getCollection() {
    $this->view = $this->view;

    $display = $this->view->getDisplay();
    // Build full view-display id.
    $view_id = $this->view->storage->id();
    $display_id = $display->display['id'];

    // Instantiate collection object.
    $collection = new Collection($view_id . '_' . $display_id);

    $collection->setTitle($this->view->getTitle());
    $collection->setDescription($display->getOption('display_description'));

    // Route as defined in e.g. \Drupal\rest\Plugin\views\display\RestExport.
    $route_names = $this->state->get('views.view_route_names');
    $route_name = $route_names["$view_id.$display_id"];

    // Get base url path for the view; getUrl returns a path not an absolute
    // URL (and no page information).
    $view_base_url = $this->view->getUrl();

    // Inject the page into the canonical URI of the view.
    if ($this->view->getCurrentPage() > 0) {
      $uri = $this->urlGenerator->generateFromRoute($route_name, [], array('query' => array('page' => $this->view->getCurrentPage()), 'absolute' => TRUE));
    }
    else {
      $uri = $this->urlGenerator->generateFromRoute($route_name, [], array('absolute' => TRUE));
    }
    $collection->setUri($uri);

    $rows = [];
    foreach ($this->view->result as $row) {
      $rows[] = $this->view->rowPlugin->render($row);
    }
    $collection->setItems($rows);

    $pager = $this->view->getPager();

    // Determine whether we have more items than we are showing, in that case
    // we are a pageable collection.
    if ($pager->getTotalItems() > $pager->getItemsPerPage()) {
      // Calculate pager links.
      $current_page = $pager->getCurrentPage();
      // Starting at page=0 we need to decrement.
      $total = ceil($pager->getTotalItems() / $pager->getItemsPerPage()) - 1;

      $collection->setLink('first', $this->urlGenerator->generateFromRoute($route_name, [], array(
        'query' => array('page' => 0),
        'absolute' => TRUE,
      )));

      // If we are not on the first page add a previous link.
      if ($current_page > 0) {
        $collection->setLink('prev', $this->urlGenerator->generateFromRoute($route_name, [], array(
          'query' => array('page' => $current_page - 1),
          'absolute' => TRUE,
        )));
      }

      // If we are not on the last page add a next link.
      if ($current_page < $total) {
        $collection->setLink('next', $this->urlGenerator->generateFromRoute($route_name, [], array(
          'query' => array('page' => $current_page + 1),
          'absolute' => TRUE,
        )));
      }

      $collection->setLink('last', $this->urlGenerator->generateFromRoute($route_name, [], array(
        'query' => array('page' => $total),
        'absolute' => TRUE,
      )));
    }

    return $collection;
  }
}
