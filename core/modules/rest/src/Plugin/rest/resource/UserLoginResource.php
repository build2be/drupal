<?php

/**
 * @file
 * Contains Drupal\rest\Plugin\rest\resource\UserLoginResource.
 */

namespace Drupal\rest\Plugin\rest\resource;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Allows user logins by setting session cookies.
 *
 * @RestResource(
 *   id = "user_login",
 *   label = @Translation("User Login")
 * )
 */
class UserLoginResource extends ResourceBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $flood;

  /**
   * Constructs a new RestPermissions instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param LoggerInterface $loggery
   *   A logger instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood control mechanism.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, ConfigFactoryInterface $config_factory, FloodInterface $flood) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger, $flood);
    $this->configFactory = $config_factory;
    $this->flood = $flood;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('config.factory'),
      $container->get('flood')
    );
  }

  /**
   * Responds to the user login POST requests and log in a user.
   *
   * @param string[] $operation
   *   array(
   *     'op' => 'login', 'logout'
   *     'credentials' => array(
   *       'name' => 'your-name',
   *       'pass' => 'your-password',
   *     ),
   *   )
   *
   *   The operation and username + pass for the login op.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   */
  public function post(array $operation = array()) {

    if (array_key_exists('op', $operation)) {
      switch ($operation['op']) {

        case 'login':
          if (!array_key_exists('credentials', $operation)) {
            $operation['credentials'] = array();
          }
          return $this->login($operation['credentials']);

        case 'status':
          return $this->status();

        case 'logout':
          return $this->logout();

        default:
          // TODO: do we have to escape?
          throw new BadRequestHttpException('Unsupported op '. Html::escape($operation['op']) . '.');

      }
    }
    else {
      throw new BadRequestHttpException('No op found. Use: status, login, logout.');
    }
  }

  /**
   * User login.
   *
   * @param array $credentials
   *   The username and pass for the user.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object
   */
  protected function login(array $credentials = array()) {

    if (empty($credentials)) {
      throw new BadRequestHttpException('Missing credentials.');
    }

    // Verify that the username is filled.
    if (!array_key_exists('name', $credentials)) {
      throw new BadRequestHttpException('Missing credentials.name.');
    }
    // Verify that the password is filled.
    if (!array_key_exists('pass', $credentials)) {
      throw new BadRequestHttpException('Missing credentials.pass.');
    }

    // Flood control.
    if ($this->restFloodControl($this->configFactory->get('user.flood'), 'rest.login_cookie')) {
      throw new BadRequestHttpException('Blocked.');
    }

    // Verify that the user is not blocked.
    if ($this->userIsBlocked($credentials['name'])) {
      throw new BadRequestHttpException('The user has not been activated or is blocked.');
    }

    // Log in the user.
    if ($uid = \Drupal::service('user.auth')->authenticate($credentials['name'], $credentials['pass'])) {
      $user = User::load($uid);
      user_login_finalize($user);
      return new ResourceResponse('You are logged in as ' . $credentials['name'] . '.', 200, array());
    }

    $this->flood->register('rest.login_cookie', $this->configFactory->get('user.flood')->get('user_window'));
    throw new BadRequestHttpException('Sorry, unrecognized username or password.');
  }

  protected function status() {
    if (\Drupal::currentUser()->isAuthenticated()) {
      return new ResourceResponse('You are logged in.', 200, array());
    }
    return new ResourceResponse('You are not logged in.', 200, array());
  }

    /**
   * User Logout.
   *
   * @return ResourceResponse
   */
  protected function logout() {
    user_logout();
    return new ResourceResponse('You are logged out.', 200, array());
  }

  /**
   * Verifies if the user is blocked.
   *
   * @param string $name
   * @return bool
   */
  protected function userIsBlocked($name) {
    return user_is_blocked($name);
  }

  /**
   * Checks for flooding.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   * @param $name
   * @return bool
   */
  protected function restFloodControl($config, $name) {
    $limit = $config->get('user_limit');
    $interval = $config->get('user_window');
    if (!$this->flood->isAllowed($name, $limit, $interval)) {
      return TRUE;
    }
    return FALSE;
  }

}
