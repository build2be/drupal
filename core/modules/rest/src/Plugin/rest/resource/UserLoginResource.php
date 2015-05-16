<?php

/**
 * @file
 * Contains Drupal\rest\Plugin\rest\resource\UserLoginResource.
 */

namespace Drupal\rest\Plugin\rest\resource;

use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\user\Entity\User;

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

    switch ($operation['op']) {

      case 'login':
        if (!empty($operation['credentials'])) {
          return $this->login($operation['credentials']);
        }
        return new ResourceResponse('Missing credentials.', 400, array());

      case 'logout':
        return $this->logout();

      default:
        return new ResourceResponse('Unsupported op.', 400, array());

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
    // Verify that the username is filled.
    if (!array_key_exists('name', $credentials)) {
      return new ResourceResponse('Missing credentials.name.', 400, array());
    }
    // Verify that the username is filled.
    if (!array_key_exists('pass', $credentials)) {
      return new ResourceResponse('Missing credentials.pass.', 400, array());
    }

    // Flood control.
    if ($this->restFloodControl(\Drupal::config('user.flood'), 'rest.login_cookie')) {
      return new ResourceResponse('Blocked.', 400, array());
    }

    // Log in the user.
    if ($uid = \Drupal::service('user.auth')->authenticate($credentials['name'], $credentials['pass'])) {
      $user = User::load($uid);
      user_login_finalize($user);
      return new ResourceResponse('You are logged in as ' . $credentials['name'], 200, array());
    }
    $this->flood->register('rest.login_cookie', \Drupal::config('user.flood')->get('user_window'));
    return new ResourceResponse('Sorry, unrecognized username or password.', 400, array());
  }

  /**
   * User Logout.
   *
   * @return ResourceResponse
   */
  protected function logout() {
    user_logout();
    return new ResourceResponse('Logged out!', 200, array());
  }

}
